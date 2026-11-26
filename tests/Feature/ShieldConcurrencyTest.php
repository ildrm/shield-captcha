<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Shield\ApiProblem;
use App\Shield\Captcha;
use App\Shield\SiteKeys;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

class ShieldConcurrencyTest extends TestCase
{
    public function test_innodb_commits_exactly_one_simultaneous_solve_and_consume(): void
    {
        if (! in_array(DB::getDefaultConnection(), ['mysql', 'mariadb'], true) || ! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Requires the isolated local MariaDB/MySQL test database, pcntl and POSIX extensions.');
        }

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $engine = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'shield_tokens')->value('ENGINE');
        $this->assertSame('InnoDB', $engine);

        $organization = Organization::query()->create(['name' => 'Race fixture', 'slug' => 'race-'.bin2hex(random_bytes(6))]);

        try {
            $site = $organization->sites()->create([
                'name' => 'Race fixture site',
                'public_key' => 'site_test_'.bin2hex(random_bytes(16)),
                'origin' => 'https://race-fixture.example',
                'environment' => 'sandbox',
                'actions' => ['login'],
            ]);
            $secret = app(SiteKeys::class)->issue($site, 'verify');
            $challenge = app(Captcha::class)->issue($site->public_key, 'login', $site->origin);
            $number = $this->solution($challenge);

            $solves = $this->race($organization->id, fn (): array => app(Captcha::class)->solve($challenge['id'], $site->public_key, 'login', $site->origin, $number));
            $this->assertEqualsCanonicalizing(['accepted', 'challenge_already_solved'], array_column($solves, 'outcome'));
            $this->assertSame(1, DB::table('shield_tokens')->where('challenge_id', $challenge['id'])->count());
            $token = array_column(array_column($solves, 'data'), 'token')[0];

            $verifications = $this->race($organization->id, fn (): array => app(Captcha::class)->verify($secret, $token, $site->public_key, 'login', $site->origin, 'sandbox'));
            $this->assertEqualsCanonicalizing(['accepted', 'token_already_used'], array_column($verifications, 'outcome'));
            $this->assertSame(1, DB::table('shield_usage_events')->where('site_id', $site->id)->count());
        } finally {
            $organization->delete();
        }
    }

    private function race(int $organizationId, Closure $operation): array
    {
        // PDO sockets cannot be shared with the child connections after a fork.
        DB::disconnect();
        $workers = [];

        try {
            for ($index = 0; $index < 2; $index++) {
                $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
                $this->assertIsArray($pair);
                [$parent, $child] = $pair;
                $pid = pcntl_fork();

                if ($pid === 0) {
                    fclose($parent);
                    foreach ($workers as $worker) {
                        fclose($worker['stream']);
                    }

                    $this->runWorker($child, $operation);
                }

                fclose($child);

                if ($pid < 0) {
                    fclose($parent);
                    $this->fail('Could not start the local database race worker.');
                }

                stream_set_timeout($parent, 6);
                $workers[] = ['pid' => $pid, 'stream' => $parent];
            }

            // Block both operations on the same organization row until both connections are ready.
            DB::beginTransaction();
            Organization::query()->lockForUpdate()->findOrFail($organizationId);

            foreach ($workers as $worker) {
                fwrite($worker['stream'], 'g');
            }

            foreach ($workers as $worker) {
                $this->assertSame('r', fread($worker['stream'], 1));
            }

            usleep(100_000);
            DB::commit();

            return array_map(function (array $worker): array {
                $result = fgets($worker['stream'], 1024);
                $this->assertIsString($result, 'Database race worker did not respond.');

                return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
            }, $workers);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($workers as $worker) {
                fclose($worker['stream']);

                if (pcntl_waitpid($worker['pid'], $status, WNOHANG) === 0) {
                    posix_kill($worker['pid'], SIGKILL);
                    pcntl_waitpid($worker['pid'], $status);
                }
            }
        }
    }

    private function runWorker($stream, Closure $operation): never
    {
        stream_set_timeout($stream, 6);

        if (fread($stream, 1) !== 'g') {
            exit(1);
        }

        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 4');
            fwrite($stream, 'r');
            $result = ['outcome' => 'accepted', 'data' => $operation()];
        } catch (ApiProblem $error) {
            $result = ['outcome' => $error->errorCode];
        } catch (Throwable $error) {
            $result = ['outcome' => get_class($error)];
        }

        fwrite($stream, json_encode($result, JSON_THROW_ON_ERROR)."\n");
        fclose($stream);
        exit(0);
    }

    private function solution(array $challenge): int
    {
        for ($number = 0; $number <= $challenge['max_number']; $number++) {
            if (hash('sha256', $challenge['salt'].$number) === $challenge['challenge']) {
                return $number;
            }
        }

        $this->fail('No valid result within the advertised puzzle bounds.');
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Tenancy\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProvisionOperator extends Command
{
    protected $signature = 'shield:operator {email : A new dedicated operator email}';

    protected $description = 'Create a dedicated operator with an interactive password';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255|unique:users'])->fails()) {
            $this->error('Use a valid email address that is not already registered.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Run this command in an interactive terminal.');

            return self::FAILURE;
        }

        $password = $this->secret('Operator password (at least 16 characters)', false);
        $confirmation = $this->secret('Confirm operator password', false);
        if (! is_string($password) || $password !== $confirmation || mb_strlen($password) < 16 || strlen($password) > 72) {
            $this->error('Passwords must match and contain at least 16 characters and at most 72 bytes.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($email, $password): void {
            $user = new User(['name' => 'Platform operator', 'email' => $email, 'password' => $password]);
            $user->forceFill(['is_operator' => true, 'email_verified_at' => now()])->save();
            Audit::record($user, 'operator.provisioned');
        });

        $this->info('The operator can sign in at /admin/login and enroll an authenticator app.');

        return self::SUCCESS;
    }
}

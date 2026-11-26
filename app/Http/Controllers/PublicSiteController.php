<?php

namespace App\Http\Controllers;

use App\Support\ReleaseCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class PublicSiteController extends Controller
{
    public function home(Request $request): View
    {
        return $this->page($request, 'home');
    }

    public function docs(Request $request): View
    {
        return $this->page($request, 'docs');
    }

    public function playground(Request $request): View
    {
        $validator = Validator::make($request->query(), [
            'site_key' => ['nullable', 'string', 'regex:/^site_test_[a-f0-9]{32}$/D'],
            'action' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_-]{0,63}$/D'],
        ]);
        $invalid = $validator->fails();

        return $this->page($request, 'playground', [
            'settings' => $invalid ? [] : $validator->validated(),
            'invalid' => $invalid,
        ]);
    }

    public function security(Request $request): View
    {
        return $this->page($request, 'security');
    }

    public function status(Request $request): View
    {
        return $this->page($request, 'status', ['ready' => $this->isReady()]);
    }

    public function ready(): JsonResponse
    {
        $ready = $this->isReady();

        return response()->json(['status' => $ready ? 'ready' : 'unavailable'], $ready ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }

    public function contract(): BinaryFileResponse
    {
        return response()->download(base_path('openapi/v1.yaml'), 'shield-captcha-v1.yaml', [
            'Content-Type' => 'application/yaml',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloads(Request $request, ReleaseCatalog $catalog): View
    {
        $artifacts = $catalog->publicArtifacts();
        $category = $request->query('category');
        if (is_string($category) && in_array($category, ['sdk', 'cms', 'examples', 'self-hosted'], true)) {
            $artifacts = array_filter($artifacts, fn (array $artifact) => $artifact['category'] === $category);
        }

        return $this->page($request, 'downloads', ['artifacts' => $artifacts]);
    }

    public function download(string $artifact, ReleaseCatalog $catalog): BinaryFileResponse
    {
        $entry = $catalog->publicArtifacts()[$artifact] ?? null;
        abort_if($entry === null, 404);
        $path = $catalog->resolve($entry);
        abort_if($path === null || ! hash_equals($entry['sha256'], hash_file('sha256', $path) ?: ''), 404);

        return response()->download($path, $entry['filename'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function page(Request $request, string $page, array $data = []): View
    {
        $locale = $request->session()->get('locale', 'en') === 'fa' ? 'fa' : 'en';
        app()->setLocale($locale);

        return view('site.'.$page, $data);
    }

    private function isReady(): bool
    {
        try {
            return filled(config('app.key'))
                && Schema::hasTable('sites')
                && Schema::hasTable('shield_tokens')
                && Schema::hasTable('billing_subscriptions')
                && Schema::hasTable('shield_sms_deliveries')
                && Schema::hasTable('shield_provider_settings')
                && Schema::hasTable('shield_sms_twilio_sites')
                && Schema::hasTable('billing_plans')
                && Schema::hasTable('shield_team_invitations')
                && Schema::hasTable('shield_protection_policies')
                && Schema::hasTable('shield_protection_events')
                && Schema::hasTable('shield_scheduler_status');
        } catch (Throwable) {
            return false;
        }
    }
}

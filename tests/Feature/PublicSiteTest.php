<?php

namespace Tests\Feature;

use App\Support\ReleaseCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_locale_and_health_are_connected(): void
    {
        $this->get('/')->assertOk()->assertSee('Protect the requests that matter.');
        $this->get('/docs/v1')->assertOk()->assertSee('expected_environment');
        $this->from('/')->post('/language', ['locale' => 'fa'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('dir="rtl"', false)->assertSee('از درخواست‌های مهم محافظت کنید.');
        $this->get('/customer/register')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/health/live')->assertExactJson(['status' => 'ok']);
        $this->get('/health/ready')->assertOk()->assertExactJson(['status' => 'ready']);
    }

    public function test_playground_only_embeds_sandbox_site_keys(): void
    {
        $this->get('/playground?site_key=site_prod_'.str_repeat('0', 32).'&action=contact')
            ->assertOk()->assertDontSee('data-shield-captcha');
        $this->get('/playground?site_key=site_test_'.str_repeat('0', 32).'&action=contact')
            ->assertOk()->assertSee('data-shield-captcha');
    }

    public function test_release_routes_only_deliver_listed_public_artifacts(): void
    {
        $this->get('/downloads')->assertOk();
        $this->get('/downloads/widget-js')->assertDownload('shield-captcha-widget-0.2.0.tgz');
        $this->get('/downloads/missing')->assertNotFound();
        $this->get('/api-reference')->assertDownload('shield-captcha-v1.yaml');
        $this->assertNull(app(ReleaseCatalog::class)->resolve([
            'path' => '../composer.json',
            'filename' => 'composer.json',
            'size_bytes' => filesize(base_path('composer.json')),
        ]));
    }
}

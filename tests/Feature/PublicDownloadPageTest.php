<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppVersion;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicDownloadPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('apk.disk'));
    }

    public function test_the_page_loads_without_authentication(): void
    {
        $app = App::factory()->create(['name' => 'Ziroone CRM', 'slug' => 'ziroone-crm']);
        AppVersion::factory()->for($app)->create([
            'version_code' => 2,
            'version_name' => '1.4.2',
            'release_notes' => 'Faster invoice sync.',
        ]);

        $this->get('/apps/ziroone-crm/download')
            ->assertOk()
            ->assertSee('Ziroone CRM')
            ->assertSee('1.4.2')
            ->assertSee('Faster invoice sync.');
    }

    public function test_it_exposes_no_admin_data(): void
    {
        $app = App::factory()->create(['slug' => 'ziroone-crm']);
        AppVersion::factory()->for($app)->create(['version_code' => 2]);

        Device::factory()->for($app)->create([
            'client_label' => 'Rahim - Accounts',
            'device_model' => 'Pixel 7',
        ]);

        $response = $this->get('/apps/ziroone-crm/download');

        $response->assertOk()
            ->assertDontSee($app->api_key)
            ->assertDontSee('Rahim - Accounts')
            ->assertDontSee('Pixel 7')
            ->assertDontSee($app->package_id);
    }

    public function test_it_downloads_the_latest_active_build(): void
    {
        $app = App::factory()->create(['slug' => 'ziroone-crm']);

        Storage::disk(config('apk.disk'))->put("apks/{$app->id}/2.apk", 'v2-bytes');

        AppVersion::factory()->for($app)->create(['version_code' => 1, 'apk_path' => 'apks/x/1.apk']);
        AppVersion::factory()->for($app)->create([
            'version_code' => 2,
            'apk_path' => "apks/{$app->id}/2.apk",
        ]);

        $response = $this->post('/apps/ziroone-crm/download');

        $response->assertOk();
        $this->assertSame('v2-bytes', $response->streamedContent());
    }

    public function test_a_paused_app_returns_404(): void
    {
        App::factory()->inactive()->create(['slug' => 'paused-app']);

        $this->get('/apps/paused-app/download')->assertNotFound();
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get('/apps/nope/download')->assertNotFound();
    }

    public function test_it_offers_a_qr_code_pointing_back_at_this_page(): void
    {
        $app = App::factory()->create(['slug' => 'ziroone-crm']);
        AppVersion::factory()->for($app)->create(['version_code' => 2]);

        // The code is only useful to someone reading the page on a desktop, so
        // it is hidden below md — but it must still be in the markup, and it
        // must encode this page rather than the APK route (which is a POST).
        $this->get('/apps/ziroone-crm/download')
            ->assertOk()
            ->assertSee('Installing on a phone?')
            ->assertSee('hidden pc:block', false)
            ->assertSee('<svg', false);
    }

    public function test_the_qr_code_is_absent_until_a_build_exists(): void
    {
        App::factory()->create(['slug' => 'ziroone-crm']);

        $this->get('/apps/ziroone-crm/download')
            ->assertOk()
            ->assertDontSee('Installing on a phone?');
    }
}

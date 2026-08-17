<?php

namespace Tests\Feature\Api;

use App\Models\App;
use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadVersionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('apk.disk'));
    }

    private function publish(App $app, int $versionCode, string $contents = 'fake-apk-bytes'): AppVersion
    {
        $path = "apks/{$app->id}/{$versionCode}.apk";

        Storage::disk(config('apk.disk'))->put($path, $contents);

        return AppVersion::factory()->for($app)->create([
            'version_code' => $versionCode,
            'version_name' => "1.0.{$versionCode}",
            'apk_path' => $path,
            'file_size' => strlen($contents),
        ]);
    }

    public function test_it_streams_the_apk(): void
    {
        $app = App::factory()->create();
        $this->publish($app, 4);

        $response = $this->get("/api/v1/apps/{$app->api_key}/download/4");

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.android.package-archive');

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertSame('fake-apk-bytes', $response->streamedContent());
    }

    public function test_a_wrong_api_key_returns_403(): void
    {
        $app = App::factory()->create();
        $this->publish($app, 4);

        $this->getJson('/api/v1/apps/definitely-not-the-key/download/4')
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    public function test_another_apps_api_key_cannot_fetch_this_apk(): void
    {
        $mine = App::factory()->create();
        $other = App::factory()->create();

        $this->publish($mine, 4);

        // Valid key, but version 4 belongs to a different app.
        $this->getJson("/api/v1/apps/{$other->api_key}/download/4")->assertForbidden();
    }

    public function test_an_unknown_version_code_returns_403(): void
    {
        $app = App::factory()->create();
        $this->publish($app, 4);

        $this->getJson("/api/v1/apps/{$app->api_key}/download/99")->assertForbidden();
    }

    public function test_a_pulled_version_cannot_be_downloaded(): void
    {
        $app = App::factory()->create();
        $version = $this->publish($app, 4);
        $version->update(['is_active' => false]);

        $this->getJson("/api/v1/apps/{$app->api_key}/download/4")->assertForbidden();
    }

    public function test_a_missing_file_returns_404(): void
    {
        $app = App::factory()->create();

        AppVersion::factory()->for($app)->create([
            'version_code' => 4,
            'apk_path' => "apks/{$app->id}/4.apk",
        ]);

        $this->getJson("/api/v1/apps/{$app->api_key}/download/4")->assertNotFound();
    }
}

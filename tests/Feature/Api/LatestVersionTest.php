<?php

namespace Tests\Feature\Api;

use App\Models\App;
use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LatestVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_highest_active_version(): void
    {
        $app = App::factory()->create();

        AppVersion::factory()->for($app)->create(['version_code' => 1, 'version_name' => '1.0.0']);
        AppVersion::factory()->for($app)->forced()->create([
            'version_code' => 3,
            'version_name' => '1.2.0',
            'release_notes' => 'Fixed the sync bug.',
            'file_size' => 12345678,
        ]);

        $response = $this->getJson("/api/v1/apps/{$app->api_key}/latest");

        $response->assertOk()->assertJson([
            'version_name' => '1.2.0',
            'version_code' => 3,
            'release_notes' => 'Fixed the sync bug.',
            'is_force_update' => true,
            'file_size' => 12345678,
        ]);

        $this->assertStringContainsString(
            "/api/v1/apps/{$app->api_key}/download/3",
            $response->json('download_url'),
        );
    }

    public function test_pulled_versions_are_ignored(): void
    {
        $app = App::factory()->create();

        AppVersion::factory()->for($app)->create(['version_code' => 1, 'version_name' => '1.0.0']);
        AppVersion::factory()->for($app)->inactive()->create(['version_code' => 9, 'version_name' => '9.9.9']);

        $this->getJson("/api/v1/apps/{$app->api_key}/latest")
            ->assertOk()
            ->assertJson(['version_code' => 1, 'version_name' => '1.0.0']);
    }

    public function test_an_app_with_no_versions_returns_404(): void
    {
        $app = App::factory()->create();

        $this->getJson("/api/v1/apps/{$app->api_key}/latest")
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_a_bad_api_key_is_rejected_with_403(): void
    {
        $app = App::factory()->create();
        AppVersion::factory()->for($app)->create(['version_code' => 1]);

        $this->getJson('/api/v1/apps/wrong-key/latest')->assertForbidden();
    }

    public function test_it_does_not_leak_another_apps_version(): void
    {
        $mine = App::factory()->create();
        $theirs = App::factory()->create();

        AppVersion::factory()->for($theirs)->create(['version_code' => 7, 'version_name' => '7.0.0']);

        $this->getJson("/api/v1/apps/{$mine->api_key}/latest")->assertNotFound();
    }
}

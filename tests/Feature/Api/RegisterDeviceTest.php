<?php

namespace Tests\Feature\Api;

use App\Models\App;
use App\Models\AppVersion;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterDeviceTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'install_uuid' => 'f0e1d2c3-b4a5-6789-0123-456789abcdef',
            'fcm_token' => 'token-abc',
            'device_model' => 'Pixel 7',
            'android_version' => '14',
            'version_code' => 1,
            'version_name' => '1.0.0',
        ], $overrides);
    }

    public function test_it_registers_a_new_device(): void
    {
        $app = App::factory()->create();

        $response = $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload());

        $response->assertCreated()
            ->assertJson([
                'registered' => true,
                'is_new_device' => true,
                'update_available' => false,
            ]);

        $this->assertDatabaseHas('devices', [
            'app_id' => $app->id,
            'install_uuid' => 'f0e1d2c3-b4a5-6789-0123-456789abcdef',
            'device_model' => 'Pixel 7',
            'current_version_code' => 1,
        ]);
    }

    public function test_repeat_check_in_updates_the_same_row_rather_than_duplicating(): void
    {
        $app = App::factory()->create();

        $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload());

        $first = Device::sole();
        $firstSeen = $first->first_seen_at;

        $response = $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload([
            'version_code' => 2,
            'version_name' => '1.0.1',
            'fcm_token' => 'token-rotated',
        ]));

        $response->assertOk()->assertJson(['is_new_device' => false]);

        $this->assertSame(1, Device::count());

        $first->refresh();
        $this->assertSame(2, $first->current_version_code);
        $this->assertSame('token-rotated', $first->fcm_token);
        // first_seen_at is stamped once and never moved.
        $this->assertEquals($firstSeen->timestamp, $first->first_seen_at->timestamp);
    }

    public function test_the_same_install_uuid_can_exist_under_two_different_apps(): void
    {
        $one = App::factory()->create();
        $two = App::factory()->create();

        $this->postJson("/api/v1/apps/{$one->api_key}/register", $this->payload())->assertCreated();
        $this->postJson("/api/v1/apps/{$two->api_key}/register", $this->payload())->assertCreated();

        $this->assertSame(2, Device::count());
    }

    public function test_it_reports_an_available_update(): void
    {
        $app = App::factory()->create();
        AppVersion::factory()->for($app)->create(['version_code' => 5, 'version_name' => '1.5.0']);

        $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload(['version_code' => 3]))
            ->assertCreated()
            ->assertJson([
                'update_available' => true,
                'latest' => ['version_code' => 5, 'version_name' => '1.5.0'],
            ]);
    }

    public function test_a_null_fcm_token_is_accepted(): void
    {
        $app = App::factory()->create();

        $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload(['fcm_token' => null]))
            ->assertCreated();

        $this->assertDatabaseHas('devices', ['app_id' => $app->id, 'fcm_token' => null]);
    }

    public function test_a_bad_api_key_is_rejected_with_403(): void
    {
        App::factory()->create();

        $this->postJson('/api/v1/apps/not-a-real-key/register', $this->payload())
            ->assertForbidden()
            ->assertJsonStructure(['message']);

        $this->assertSame(0, Device::count());
    }

    public function test_an_inactive_app_is_rejected_with_403(): void
    {
        $app = App::factory()->inactive()->create();

        $this->postJson("/api/v1/apps/{$app->api_key}/register", $this->payload())
            ->assertForbidden();
    }

    public function test_missing_fields_return_422(): void
    {
        $app = App::factory()->create();

        $this->postJson("/api/v1/apps/{$app->api_key}/register", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['install_uuid', 'version_code']);
    }
}

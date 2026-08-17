<?php

namespace Tests\Feature;

use App\Models\App;
use App\Models\AppVersion;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $app = App::factory()->create();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('apps.index'))->assertRedirect(route('login'));
        $this->get(route('apps.show', $app))->assertRedirect(route('login'));
    }

    public function test_creating_an_app_generates_an_api_key_and_slug(): void
    {
        $this->actingAs($this->admin)->post(route('apps.store'), [
            'name' => 'Ziroone CRM',
            'slug' => '',
            'package_id' => 'com.ziroone.crm',
            'description' => 'Internal CRM.',
            'is_active' => '1',
        ])->assertRedirect();

        $app = App::sole();

        $this->assertSame('ziroone-crm', $app->slug);
        $this->assertSame(40, strlen($app->api_key));
    }

    public function test_the_api_key_cannot_be_set_by_the_client(): void
    {
        $this->actingAs($this->admin)->post(route('apps.store'), [
            'name' => 'Ziroone CRM',
            'package_id' => 'com.ziroone.crm',
            'api_key' => 'attacker-chosen-key',
        ]);

        $this->assertNotSame('attacker-chosen-key', App::sole()->api_key);
    }

    public function test_a_duplicate_slug_is_rejected(): void
    {
        App::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->admin)->post(route('apps.store'), [
            'name' => 'Another',
            'slug' => 'taken',
            'package_id' => 'com.ziroone.other',
        ])->assertSessionHasErrors('slug');
    }

    public function test_a_malformed_package_id_is_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('apps.store'), [
            'name' => 'Bad',
            'package_id' => 'not a package',
        ])->assertSessionHasErrors('package_id');
    }

    public function test_the_app_screen_renders_with_versions_and_devices(): void
    {
        $app = App::factory()->create(['name' => 'Ziroone CRM']);
        AppVersion::factory()->for($app)->create(['version_code' => 2, 'version_name' => '1.4.2']);
        Device::factory()->for($app)->create([
            'client_label' => 'Rahim - Accounts',
            'current_version_code' => 1,
        ]);

        $this->actingAs($this->admin)->get(route('apps.show', $app))
            ->assertOk()
            ->assertSee('Ziroone CRM')
            ->assertSee('1.4.2')
            ->assertSee('Rahim - Accounts')
            ->assertSee('Outdated')
            // The share block renders a QR code as inline SVG.
            ->assertSee('<svg', false);
    }

    public function test_the_outdated_badge_clears_once_the_device_reports_the_new_version(): void
    {
        $app = App::factory()->create();
        AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $device = Device::factory()->for($app)->create([
            'install_uuid' => 'uuid-1',
            'current_version_code' => 1,
        ]);

        $this->assertTrue(Device::query()->withLatestVersionCode()->find($device->id)->isOutdated());

        $this->postJson("/api/v1/apps/{$app->api_key}/register", [
            'install_uuid' => 'uuid-1',
            'version_code' => 2,
            'version_name' => '1.4.2',
        ])->assertOk();

        $this->assertFalse(Device::query()->withLatestVersionCode()->find($device->id)->isOutdated());
    }

    public function test_a_client_label_can_be_edited_inline(): void
    {
        $app = App::factory()->create();
        $device = Device::factory()->for($app)->create(['client_label' => null]);

        $this->actingAs($this->admin)
            ->patch(route('apps.devices.update', [$app, $device]), ['client_label' => 'Rahim - Accounts'])
            ->assertRedirect();

        $this->assertSame('Rahim - Accounts', $device->fresh()->client_label);
    }

    public function test_a_device_cannot_be_relabelled_through_another_app(): void
    {
        $app = App::factory()->create();
        $other = App::factory()->create();
        $device = Device::factory()->for($app)->create();

        $this->actingAs($this->admin)
            ->patch(route('apps.devices.update', [$other, $device]), ['client_label' => 'Hijacked'])
            ->assertNotFound();

        $this->assertNull($device->fresh()->client_label);
    }

    public function test_the_dashboard_counts_outdated_devices_across_all_apps(): void
    {
        $one = App::factory()->create();
        $two = App::factory()->create();

        AppVersion::factory()->for($one)->create(['version_code' => 3]);
        AppVersion::factory()->for($two)->create(['version_code' => 5]);

        Device::factory()->for($one)->create(['current_version_code' => 1]); // outdated
        Device::factory()->for($one)->create(['current_version_code' => 3]); // current
        Device::factory()->for($two)->create(['current_version_code' => 4]); // outdated

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $this->assertSame(2, $response->viewData('stats')['outdated']);
        $this->assertSame(3, $response->viewData('stats')['devices']);
    }

    public function test_apps_can_be_searched(): void
    {
        App::factory()->create(['name' => 'Ziroone CRM']);
        App::factory()->create(['name' => 'Bridgely Portal']);

        $this->actingAs($this->admin)->get(route('apps.index', ['search' => 'Ziroone']))
            ->assertOk()
            ->assertSee('Ziroone CRM')
            ->assertDontSee('Bridgely Portal');
    }
}

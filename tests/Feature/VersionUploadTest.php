<?php

namespace Tests\Feature;

use App\Jobs\NotifyDevicesOfUpdate;
use App\Models\App;
use App\Models\AppVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VersionUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('apk.disk'));
        $this->admin = User::factory()->create();
    }

    private function apk(string $name = 'build.apk', int $kilobytes = 512): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/vnd.android.package-archive');
    }

    public function test_it_stores_the_apk_privately_and_creates_the_version(): void
    {
        Queue::fake();

        $app = App::factory()->create();

        $response = $this->actingAs($this->admin)->post(route('apps.versions.store', $app), [
            'apk' => $this->apk(),
            'version_name' => '1.0.0',
            'version_code' => 1,
            'release_notes' => 'First build.',
            'is_force_update' => '0',
        ]);

        $response->assertRedirect(route('apps.show', $app));

        $version = AppVersion::sole();

        $this->assertSame(1, $version->version_code);
        $this->assertSame('1.0.0', $version->version_name);
        $this->assertTrue($version->is_active);
        $this->assertSame("apks/{$app->id}/1.apk", $version->apk_path);

        Storage::disk(config('apk.disk'))->assertExists($version->apk_path);

        // Nothing was written anywhere web-reachable.
        $this->assertStringNotContainsString('public', $version->apk_path);
    }

    public function test_it_queues_the_push_job(): void
    {
        Queue::fake();

        $app = App::factory()->create();

        $this->actingAs($this->admin)->post(route('apps.versions.store', $app), [
            'apk' => $this->apk(),
            'version_name' => '1.0.0',
            'version_code' => 1,
        ]);

        Queue::assertPushed(NotifyDevicesOfUpdate::class, function ($job) {
            return $job->version->version_code === 1;
        });
    }

    public function test_version_code_must_be_strictly_greater_than_the_current_max(): void
    {
        Queue::fake();

        $app = App::factory()->create();
        AppVersion::factory()->for($app)->create(['version_code' => 5]);

        foreach ([4, 5] as $rejected) {
            $this->actingAs($this->admin)
                ->post(route('apps.versions.store', $app), [
                    'apk' => $this->apk(),
                    'version_name' => '1.0.1',
                    'version_code' => $rejected,
                ])
                ->assertSessionHasErrors('version_code');
        }

        $this->assertSame(1, AppVersion::count());
        Queue::assertNothingPushed();
    }

    public function test_a_pulled_version_still_blocks_reuse_of_its_version_code(): void
    {
        Queue::fake();

        $app = App::factory()->create();
        AppVersion::factory()->for($app)->inactive()->create(['version_code' => 9]);

        $this->actingAs($this->admin)
            ->post(route('apps.versions.store', $app), [
                'apk' => $this->apk(),
                'version_name' => '1.0.1',
                'version_code' => 9,
            ])
            ->assertSessionHasErrors('version_code');
    }

    public function test_non_apk_uploads_are_rejected(): void
    {
        Queue::fake();

        $app = App::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('apps.versions.store', $app), [
                'apk' => UploadedFile::fake()->create('notes.pdf', 64, 'application/pdf'),
                'version_name' => '1.0.0',
                'version_code' => 1,
            ])
            ->assertSessionHasErrors('apk');

        $this->assertSame(0, AppVersion::count());
    }

    public function test_guests_cannot_upload(): void
    {
        Queue::fake();

        $app = App::factory()->create();

        $this->post(route('apps.versions.store', $app), [
            'apk' => $this->apk(),
            'version_name' => '1.0.0',
            'version_code' => 1,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, AppVersion::count());
    }

    public function test_a_version_can_be_pulled_and_restored(): void
    {
        $app = App::factory()->create();
        $version = AppVersion::factory()->for($app)->create(['version_code' => 1]);

        $this->actingAs($this->admin)
            ->patch(route('apps.versions.update', [$app, $version]), ['is_active' => 0]);

        $this->assertFalse($version->fresh()->is_active);

        $this->actingAs($this->admin)
            ->patch(route('apps.versions.update', [$app, $version]), ['is_active' => 1]);

        $this->assertTrue($version->fresh()->is_active);
    }
}

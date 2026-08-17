<?php

namespace Tests\Feature;

use App\Jobs\NotifyDevicesOfUpdate;
use App\Models\App;
use App\Models\AppVersion;
use App\Models\Device;
use App\Models\PushLog;
use App\Services\PushResult;
use App\Services\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Messaging\CloudMessage;
use Tests\TestCase;

class NotifyDevicesOfUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Records what the job asked to send, without touching FCM.
     */
    private function fakeSender(array $staleTokens = [], bool $failAll = false): PushSender
    {
        return new class($staleTokens, $failAll) extends PushSender
        {
            /** @var list<list<string>> */
            public array $batches = [];

            public function __construct(private array $stale, private bool $failAll) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function send(CloudMessage $message, array $tokens): PushResult
            {
                $this->batches[] = $tokens;

                if ($this->failAll) {
                    return PushResult::allFailed(count($tokens));
                }

                $stale = array_values(array_intersect($this->stale, $tokens));

                return new PushResult(
                    sent: count($tokens) - count($stale),
                    failed: count($stale),
                    staleTokens: $stale,
                );
            }
        };
    }

    public function test_it_writes_a_push_log_with_the_targeted_count(): void
    {
        $app = App::factory()->create();
        Device::factory()->count(3)->for($app)->create(['current_version_code' => 1]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $sender = $this->fakeSender();
        $this->app->instance(PushSender::class, $sender);

        (new NotifyDevicesOfUpdate($version))->handle($sender);

        $log = PushLog::sole();

        $this->assertSame($version->id, $log->app_version_id);
        $this->assertSame(3, $log->devices_targeted);
        $this->assertSame(3, $log->devices_sent);
        $this->assertSame(0, $log->devices_failed);
        $this->assertNotNull($log->sent_at);
    }

    public function test_devices_without_an_fcm_token_are_not_targeted(): void
    {
        $app = App::factory()->create();
        Device::factory()->count(2)->for($app)->create(['current_version_code' => 1]);
        Device::factory()->count(3)->for($app)->withoutPush()->create(['current_version_code' => 1]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $sender = $this->fakeSender();
        (new NotifyDevicesOfUpdate($version))->handle($sender);

        $this->assertSame(2, PushLog::sole()->devices_targeted);
    }

    public function test_devices_already_on_the_new_build_are_skipped(): void
    {
        $app = App::factory()->create();
        Device::factory()->for($app)->create(['current_version_code' => 1]);
        Device::factory()->for($app)->create(['current_version_code' => 2]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $sender = $this->fakeSender();
        (new NotifyDevicesOfUpdate($version))->handle($sender);

        $this->assertSame(1, PushLog::sole()->devices_targeted);
    }

    public function test_other_apps_devices_are_never_targeted(): void
    {
        $app = App::factory()->create();
        $other = App::factory()->create();

        Device::factory()->for($app)->create(['current_version_code' => 1]);
        Device::factory()->count(4)->for($other)->create(['current_version_code' => 1]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $sender = $this->fakeSender();
        (new NotifyDevicesOfUpdate($version))->handle($sender);

        $this->assertSame(1, PushLog::sole()->devices_targeted);
    }

    public function test_stale_tokens_are_cleared_so_the_device_shows_as_push_disabled(): void
    {
        $app = App::factory()->create();

        $dead = Device::factory()->for($app)->create([
            'fcm_token' => 'dead-token',
            'current_version_code' => 1,
        ]);
        $alive = Device::factory()->for($app)->create([
            'fcm_token' => 'live-token',
            'current_version_code' => 1,
        ]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        $sender = $this->fakeSender(staleTokens: ['dead-token']);
        (new NotifyDevicesOfUpdate($version))->handle($sender);

        $this->assertNull($dead->fresh()->fcm_token);
        $this->assertSame('live-token', $alive->fresh()->fcm_token);

        $log = PushLog::sole();
        $this->assertSame(2, $log->devices_targeted);
        $this->assertSame(1, $log->devices_sent);
        $this->assertSame(1, $log->devices_failed);
    }

    public function test_it_still_logs_when_every_send_fails(): void
    {
        $app = App::factory()->create();
        Device::factory()->count(2)->for($app)->create(['current_version_code' => 1]);

        $version = AppVersion::factory()->for($app)->create(['version_code' => 2]);

        (new NotifyDevicesOfUpdate($version))->handle($this->fakeSender(failAll: true));

        $log = PushLog::sole();
        $this->assertSame(2, $log->devices_targeted);
        $this->assertSame(0, $log->devices_sent);
        $this->assertSame(2, $log->devices_failed);
    }

    public function test_an_inactive_version_is_not_pushed(): void
    {
        $app = App::factory()->create();
        Device::factory()->for($app)->create(['current_version_code' => 1]);

        $version = AppVersion::factory()->for($app)->inactive()->create(['version_code' => 2]);

        (new NotifyDevicesOfUpdate($version))->handle($this->fakeSender());

        $this->assertSame(0, PushLog::count());
    }
}

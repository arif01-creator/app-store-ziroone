<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'app_id' => App::factory(),
            'install_uuid' => (string) Str::uuid(),
            'fcm_token' => Str::random(152),
            'device_model' => fake()->randomElement(['Pixel 7', 'Galaxy A54', 'Redmi Note 12', 'Nokia G21']),
            'android_version' => (string) fake()->numberBetween(10, 15),
            'client_label' => null,
            'current_version_code' => 1,
            'current_version_name' => '1.0.0',
            'first_seen_at' => now()->subDays(7),
            'last_seen_at' => now(),
            'is_active' => true,
        ];
    }

    /** A device that never granted notification permission. */
    public function withoutPush(): static
    {
        return $this->state(fn () => ['fcm_token' => null]);
    }
}

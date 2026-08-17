<?php

namespace Database\Factories;

use App\Models\App;
use App\Models\AppVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppVersion>
 */
class AppVersionFactory extends Factory
{
    protected $model = AppVersion::class;

    public function definition(): array
    {
        $code = fake()->unique()->numberBetween(1, 100000);

        return [
            'app_id' => App::factory(),
            'version_name' => "1.0.{$code}",
            'version_code' => $code,
            'apk_path' => "apks/1/{$code}.apk",
            'file_size' => fake()->numberBetween(1_000_000, 50_000_000),
            'release_notes' => fake()->optional()->sentence(),
            'is_force_update' => false,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function forced(): static
    {
        return $this->state(fn () => ['is_force_update' => true]);
    }
}

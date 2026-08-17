<?php

namespace Database\Factories;

use App\Models\App;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<App>
 */
class AppFactory extends Factory
{
    protected $model = App::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            // Deliberately neutral — a shared prefix here would make tests that
            // search by name accidentally match every app's package id too.
            'package_id' => 'com.'.Str::lower(Str::random(6)).'.'.Str::lower(Str::random(8)),
            'icon_path' => null,
            'description' => fake()->optional()->sentence(),
            'api_key' => App::generateApiKey(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

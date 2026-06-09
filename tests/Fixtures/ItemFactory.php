<?php

namespace Jiannius\Atom\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'status' => fake()->randomElement(['draft', 'published']),
            'amount' => fake()->numberBetween(1, 1000),
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Electronics',
                'Furniture',
                'Office Supplies',
                'Computers',
                'Audio/Video',
                'Networking',
                'Peripherals',
                'Mobile Devices',
            ]) . ' ' . $this->faker->unique()->numerify('####'),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}

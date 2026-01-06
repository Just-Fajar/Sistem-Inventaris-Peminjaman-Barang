<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stock = $this->faker->numberBetween(5, 50);
        
        return [
            'code' => 'ITM-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->randomElement([
                'Laptop Dell', 'Laptop HP', 'Laptop Lenovo',
                'Mouse Logitech', 'Keyboard Mechanical',
                'Monitor LG', 'Printer Canon',
                'Projector Epson', 'Webcam Logitech',
            ]) . ' ' . $this->faker->numerify('###'),
            'category_id' => Category::factory(),
            'stock' => $stock,
            'available_stock' => $stock,
            'condition' => $this->faker->randomElement(['good', 'fair', 'poor']),
            'description' => $this->faker->optional()->sentence(),
            'image' => $this->faker->optional()->imageUrl(640, 480, 'technics'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Borrowing>
 */
class BorrowingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $borrowDate = $this->faker->dateTimeBetween('-30 days', 'now');
        $dueDate = (clone $borrowDate)->modify('+7 days');
        
        return [
            'code' => 'BRW-' . date('Y') . '-' . $this->faker->unique()->numerify('####'),
            'user_id' => User::factory(),
            'item_id' => Item::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'return_date' => null,
            'status' => $this->faker->randomElement(['pending', 'approved', 'returned', 'rejected']),
            'notes' => $this->faker->optional()->sentence(),
            'return_condition' => null,
            'approved_by' => null,
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Create Staff User
        User::create([
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => Hash::make('password'),
            'role' => 'staff',
        ]);

        // Create Categories
        $categories = [
            ['name' => 'Elektronik', 'description' => 'Peralatan elektronik kantor'],
            ['name' => 'Furniture', 'description' => 'Meja, kursi, dan perabotan kantor'],
            ['name' => 'Alat Tulis', 'description' => 'ATK dan perlengkapan tulis'],
            ['name' => 'Komputer', 'description' => 'Laptop, PC, dan aksesoris'],
            ['name' => 'Jaringan', 'description' => 'Router, switch, dan peralatan jaringan'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // Create Items
        $items = [
            [
                'code' => 'ITM-20260104-0001',
                'name' => 'Laptop Dell Latitude',
                'description' => 'Laptop untuk pekerjaan kantor',
                'category_id' => 4,
                'stock' => 10,
                'available_stock' => 10,
                'condition' => 'baik',
            ],
            [
                'code' => 'ITM-20260104-0002',
                'name' => 'Mouse Wireless Logitech',
                'description' => 'Mouse wireless untuk komputer',
                'category_id' => 1,
                'stock' => 25,
                'available_stock' => 25,
                'condition' => 'baik',
            ],
            [
                'code' => 'ITM-20260104-0003',
                'name' => 'Kursi Kantor Ergonomis',
                'description' => 'Kursi kantor dengan penyangga punggung',
                'category_id' => 2,
                'stock' => 15,
                'available_stock' => 15,
                'condition' => 'baik',
            ],
            [
                'code' => 'ITM-20260104-0004',
                'name' => 'Router WiFi TP-Link',
                'description' => 'Router untuk jaringan kantor',
                'category_id' => 5,
                'stock' => 8,
                'available_stock' => 8,
                'condition' => 'baik',
            ],
            [
                'code' => 'ITM-20260104-0005',
                'name' => 'Printer HP LaserJet',
                'description' => 'Printer laser untuk dokumen',
                'category_id' => 1,
                'stock' => 5,
                'available_stock' => 5,
                'condition' => 'baik',
            ],
        ];

        foreach ($items as $item) {
            Item::create($item);
        }

        $this->command->info('Database seeded successfully!');
    }
}

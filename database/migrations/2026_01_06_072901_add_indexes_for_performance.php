<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Items table indexes
        // Note: code sudah unique, category_id sudah ada foreign key index
        Schema::table('items', function (Blueprint $table) {
            $table->index('condition'); // Untuk filter by condition
            $table->index('available_stock'); // Untuk filter available items
            $table->index(['category_id', 'available_stock']); // Composite index
        });

        // Borrowings table indexes
        // Note: borrow_code sudah unique, user_id & item_id sudah ada foreign key indexes
        Schema::table('borrowings', function (Blueprint $table) {
            $table->index('status'); // Untuk filter by status
            $table->index('due_date'); // Untuk overdue detection
            $table->index('borrow_date'); // Untuk date range filters
            $table->index(['status', 'due_date']); // Composite index untuk overdue queries
            $table->index(['user_id', 'status']); // Composite index untuk user borrowings
        });

        // Categories table indexes
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name'); // Untuk search by name
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('role'); // Untuk filter by role
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['condition']);
            $table->dropIndex(['available_stock']);
            $table->dropIndex(['category_id', 'available_stock']);
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['due_date']);
            $table->dropIndex(['borrow_date']);
            $table->dropIndex(['status', 'due_date']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};

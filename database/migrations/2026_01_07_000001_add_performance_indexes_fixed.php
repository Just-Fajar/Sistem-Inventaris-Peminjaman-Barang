<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop any existing duplicate indexes first
        $this->dropIndexIfExists('items', 'items_condition_index');
        $this->dropIndexIfExists('items', 'items_available_stock_index');
        $this->dropIndexIfExists('items', 'items_category_id_available_stock_index');
        $this->dropIndexIfExists('borrowings', 'borrowings_status_index');
        $this->dropIndexIfExists('borrowings', 'borrowings_due_date_index');
        $this->dropIndexIfExists('borrowings', 'borrowings_borrow_date_index');
        $this->dropIndexIfExists('borrowings', 'borrowings_status_due_date_index');
        $this->dropIndexIfExists('borrowings', 'borrowings_user_id_status_index');
        $this->dropIndexIfExists('categories', 'categories_name_index');
        $this->dropIndexIfExists('users', 'users_role_index');

        // Items table indexes
        Schema::table('items', function (Blueprint $table) {
            $table->index('condition');
            $table->index('available_stock');
            $table->index(['category_id', 'available_stock']);
        });

        // Borrowings table indexes
        Schema::table('borrowings', function (Blueprint $table) {
            $table->index('status');
            $table->index('due_date');
            $table->index('borrow_date');
            $table->index(['status', 'due_date']);
            $table->index(['user_id', 'status']);
        });

        // Categories table indexes
        Schema::table('categories', function (Blueprint $table) {
            $table->index('name');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
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

    /**
     * Drop index if exists
     */
    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }
    }
};

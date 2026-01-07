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
        // Items table composite indexes
        Schema::table('items', function (Blueprint $table) {
            // Index for searching by category and condition
            $table->index(['category_id', 'condition'], 'items_category_condition_index');
            
            // Index for searching by condition and stock availability
            $table->index(['condition', 'available_stock'], 'items_condition_stock_index');
            
            // Index for date-based queries
            $table->index(['created_at', 'category_id'], 'items_created_category_index');
        });

        // Borrowings table composite indexes
        Schema::table('borrowings', function (Blueprint $table) {
            // Index for user's active borrowings
            $table->index(['user_id', 'status'], 'borrowings_user_status_index');
            
            // Index for item's borrowing history
            $table->index(['item_id', 'status'], 'borrowings_item_status_index');
            
            // Index for date range queries
            $table->index(['borrow_date', 'return_date'], 'borrowings_dates_index');
            
            // Index for overdue borrowings
            $table->index(['status', 'return_date'], 'borrowings_status_return_index');
        });

        // Activity logs table composite index
        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->index(['causer_id', 'log_name'], 'activity_causer_log_index');
                $table->index(['subject_type', 'subject_id'], 'activity_subject_index');
            });
        }

        // Security audit logs composite index
        Schema::table('security_audit_logs', function (Blueprint $table) {
            // Index for user security events
            $table->index(['user_id', 'event_type'], 'security_user_event_index');
            
            // Index for IP-based queries
            $table->index(['ip_address', 'event_type'], 'security_ip_event_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('items_category_condition_index');
            $table->dropIndex('items_condition_stock_index');
            $table->dropIndex('items_created_category_index');
        });

        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropIndex('borrowings_user_status_index');
            $table->dropIndex('borrowings_item_status_index');
            $table->dropIndex('borrowings_dates_index');
            $table->dropIndex('borrowings_status_return_index');
        });

        if (Schema::hasTable('activity_log')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropIndex('activity_causer_log_index');
                $table->dropIndex('activity_subject_index');
            });
        }

        Schema::table('security_audit_logs', function (Blueprint $table) {
            $table->dropIndex('security_user_event_index');
            $table->dropIndex('security_ip_event_index');
        });
    }
};

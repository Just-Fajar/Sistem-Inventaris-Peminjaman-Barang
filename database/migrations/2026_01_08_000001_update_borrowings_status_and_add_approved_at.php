<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            if (!Schema::hasColumn('borrowings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (DB::getDriverName() === 'sqlite') {
                $table->string('status')->default('pending')->change();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE borrowings MODIFY COLUMN status ENUM('pending', 'dipinjam', 'dikembalikan', 'terlambat', 'ditolak', 'rejected', 'approved') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE borrowings MODIFY COLUMN status ENUM('dipinjam', 'dikembalikan', 'terlambat') NOT NULL DEFAULT 'dipinjam'");
        }

        Schema::table('borrowings', function (Blueprint $table) {
            if (Schema::hasColumn('borrowings', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
            if (DB::getDriverName() === 'sqlite') {
                $table->string('status')->default('dipinjam')->change();
            }
        });
    }
};

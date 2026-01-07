<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify role enum to include avp and staff roles
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'buyer', 'avp', 'staff') DEFAULT 'staff'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'buyer') DEFAULT 'buyer'");
    }
};

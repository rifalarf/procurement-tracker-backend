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
        Schema::create('custom_field_configs', function (Blueprint $table) {
            $table->id();
            $table->string('field_name', 50)->unique();
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->integer('display_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed default configs for 5 custom fields
        DB::table('custom_field_configs')->insert([
            ['field_name' => 'custom_field_1', 'label' => null, 'is_active' => false, 'is_searchable' => false, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['field_name' => 'custom_field_2', 'label' => null, 'is_active' => false, 'is_searchable' => false, 'display_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['field_name' => 'custom_field_3', 'label' => null, 'is_active' => false, 'is_searchable' => false, 'display_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['field_name' => 'custom_field_4', 'label' => null, 'is_active' => false, 'is_searchable' => false, 'display_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['field_name' => 'custom_field_5', 'label' => null, 'is_active' => false, 'is_searchable' => false, 'display_order' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_configs');
    }
};

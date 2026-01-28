<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('old_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('new_status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index(['procurement_item_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};

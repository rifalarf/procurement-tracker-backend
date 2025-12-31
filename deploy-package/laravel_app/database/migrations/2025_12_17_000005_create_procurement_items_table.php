<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->string('no_pr', 50)->unique();
            $table->string('mat_code', 50)->nullable();
            $table->string('nama_barang', 500);
            $table->integer('qty')->default(0);
            $table->string('um', 50)->nullable();
            $table->string('pg', 50)->nullable();
            $table->string('user', 255)->nullable()->comment('User/requester name');
            $table->decimal('nilai', 18, 2)->default(0);
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tgl_terima_dokumen')->nullable();
            $table->enum('procx_manual', ['PROCX', 'MANUAL'])->default('PROCX');
            $table->foreignId('buyer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained()->nullOnDelete();
            $table->date('tgl_status')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->string('no_po', 50)->nullable();
            $table->string('nama_vendor', 255)->nullable();
            $table->date('tgl_po')->nullable();
            $table->date('tgl_datang')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for common filters
            $table->index('status_id');
            $table->index('department_id');
            $table->index('buyer_id');
            $table->index('user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};

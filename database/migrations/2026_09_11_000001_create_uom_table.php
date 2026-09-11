<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uom', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 15)->unique();
            $table->string('nama', 100);
            $table->string('kategori', 50)->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active', 'idx_uom_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uom');
    }
};

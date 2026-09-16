<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('varian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_id')->constrained('kategori')->cascadeOnDelete();
            $table->string('nama', 50);
            $table->timestamps();

            $table->unique(['kategori_id', 'nama'], 'uq_varian_kategori_nama');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('varian');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            // lokal / impor
            $table->string('kategori', 10);
            $table->string('kontak', 100)->nullable();
            $table->text('alamat')->nullable();
            // tempo / termin / pelunasan
            $table->string('termin_default', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('catatan')->constrained('users')->nullOnDelete();
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('kartu_stok', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
        });
    }
};

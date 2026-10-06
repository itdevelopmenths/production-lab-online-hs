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
        Schema::dropIfExists('analisa_impor_varian');

        if (Schema::hasTable('analisa_impor')) {
            Schema::table('analisa_impor', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_impor', 'punya_varian')) {
                    $table->dropColumn('punya_varian');
                }
                if (Schema::hasColumn('analisa_impor', 'varian_detail')) {
                    $table->dropColumn('varian_detail');
                }
            });
        }

        if (Schema::hasTable('analisa_impor_meta')) {
            Schema::table('analisa_impor_meta', function (Blueprint $table) {
                if (Schema::hasColumn('analisa_impor_meta', 'punya_varian')) {
                    $table->dropColumn('punya_varian');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisa_impor', function (Blueprint $table) {
            $table->boolean('punya_varian')->default(false);
            $table->jsonb('varian_detail')->nullable();
        });
        Schema::table('analisa_impor_meta', function (Blueprint $table) {
            $table->boolean('punya_varian')->default(false);
        });
    }
};

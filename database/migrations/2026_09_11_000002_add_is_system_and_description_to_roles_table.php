<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
            $table->boolean('is_system')->default(false)->after('guard_name');
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $rolesTable = $tableNames['roles'] ?? 'roles';

        Schema::table($rolesTable, function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });
    }
};

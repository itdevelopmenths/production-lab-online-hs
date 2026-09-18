<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_audits', function (Blueprint $table) {
            $table->id();
            $table->string('auditable_type', 100)->index();
            $table->string('auditable_id', 64)->index(); // Mendukung UUID dan string ID
            $table->string('module', 30)->index();        // purchasing, produksi, request, transfer
            $table->string('nomor_referensi', 100)->index(); // no_po, no_batch, no_transaksi
            $table->string('event', 50)->index();         // created, updated, submitted, approved, received, completed, paid, cancelled, deleted
            $table->string('action_title', 255);          // Deskripsi ringkas aksi
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 150)->nullable();
            $table->string('user_role', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_audits');
    }
};

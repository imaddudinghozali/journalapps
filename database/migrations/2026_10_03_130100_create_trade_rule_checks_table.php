<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_rule_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trade_id')->constrained()->cascadeOnDelete();

            // Nullable dan kolom tunggal, bukan FK komposit seperti aturan
            // umum di CLAUDE.md. Pengecualian yang disengaja:
            // - komposit dengan cascade akan menghapus riwayat checklist saat
            //   rule dihapus, memusnahkan justru data yang mau dipelajari;
            // - komposit dengan restrict akan memblokir penghapusan akun.
            // Snapshot di bawah sudah membawa seluruh data yang dibutuhkan,
            // jadi integritas laporan tidak bergantung pada FK ini.
            $table->foreignId('setup_rule_id')->nullable()->constrained()->nullOnDelete();

            $table->boolean('is_met');

            // Snapshot kondisi rule SAAT trade dicatat. Inilah yang membuat
            // mengedit bobot rule hari ini tidak mengubah skor trade bulan
            // lalu — dan alasan tabel versi terpisah tidak diperlukan.
            $table->string('rule_label', 120);
            $table->unsignedTinyInteger('rule_weight');
            $table->boolean('rule_required');

            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trade_id', 'position']);

            $table->foreign(['trade_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('trades')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_rule_checks');
    }
};

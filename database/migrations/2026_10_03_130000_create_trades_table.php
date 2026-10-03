<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete, BUKAN cascade: menghapus setup tidak boleh
            // memusnahkan riwayat trade. Itu persis hal yang dicegah oleh
            // keputusan arsip-bukan-hapus di milestone 2.
            $table->foreignId('trading_setup_id')->constrained()->restrictOnDelete();

            $table->string('symbol', 20);
            $table->string('direction', 5);

            // Keduanya disimpan: nominal saja membuat expectancy lintas
            // instrumen tidak sebanding, R saja menghilangkan konteks uang.
            // R-multiple = pnl_amount / risk_amount, dihitung saat dibutuhkan.
            $table->decimal('risk_amount', 18, 2);
            $table->decimal('pnl_amount', 18, 2)->nullable();

            // null = belum bisa dinilai (setup tanpa rule). Bukan 0, bukan 100.
            $table->unsignedTinyInteger('compliance_score')->nullable();

            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'opened_at']);

            // Dirujuk FK komposit dari trade_rule_checks.
            $table->unique(['id', 'user_id']);

            $table->foreign(['trading_setup_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('trading_setups')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};

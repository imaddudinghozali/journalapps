<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            // Nullable: baris yang sudah ada dicatat sebelum perubahan ini,
            // dan P&L mereka sudah tersimpan. Form mewajibkan pengisiannya
            // untuk trade baru.
            $table->foreignId('instrument_id')->nullable()->after('trading_setup_id')
                ->constrained()->nullOnDelete();

            $table->decimal('lot_size', 18, 4)->nullable()->after('direction');
            $table->decimal('entry_price', 18, 8)->nullable()->after('lot_size');
            $table->decimal('stop_price', 18, 8)->nullable()->after('entry_price');
            $table->decimal('exit_price', 18, 8)->nullable()->after('stop_price');

            // Snapshot pengali saat trade dicatat, supaya terlihat dari mana
            // angka P&L-nya berasal kalau pengali instrumennya kelak dikoreksi.
            $table->decimal('contract_size', 18, 8)->nullable()->after('exit_price');

            // risk_amount kini DITURUNKAN dari jarak entry ke stop, bukan
            // diketik. Dijadikan nullable karena stop yang berimpit dengan
            // entry membuat risiko tidak terdefinisi.
            $table->decimal('risk_amount', 18, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instrument_id');
            $table->dropColumn(['lot_size', 'entry_price', 'stop_price', 'exit_price', 'contract_size']);
        });
    }
};

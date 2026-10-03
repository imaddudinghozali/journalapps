<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 20);

            // Pengali yang mengubah pergerakan harga menjadi uang.
            // XAUUSD 100 (oz per lot), EURUSD 100000 (unit per lot),
            // BTCUSD 1. Tanpa ini P&L seluruh pair forex akan salah besar.
            $table->decimal('contract_size', 18, 8)->default(1);

            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
            $table->unique(['id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};

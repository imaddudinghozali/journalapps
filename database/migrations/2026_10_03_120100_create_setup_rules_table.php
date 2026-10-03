<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_rules', function (Blueprint $table) {
            $table->id();

            // user_id disimpan di sini juga, bukan hanya diturunkan dari setup.
            // Global scope bekerja per-model: tanpa kolom ini, SetupRule::find()
            // tidak terscope dan menjadi lubang IDOR.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->foreignId('trading_setup_id')->constrained()->cascadeOnDelete();
            $table->string('label', 120);

            // Tidak semua aturan sama pentingnya. Skala 1-5 (SetupRule::MIN_WEIGHT
            // sampai MAX_WEIGHT) ditegakkan di lapisan validasi; tipe kolom hanya
            // mencegah nilai negatif dan nilai besar, bukan menegakkan skalanya.
            $table->unsignedTinyInteger('weight')->default(1);

            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['trading_setup_id', 'position']);

            // Integritas komposit: database menolak rule yang pemiliknya berbeda
            // dari pemilik setup-nya. Tanpa ini, setiap kode masa depan yang
            // menulis trading_setup_id dari input mewarisi celah itu.
            $table->foreign(['trading_setup_id', 'user_id'])
                ->references(['id', 'user_id'])
                ->on('trading_setups')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_rules');
    }
};

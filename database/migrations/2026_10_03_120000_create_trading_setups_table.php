<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();

            // Arsip, bukan hapus: setup yang tidak dipakai lagi hilang dari form
            // pencatatan trade, tapi trade historis yang memakainya tetap punya
            // konteks. Berbeda maksud dari soft delete.
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            // Unique WAJIB memuat user_id. Tanpa itu, nama setup pengguna lain
            // bocor lewat pelanggaran unique, dan upsert bisa menimpa lintas akun.
            $table->unique(['user_id', 'name']);

            // Dirujuk FK komposit dari setup_rules, supaya database sendiri
            // menolak rule yang pemiliknya berbeda dari pemilik setup-nya.
            $table->unique(['id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_setups');
    }
};

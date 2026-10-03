<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ambang peringatan kepatuhan, ditetapkan pengguna sendiri.
            // PRD mensyaratkan ambangnya milik pengguna, bukan angka tetap
            // yang dipaksakan aplikasi.
            $table->unsignedTinyInteger('compliance_threshold')->default(80)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('compliance_threshold');
        });
    }
};

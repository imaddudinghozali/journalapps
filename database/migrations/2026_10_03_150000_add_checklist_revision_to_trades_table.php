<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            // Skor pertama, disimpan sekali saat trade dicatat dan tidak
            // pernah ditimpa. Checklist boleh direvisi, tapi jawaban pertama
            // tetap ada supaya perbaikan tidak bisa menghapus jejaknya.
            $table->unsignedTinyInteger('original_compliance_score')->nullable()->after('compliance_score');

            $table->timestamp('checklist_revised_at')->nullable()->after('original_compliance_score');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn(['original_compliance_score', 'checklist_revised_at']);
        });
    }
};

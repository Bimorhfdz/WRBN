<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->enum('workflow_stage', [
                'client_intake',
                'client_liaison',
                'koordinator',
                'pendukung',
                'analisis_utama',
                'qc',
                'finalisasi_dokumen',
                'done',
            ])->default('client_intake')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('workflow_stage');
        });
    }
};

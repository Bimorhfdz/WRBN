<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * New uploads keep their bytes in consultation_files.contents and no longer
     * write a row to disk, so storage_path only describes legacy files.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE consultation_files MODIFY storage_path VARCHAR(255) NULL');

            return;
        }

        Schema::table('consultation_files', function (Blueprint $table) {
            $table->string('storage_path')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE consultation_files SET storage_path = '' WHERE storage_path IS NULL");
            DB::statement('ALTER TABLE consultation_files MODIFY storage_path VARCHAR(255) NOT NULL');

            return;
        }

        Schema::table('consultation_files', function (Blueprint $table) {
            $table->string('storage_path')->nullable(false)->change();
        });
    }
};

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
     * Client files used to live on the local disk, which does not exist on a
     * serverless host. They now live in the database instead.
     */
    public function up(): void
    {
        Schema::table('consultation_files', function (Blueprint $table) {
            $table->binary('contents')->nullable()->after('storage_path');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE consultation_files MODIFY contents MEDIUMBLOB NULL');
        }

        $this->inlineLegacyFiles();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_files', function (Blueprint $table) {
            $table->dropColumn('contents');
        });
    }

    /**
     * Read every file that still sits on disk into the new column so a host
     * without a writable disk does not lose historical uploads.
     */
    protected function inlineLegacyFiles(): void
    {
        $rows = DB::select('SELECT id, storage_path FROM consultation_files WHERE contents IS NULL');

        foreach ($rows as $row) {
            $path = $row->storage_path;

            if ($path === null || $path === '') {
                continue;
            }

            if (preg_match('#^([A-Za-z]:[\\\\/]|/)#', $path) !== 1) {
                $path = storage_path('app/'.ltrim($path, '/'));
            }

            if (! is_file($path)) {
                continue;
            }

            DB::update('UPDATE consultation_files SET contents = ? WHERE id = ?', [
                file_get_contents($path) ?: null,
                $row->id,
            ]);
        }
    }
};

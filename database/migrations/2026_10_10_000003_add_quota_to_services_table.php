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
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedTinyInteger('data_quota')->default(1)->after('price_label');
            $table->unsignedTinyInteger('revision_quota')->default(1)->after('data_quota');
            $table->string('deliverable_label', 120)->nullable()->after('revision_quota');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['data_quota', 'revision_quota', 'deliverable_label']);
        });
    }
};

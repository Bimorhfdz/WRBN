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
        Schema::create('member_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 160)->unique();
            $table->string('plan_label', 80)->nullable();
            $table->string('note', 300)->nullable();
            $table->timestamp('expires_at', 3)->nullable();
            $table->timestamp('created_at', 3)->nullable();
            $table->timestamp('updated_at', 3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_emails');
    }
};

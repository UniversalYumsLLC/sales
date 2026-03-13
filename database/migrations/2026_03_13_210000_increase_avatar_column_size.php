<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Google OAuth profile picture URLs can exceed 1000 characters,
     * so we need to increase the avatar column size from VARCHAR(255).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('avatar')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->nullable()->change();
        });
    }
};

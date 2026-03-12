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
        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'converted', 'archived'])->default('active');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('status');
            $table->index('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects');
    }
};

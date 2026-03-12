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
        Schema::create('prospect_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('product_id'); // Fulfil product ID
            $table->timestamps();

            $table->index('prospect_id');
            $table->unique(['prospect_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospect_products');
    }
};

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
        Schema::create('customer_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_party_id')->index();
            $table->string('yums_sku')->index();
            $table->string('customer_sku');
            $table->timestamps();

            // Each Yums SKU can only be mapped once per customer
            $table->unique(['fulfil_party_id', 'yums_sku']);

            $table->foreign('fulfil_party_id')
                ->references('fulfil_party_id')
                ->on('local_customer_metadata')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_skus');
    }
};

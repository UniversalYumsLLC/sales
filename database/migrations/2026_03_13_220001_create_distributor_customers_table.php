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
        Schema::create('distributor_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_party_id')->index();
            $table->string('name');
            $table->json('company_urls')->nullable();
            $table->timestamps();

            $table->foreign('fulfil_party_id')
                ->references('fulfil_party_id')
                ->on('fulfil_customer_metadata')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_customers');
    }
};

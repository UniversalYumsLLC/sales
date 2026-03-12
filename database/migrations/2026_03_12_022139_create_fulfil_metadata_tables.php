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
        // Local metadata for Fulfil customers (extends Fulfil data with app-specific fields)
        Schema::create('fulfil_customer_metadata', function (Blueprint $table) {
            $table->unsignedBigInteger('fulfil_party_id')->primary();
            $table->json('company_urls')->nullable()->comment('Email domains for Gmail matching');
            $table->timestamps();
        });

        // Local metadata for Fulfil customer contacts (email tracking)
        Schema::create('fulfil_contact_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_party_id')->index();
            $table->string('email')->index()->comment('Contact email used as lookup key');
            $table->timestamp('last_emailed_at')->nullable()->comment('Last email sent to this contact');
            $table->timestamp('last_received_at')->nullable()->comment('Last email received from this contact');
            $table->timestamps();

            // Unique constraint: one metadata record per email per customer
            $table->unique(['fulfil_party_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfil_contact_metadata');
        Schema::dropIfExists('fulfil_customer_metadata');
    }
};

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
        Schema::create('fulfil_broker_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_party_id');
            $table->string('name');
            $table->string('email');
            $table->datetime('last_emailed_at')->nullable();
            $table->datetime('last_received_at')->nullable();
            $table->timestamps();

            $table->foreign('fulfil_party_id')
                ->references('fulfil_party_id')
                ->on('fulfil_customer_metadata')
                ->onDelete('cascade');

            $table->unique(['fulfil_party_id', 'email']);
            $table->index('fulfil_party_id');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fulfil_broker_contacts');
    }
};

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
        Schema::create('email_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_party_id')->index();
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('email_type')->index();
            $table->timestamp('sent_at');
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            // Composite index for checking if specific email type was sent for an invoice
            $table->index(['invoice_id', 'email_type']);

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
        Schema::dropIfExists('email_records');
    }
};

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
        // Drop if partially created from a prior failed migration (index name was too long for MySQL)
        Schema::dropIfExists('distributor_customer_contacts');

        Schema::create('distributor_customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_customer_id')->constrained()->onDelete('cascade');
            $table->string('name')->default('');
            $table->string('email');
            $table->string('type')->default('uncategorized'); // buyer, accounts_payable, other, uncategorized
            $table->timestamp('last_emailed_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->timestamps();

            $table->unique(['distributor_customer_id', 'email'], 'dist_cust_contacts_cust_id_email_unique');
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distributor_customer_contacts');
    }
};

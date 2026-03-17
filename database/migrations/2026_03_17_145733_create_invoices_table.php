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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fulfil_id')->unique();
            $table->string('number')->unique();
            $table->unsignedBigInteger('fulfil_party_id')->index();
            $table->date('due_date')->nullable();
            $table->date('created_date')->nullable()->comment('Invoice creation date from Fulfil');
            $table->datetime('last_modified_date')->nullable()->comment('write_date from Fulfil');
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->string('state')->nullable()->comment('draft, validated, posted, paid, cancel');
            $table->timestamps();

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
        Schema::dropIfExists('invoices');
    }
};

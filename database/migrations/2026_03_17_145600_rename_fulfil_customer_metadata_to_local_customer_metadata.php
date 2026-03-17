<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('fulfil_customer_metadata', 'local_customer_metadata');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('local_customer_metadata', 'fulfil_customer_metadata');
    }
};

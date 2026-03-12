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
        // Store Gmail OAuth tokens for each salesperson
        Schema::create('user_gmail_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gmail_email'); // The Gmail address connected
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('token_expires_at');
            $table->string('history_id')->nullable(); // Gmail history ID for incremental sync
            $table->timestamps();

            $table->unique('user_id');
        });

        // Track sync history to ensure no gaps in email fetching
        Schema::create('gmail_sync_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('sync_started_at');
            $table->timestamp('sync_completed_at')->nullable();
            $table->timestamp('emails_from'); // Start of time range checked
            $table->timestamp('emails_to'); // End of time range checked
            $table->integer('emails_fetched')->default(0);
            $table->integer('emails_matched')->default(0); // Matched prospect domains
            $table->string('status')->default('running'); // running, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'sync_started_at']);
        });

        // Store ingested emails for future reference
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('gmail_message_id')->unique(); // Gmail's unique message ID
            $table->string('gmail_thread_id'); // Gmail thread ID for conversation grouping
            $table->foreignId('prospect_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('contact_id')->nullable()->constrained('prospect_contacts')->onDelete('set null');
            $table->enum('direction', ['inbound', 'outbound']); // Whether we received or sent
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->text('to_emails'); // JSON array of recipients
            $table->text('cc_emails')->nullable(); // JSON array of CC recipients
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable(); // Plain text body
            $table->longText('body_html')->nullable(); // HTML body
            $table->timestamp('email_date'); // When the email was sent/received
            $table->boolean('has_attachments')->default(false);
            $table->text('attachment_info')->nullable(); // JSON array of attachment metadata
            $table->timestamps();

            $table->index(['user_id', 'email_date']);
            $table->index(['prospect_id', 'email_date']);
            $table->index(['contact_id', 'email_date']);
            $table->index('from_email');
        });

        // Add company_urls to prospects (JSON array of domain URLs)
        Schema::table('prospects', function (Blueprint $table) {
            $table->text('company_urls')->nullable()->after('vendor_guide'); // JSON array
        });

        // Add email tracking fields to prospect_contacts
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->timestamp('last_emailed_at')->nullable()->after('value'); // Last email sent to contact
            $table->timestamp('last_received_at')->nullable()->after('last_emailed_at'); // Last email received from contact
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_contacts', function (Blueprint $table) {
            $table->dropColumn(['last_emailed_at', 'last_received_at']);
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn('company_urls');
        });

        Schema::dropIfExists('emails');
        Schema::dropIfExists('gmail_sync_history');
        Schema::dropIfExists('user_gmail_tokens');
    }
};

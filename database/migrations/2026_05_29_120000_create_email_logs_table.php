<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Generic log of EVERY outgoing email (all Mailables, Mail::raw, etc.),
     * captured centrally via the MessageSent event and updated by the Brevo
     * delivery webhook. One row per recipient so per-recipient events match.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->nullable();
            $table->string('mailable')->nullable();   // originating Mailable class, if known
            $table->string('to_email')->nullable();
            $table->string('to_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->enum('status', [
                'sent', 'delivered', 'opened', 'clicked',
                'bounced', 'spam', 'blocked', 'deferred', 'failed',
            ])->default('sent');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->timestamps();

            $table->index('message_id');
            $table->index('to_email');
            $table->index('status');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

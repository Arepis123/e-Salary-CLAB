<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Brevo delivery-tracking fields so transactional webhook events
     * (delivered / opened / bounced …) can be matched back to a sent email
     * via its Brevo message-id and reflected on the notification logs page.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('message_id')->nullable()->after('status');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('bounced_at')->nullable()->after('opened_at');
            $table->string('bounce_reason')->nullable()->after('bounced_at');

            $table->index('message_id');
        });

        // Widen the status enum to cover Brevo delivery lifecycle events
        DB::statement("ALTER TABLE notification_logs MODIFY COLUMN status ENUM(
            'pending', 'sent', 'delivered', 'opened', 'clicked',
            'bounced', 'spam', 'blocked', 'deferred', 'failed'
        ) DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropColumn(['message_id', 'delivered_at', 'opened_at', 'bounced_at', 'bounce_reason']);
        });

        DB::statement("ALTER TABLE notification_logs MODIFY COLUMN status ENUM(
            'pending', 'sent', 'failed'
        ) DEFAULT 'pending'");
    }
};

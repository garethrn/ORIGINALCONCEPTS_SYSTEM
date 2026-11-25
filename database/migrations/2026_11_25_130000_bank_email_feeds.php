<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('account_last4', 4)->nullable()->index();
        });

        Schema::create('bank_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Bank alerts');
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 8)->default('ssl');
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');
            $table->string('forwarding_address')->nullable();
            $table->text('allowed_senders')->nullable();
            $table->boolean('auto_allocate')->default(true);
            $table->unsignedTinyInteger('confidence_threshold')->default(90);
            $table->boolean('active')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bank_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_mailbox_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->nullable();
            $table->string('from_address')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->longText('raw_body');
            $table->string('body_hash', 64)->unique();
            $table->string('status')->default('processed');
            $table->timestamps();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('bank_email_id')->nullable()->constrained()->nullOnDelete();
            $table->string('review_status')->nullable()->index();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('suggestion')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parser')->nullable();
        });

        Schema::create('bank_feed_notices', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_feed_notices');
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_email_id');
            $table->dropConstrainedForeignId('payment_id');
            $table->dropColumn(['review_status', 'confidence', 'suggestion', 'parser']);
        });
        Schema::dropIfExists('bank_emails');
        Schema::dropIfExists('bank_mailboxes');
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('account_last4');
        });
    }
};

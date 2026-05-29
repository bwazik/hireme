<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outreach_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('company_id')->constrained('companies');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->enum('channel', ['email','whatsapp']);
            $table->enum('status', ['pending','sent','failed','replied'])->default('pending');
            $table->unsignedBigInteger('email_account_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('reply_content')->nullable();
            $table->boolean('follow_up_sent')->default(false);
            $table->timestamp('follow_up_sent_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('channel');
            $table->index('sent_at');
            $table->index(['contact_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_logs');
    }
};

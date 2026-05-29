<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->foreignId('contact_id')->constrained('contacts');
            $table->foreignId('company_id')->constrained('companies');
            $table->string('email_subject', 255)->nullable();
            $table->longText('email_body')->nullable();
            $table->text('whatsapp_message')->nullable();
            $table->string('ai_model', 100)->default('gemini-flash-latest');
            $table->integer('ai_prompt_tokens')->nullable();
            $table->integer('ai_output_tokens')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->boolean('approved')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->index(['contact_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
    }
};

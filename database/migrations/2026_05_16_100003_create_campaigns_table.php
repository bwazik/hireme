<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('channels');                          // ["email","whatsapp"]
            $table->json('target_countries')->nullable();      // ["EG","DE"] — null=all
            $table->json('target_industries')->nullable();     // ["software_house","saas"] — null=all
            $table->json('target_cities')->nullable();         // ["Cairo"] — null=all
            $table->integer('daily_email_limit')->default(50);
            $table->integer('daily_whatsapp_limit')->default(30);
            $table->boolean('follow_up_enabled')->default(false);
            $table->integer('follow_up_days')->default(7);
            $table->enum('status', ['draft','active','paused','completed'])->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};

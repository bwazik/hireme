<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_instances', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);       // e.g. 'BanhaFade'
            $table->string('api_url', 255);    // e.g. 'https://whatsapp.shattor.com'
            $table->text('api_key');    // encrypted cast
            $table->string('phone_number', 30)->nullable();
            $table->integer('daily_limit')->default(50);
            $table->integer('sent_today')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_instances');
    }
};

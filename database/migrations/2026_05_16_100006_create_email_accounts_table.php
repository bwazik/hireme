<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->text('password'); // Laravel encrypted cast
            $table->string('sender_name', 100)->default('Abdullah Mohamed');
            $table->string('smtp_host', 100)->default('smtp.gmail.com');
            $table->integer('smtp_port')->default(587);
            $table->string('smtp_encryption', 10)->default('tls');
            $table->integer('daily_limit')->default(80);
            $table->integer('sent_today')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};

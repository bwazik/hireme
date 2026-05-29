<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('role', 100)->nullable(); // 'HR','CTO','Founder','Recruiter'
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone_raw', 50)->nullable();
            $table->string('phone', 30)->nullable()->unique(); // E.164: +201098617164
            $table->boolean('is_whatsapp')->default(false);
            $table->string('source', 50);
            $table->text('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scraping_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scraping_job_id')->constrained('scraping_jobs')->cascadeOnDelete();
            $table->json('raw_data');
            $table->string('company_name', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('location', 255)->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->index('processed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_results');
    }
};

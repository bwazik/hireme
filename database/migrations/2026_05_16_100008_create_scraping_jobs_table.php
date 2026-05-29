<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scraping_jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('source', ['serper_maps','serper_search','indeed','wellfound','linkedin','manual']);
            $table->string('query', 255);
            $table->string('location', 100)->nullable();
            $table->string('country_code', 5)->nullable();
            $table->enum('status', ['pending','running','completed','failed'])->default('pending');
            $table->integer('results_found')->default(0);
            $table->integer('new_leads')->default(0);
            $table->integer('duplicates')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraping_jobs');
    }
};

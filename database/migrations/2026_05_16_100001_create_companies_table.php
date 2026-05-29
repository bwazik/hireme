<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('industry')->nullable();
            $table->enum('size', ['unknown','small','medium','large'])->default('unknown');
            $table->string('country', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('website_domain', 150)->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->string('source', 50); // 'google_maps','linkedin','indeed','wellfound','manual','facebook'
            $table->text('source_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('country');
            // $table->index('industry'); // JSON column doesn't support simple indexing
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

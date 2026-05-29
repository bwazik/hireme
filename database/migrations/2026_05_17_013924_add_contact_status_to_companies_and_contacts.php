<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->enum('contact_status', [
                'has_contacts',      // at least one email or phone found
                'pending_enrichment',// just discovered, enrichment job queued
                'manual_required',   // all 3 strategies failed — needs human
                'no_website',        // no website URL at all, can't auto-enrich
            ])->default('pending_enrichment')->after('is_active');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->boolean('is_guessed')->default(false)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('contact_status');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('is_guessed');
        });
    }
};

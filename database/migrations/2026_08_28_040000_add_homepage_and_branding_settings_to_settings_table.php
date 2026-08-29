<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->boolean('catalogue_categories_separate')->default(true);
            $table->boolean('catalogue_use_carousel')->default(true);
            $table->unsignedTinyInteger('catalogue_products_per_category')->default(4);
            $table->string('site_logo_path')->nullable();
            $table->string('site_favicon_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table): void {
            $table->dropColumn([
                'catalogue_categories_separate',
                'catalogue_use_carousel',
                'catalogue_products_per_category',
                'site_logo_path',
                'site_favicon_path',
            ]);
        });
    }
};

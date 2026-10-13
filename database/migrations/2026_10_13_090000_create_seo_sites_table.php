<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            // Clé X-TSS-Key générée dans l'extension « Takada SEO Suivi » (chiffrée par le cast du modèle).
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        $role = DB::table('roles')->where('id', 'admin')->first();

        if ($role) {
            $current = json_decode($role->permissions ?: '[]', true);
            $current = is_array($current) ? $current : [];
            $added = [
                'seo.dashboard',
                'seo_site.viewAny', 'seo_site.view', 'seo_site.create', 'seo_site.update', 'seo_site.delete',
            ];

            DB::table('roles')
                ->where('id', 'admin')
                ->update([
                    'permissions' => json_encode(array_values(array_unique([...$current, ...$added]))),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_sites');
    }
};

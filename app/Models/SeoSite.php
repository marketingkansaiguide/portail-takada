<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Site WordPress équipé de l'extension « Takada SEO Suivi », lu en lecture seule
 * par le tableau de bord SEO via /wp-json/tss/v1/* et l'en-tête X-TSS-Key.
 */
class SeoSite extends Model
{
    protected $fillable = [
        'name',
        'url',
        'api_key',
        'is_active',
        'sort_order',
        'last_success_at',
        'last_error_at',
        'last_error',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'is_active' => 'boolean',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    public function baseUrl(): string
    {
        return rtrim((string) $this->url, '/');
    }

    public function apiUrl(string $path): string
    {
        return $this->baseUrl().'/wp-json/tss/v1/'.ltrim($path, '/');
    }

    public function wpAdminUrl(string $tab = 'vue'): string
    {
        return $this->baseUrl().'/wp-admin/admin.php?page=tss&tab='.rawurlencode($tab);
    }

    public function host(): string
    {
        return (string) (parse_url($this->baseUrl(), PHP_URL_HOST) ?: $this->baseUrl());
    }
}

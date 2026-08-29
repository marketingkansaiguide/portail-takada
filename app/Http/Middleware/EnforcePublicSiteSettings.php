<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforcePublicSiteSettings
{
    /**
     * Keep the administration available while the agency portal and public pages
     * are deliberately unavailable.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request) || ! Schema::hasTable('settings')) {
            return $next($request);
        }

        try {
            $settings = Setting::query()->first([
                'maintenance_mode',
                'prevent_search_indexing',
            ]);
        } catch (Throwable) {
            return $next($request);
        }

        if ($settings?->maintenance_mode) {
            return response()
                ->view('maintenance', [], 503)
                ->header('Retry-After', '3600')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $response = $next($request);

        if ($settings?->prevent_search_indexing) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    private function shouldBypass(Request $request): bool
    {
        $path = trim($request->path(), '/');

        if ($path === 'admin' || str_starts_with($path, 'admin/')) {
            return true;
        }

        if ($path === 'up' || $path === 'robots.txt') {
            return true;
        }

        // Livewire's production endpoint uses a hashed prefix (for example,
        // /livewire-a67ad268/update). Its static assets are safe to serve, and
        // its state-changing requests are allowed only when they belong to the
        // admin panel so a maintenance page cannot be bypassed from a stale
        // public or agency tab.
        if (str_starts_with($path, 'livewire')) {
            return $request->method() === 'GET'
                || $this->isAdminLivewireRequest($request);
        }

        return false;
    }

    private function isAdminLivewireRequest(Request $request): bool
    {
        if (str_contains((string) $request->headers->get('referer'), '/admin')) {
            return true;
        }

        $components = $request->input('components');

        if (! is_array($components)) {
            $payload = json_decode($request->getContent(), true);
            $components = is_array($payload) ? ($payload['components'] ?? []) : [];
        }

        foreach ($components as $component) {
            $snapshot = $component['snapshot'] ?? null;
            $snapshot = is_string($snapshot) ? json_decode($snapshot, true) : $snapshot;
            $componentPath = is_array($snapshot) ? data_get($snapshot, 'memo.path') : null;

            if (is_string($componentPath)) {
                $componentPath = trim($componentPath, '/');

                if ($componentPath === 'admin' || str_starts_with($componentPath, 'admin/')) {
                    return true;
                }
            }
        }

        return false;
    }
}

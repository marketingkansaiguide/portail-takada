<?php

namespace App\Services;

use App\Models\SeoSite;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client lecture seule de l'API REST de l'extension WordPress « Takada SEO Suivi ».
 *
 * Routes utilisées : GET /wp-json/tss/v1/site (état des connecteurs et couverture)
 * et GET /wp-json/tss/v1/summary (KPI, séries, mouvements, alertes, modules),
 * authentifiées par l'en-tête X-TSS-Key propre à chaque site.
 */
class SeoSuiviApi
{
    public const PRESETS = [
        'last_7' => '7 derniers jours',
        'last_28' => '28 derniers jours',
        'last_90' => '90 derniers jours',
        'last_week' => 'Dernière semaine complète',
        'last_month' => 'Dernier mois complet',
    ];

    public const COMPARES = [
        'previous' => 'Période précédente',
        'yoy' => 'Année précédente',
    ];

    private const CACHE_MINUTES = 30;

    private const TIMEOUT = 60;

    /**
     * Données du tableau de bord de chaque site : /site et /summary, lus en parallèle,
     * mis en cache 30 minutes. Un site en erreur n'empêche pas l'affichage des autres.
     *
     * @param  Collection<int, SeoSite>  $sites
     * @return array<int, array{site: SeoSite, info: ?array, summary: ?array, error: ?string, fetched_at: ?string}>
     */
    public function dashboard(Collection $sites, string $preset = 'last_28', string $compare = 'previous', bool $fresh = false): array
    {
        $preset = array_key_exists($preset, self::PRESETS) ? $preset : 'last_28';
        $compare = array_key_exists($compare, self::COMPARES) ? $compare : 'previous';
        $query = ['preset' => $preset, 'compare' => $compare];

        $results = [];
        $missing = [];

        foreach ($sites as $site) {
            $cached = $fresh ? null : Cache::get($this->cacheKey($site, $query));
            if (is_array($cached)) {
                $results[$site->id] = ['site' => $site] + $cached;
            } else {
                $missing[$site->id] = $site;
            }
        }

        if ($missing !== []) {
            $responses = Http::pool(function (Pool $pool) use ($missing, $query) {
                $requests = [];
                foreach ($missing as $site) {
                    $requests[] = $this->request($pool->as("site-{$site->id}"), $site)->get($site->apiUrl('site'));
                    $requests[] = $this->request($pool->as("summary-{$site->id}"), $site)->get($site->apiUrl('summary'), $query);
                }

                return $requests;
            });

            foreach ($missing as $site) {
                $entry = $this->entryFromResponses($responses["site-{$site->id}"] ?? null, $responses["summary-{$site->id}"] ?? null);
                $this->recordOutcome($site, $entry['error']);

                if ($entry['error'] === null) {
                    Cache::put($this->cacheKey($site, $query), $entry, now()->addMinutes(self::CACHE_MINUTES));
                }

                $results[$site->id] = ['site' => $site] + $entry;
            }
        }

        // Ordre d'origine des sites.
        return $sites->map(fn (SeoSite $site) => $results[$site->id])->values()->all();
    }

    /**
     * Teste la connexion (route /site). Renvoie les informations du site ou lève une exception lisible.
     *
     * @return array<string, mixed>
     */
    public function testConnection(SeoSite $site): array
    {
        try {
            $response = $this->request(Http::createPendingRequest(), $site)->get($site->apiUrl('site'));
        } catch (ConnectionException $e) {
            $this->recordOutcome($site, 'Site injoignable : '.$e->getMessage());
            throw new \RuntimeException('Site injoignable : '.$e->getMessage());
        }

        $error = $this->responseError($response);
        $this->recordOutcome($site, $error);

        if ($error !== null) {
            throw new \RuntimeException($error);
        }

        return (array) $response->json();
    }

    private function request(PendingRequest $client, SeoSite $site): PendingRequest
    {
        return $client
            ->acceptJson()
            ->withHeaders(['X-TSS-Key' => (string) $site->api_key])
            ->timeout(self::TIMEOUT)
            ->connectTimeout(10);
    }

    /**
     * @return array{info: ?array, summary: ?array, error: ?string, fetched_at: ?string}
     */
    private function entryFromResponses(mixed $siteResponse, mixed $summaryResponse): array
    {
        $error = $this->responseError($siteResponse) ?? $this->responseError($summaryResponse);

        return [
            'info' => $siteResponse instanceof Response && $siteResponse->successful() ? (array) $siteResponse->json() : null,
            'summary' => $summaryResponse instanceof Response && $summaryResponse->successful() ? (array) $summaryResponse->json() : null,
            'error' => $error,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    private function responseError(mixed $response): ?string
    {
        if ($response instanceof \Throwable) {
            return 'Site injoignable : '.$response->getMessage();
        }

        if (! $response instanceof Response) {
            return 'Aucune réponse du site.';
        }

        if ($response->successful()) {
            return is_array($response->json()) ? null : 'Réponse inattendue du site (pas de JSON). L\'extension est-elle activée ?';
        }

        $message = (string) ($response->json('message') ?? '');

        return match (true) {
            in_array($response->status(), [401, 403], true) => 'Clé d\'accès refusée par le site (HTTP '.$response->status().'). Vérifiez ou régénérez la clé dans l\'extension.',
            $response->status() === 404 => 'API introuvable (HTTP 404). L\'extension Takada SEO Suivi est-elle installée et activée ?',
            default => 'Le site a répondu HTTP '.$response->status().($message !== '' ? ' : '.$message : '.'),
        };
    }

    private function recordOutcome(SeoSite $site, ?string $error): void
    {
        $attributes = $error === null
            ? ['last_success_at' => now(), 'last_error' => null]
            : ['last_error_at' => now(), 'last_error' => mb_substr($error, 0, 1000)];

        $site->forceFill($attributes)->saveQuietly();
    }

    /** @param array<string, string> $query */
    private function cacheKey(SeoSite $site, array $query): string
    {
        // L'URL et la clé font partie de l'empreinte : les modifier invalide le cache du site.
        return 'seo-suivi.'.$site->id.'.'.sha1($site->baseUrl().'|'.$site->api_key.'|'.implode('|', $query));
    }
}

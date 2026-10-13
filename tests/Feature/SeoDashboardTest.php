<?php

use App\Models\SeoSite;
use App\Models\User;
use App\Services\SeoSuiviApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seoSite(array $attributes = []): SeoSite
{
    return SeoSite::create($attributes + [
        'name' => 'Séjour au Japon',
        'url' => 'https://www.sejouraujapon.com',
        'api_key' => 'cle-secrete',
    ]);
}

test('the api key is stored encrypted and sent in the X-TSS-Key header', function () {
    $site = seoSite();

    expect(\DB::table('seo_sites')->value('api_key'))->not->toBe('cle-secrete');

    Http::fake([
        'www.sejouraujapon.com/wp-json/tss/v1/site' => Http::response(['name' => 'Séjour au Japon', 'plugin_version' => '1.2.1', 'sources' => []]),
        'www.sejouraujapon.com/wp-json/tss/v1/summary*' => Http::response([
            'periods' => ['label_a' => 'du 01/09/2026 au 28/09/2026', 'label_b' => 'du 04/08/2026 au 31/08/2026'],
            'kpis' => ['metrics' => ['clicks' => ['a' => 120, 'b' => 100]]],
            'alerts' => [['level' => 'critical', 'type' => 'page_clicks', 'message' => 'Baisse', 'url' => '']],
            'leads' => ['totals' => ['a' => 4, 'b' => 2]],
            'monitor' => ['status_now' => ['error' => 'Méthode indisponible.']],
        ]),
    ]);

    $results = app(SeoSuiviApi::class)->dashboard(SeoSite::all(), 'last_28', 'previous');

    expect($results[0]['error'])->toBeNull()
        ->and($results[0]['summary']['kpis']['metrics']['clicks']['a'])->toBe(120)
        ->and($site->fresh()->last_success_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->hasHeader('X-TSS-Key', 'cle-secrete')
        && str_contains($request->url(), 'preset=last_28'));
});

test('a refused key is reported on the site without breaking the dashboard', function () {
    $site = seoSite();

    Http::fake([
        '*' => Http::response(['code' => 'rest_forbidden', 'message' => 'Désolé'], 401),
    ]);

    $results = app(SeoSuiviApi::class)->dashboard(SeoSite::all());

    expect($results[0]['error'])->toContain('Clé d\'accès refusée')
        ->and($site->fresh()->last_error)->toContain('HTTP 401');
});

test('successful reads are cached until a refresh is forced', function () {
    seoSite();

    Http::fake([
        '*' => Http::response(['kpis' => ['metrics' => []]]),
    ]);

    $api = app(SeoSuiviApi::class);
    $api->dashboard(SeoSite::all());
    $api->dashboard(SeoSite::all());
    Http::assertSentCount(2);

    $api->dashboard(SeoSite::all(), fresh: true);
    Http::assertSentCount(4);
});

test('only users with the seo permission can open the dashboard and manage sites', function () {
    $agent = new User(['role' => User::ROLE_AGENT]);
    $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);

    expect($agent->can('viewAny', SeoSite::class))->toBeFalse()
        ->and($superAdmin->can('viewAny', SeoSite::class))->toBeTrue()
        ->and($superAdmin->can('create', SeoSite::class))->toBeTrue();
});

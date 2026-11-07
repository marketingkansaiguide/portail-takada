<?php

use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\ItemStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionSeasonPrice;
use App\Models\ProductPeriod;
use App\Models\ProductPrice;
use App\Services\GuideMissionPayloadFactory;
use App\Services\GuidePlanningApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function guideFolderItem(array $overrides = []): FolderItem
{
    $agency = new Agency(['name' => 'Agence Test']);
    $folder = new Folder([
        'folder_name' => 'DUPONT',
        'lead_traveler_name' => 'Jean Dupont',
        'first_hotel_name' => 'Kyoto Hotel',
        'first_hotel_address' => '1 Kyoto',
    ]);
    $folder->setRelation('agency', $agency);

    $product = new Product([
        'name' => 'Visite guidée de Kyoto',
        'category_id' => 4,
        'guide_city' => 'Kyoto',
        'custom_field_definitions' => [
            ['key' => 'demande', 'name' => 'Demande particulière de guidage'],
            ['key' => 'hotel_guidage', 'name' => 'Hôtel du rendez-vous', 'type' => 'client_hotel'],
        ],
    ]);
    $option = new ProductOption([
        'name' => 'Demi-journée matin',
        'guide_start_time' => '09:00:00',
        'guide_end_time' => '13:00:00',
    ]);

    $item = new FolderItem(array_merge([
        'service_date' => '2026-10-15',
        'quantity' => 5,
        'pax_adults' => 3,
        'pax_children' => 2,
        'purchase_total_price' => 25000,
        'custom_values' => ['demande' => 'Guide francophone', 'hotel_guidage' => 'Hotel Granvia Kyoto'],
    ], $overrides));
    $item->forceFill(['id' => 456]);
    $item->setRelation('folder', $folder);
    $item->setRelation('product', $product);
    $item->setRelation('productOption', $option);

    return $item;
}

it('builds the complete guide mission payload from the ordered item', function () {
    $payload = app(GuideMissionPayloadFactory::class)->make(guideFolderItem());

    expect($payload)->toMatchArray([
        'external_source' => 'ticketing_portal',
        'external_reference' => 'folder_item_456',
        'ticketing_order_item_id' => 456,
        'title' => 'DUPONT - Kyoto',
        'agency' => 'Agence Test',
        'client' => 'DUPONT',
        'date' => '2026-10-15',
        'start_time' => '09:00',
        'end_time' => '13:00',
        'remuneration' => 25000,
        'meeting_place' => 'Hotel Granvia Kyoto',
        'specific_request' => 'Guide francophone',
    ])->and($payload['details'])
        ->toContain('Adultes : 3')
        ->toContain('Enfants : 2')
        ->toContain('Total : 5')
        ->toContain('Hotel Granvia Kyoto')
        ->not->toContain('Kyoto Hotel')
        ->toContain('Guide francophone');
});

it('omits optional empty mission details', function () {
    $item = guideFolderItem(['custom_values' => []]);
    $item->custom_values = [];

    $details = app(GuideMissionPayloadFactory::class)->make($item)['details'];

    expect($details)->not->toContain('Demande spécifique')->not->toContain('N/A')->not->toContain('null');
});

it('identifies an annulled item as non billable', function () {
    $item = guideFolderItem();
    $item->setRelation('itemStatus', new ItemStatus(['name' => 'Annulé']));

    expect($item->isCancelled())->toBeTrue();
});

it('refuses to dispatch a mission whose structured schedule is incomplete', function () {
    $item = guideFolderItem();
    $item->productOption->guide_start_time = null;

    app(GuideMissionPayloadFactory::class)->make($item);
})->throws(ValidationException::class);

it('selects the guide purchase price for the mission season', function () {
    $product = new Product(['category_id' => 4]);
    $summer = (new ProductPeriod(['start_date' => '04-01', 'end_date' => '10-31']))->forceFill(['id' => 10]);
    $winter = (new ProductPeriod(['start_date' => '11-01', 'end_date' => '03-31']))->forceFill(['id' => 20]);
    $summerPrice = (new ProductPrice(['min_pax' => 1, 'max_pax' => 10, 'pricing_mode' => 'global']))->forceFill(['id' => 101]);
    $winterPrice = (new ProductPrice(['min_pax' => 1, 'max_pax' => 10, 'pricing_mode' => 'global']))->forceFill(['id' => 201]);
    $summer->setRelation('productPrices', collect([$summerPrice]));
    $winter->setRelation('productPrices', collect([$winterPrice]));
    $product->setRelation('productPeriods', collect([$summer, $winter]));

    $option = new ProductOption(['name' => 'Journée complète']);
    $option->setRelation('product', $product);
    $option->setRelation('seasonalPrices', collect([
        new ProductOptionSeasonPrice(['product_period_id' => 10, 'product_price_id' => 101, 'purchase_price_modifier' => 25000]),
        new ProductOptionSeasonPrice(['product_period_id' => 20, 'product_price_id' => 201, 'purchase_price_modifier' => 30000]),
    ]));

    expect($option->purchasePriceForDate('2026-07-15', 5))->toBe(25000.0)
        ->and($option->purchasePriceForDate('2026-12-15', 5))->toBe(30000.0);
});

it('falls back to the base seasonal purchase price when the guide option has no seasonal price', function () {
    $product = new Product(['category_id' => 4, 'guide_city' => 'Kyoto']);
    $period = (new ProductPeriod(['start_date' => '01-01', 'end_date' => '12-31']))->forceFill(['id' => 10]);
    $price = (new ProductPrice(['min_pax' => 1, 'max_pax' => 10, 'pricing_mode' => 'global', 'purchase_price' => 18000]))->forceFill(['id' => 101]);
    $period->setRelation('productPrices', collect([$price]));
    $product->setRelation('productPeriods', collect([$period]));

    $option = new ProductOption(['name' => 'Journée complète', 'guide_start_time' => '09:00:00', 'guide_end_time' => '13:00:00']);
    $option->setRelation('product', $product);
    $option->setRelation('seasonalPrices', collect());

    $item = guideFolderItem(['purchase_total_price' => 0]);
    $item->setRelation('product', $product);
    $item->setRelation('productOption', $option);

    // Depuis le commit 3373800 : PA guide = PA du tarif de base + écart de l'option.
    // Sans tarif saisonnier propre, l'option n'ajoute aucun écart.
    expect($option->purchasePriceForDate('2026-07-15', 5))->toBe(0.0)
        ->and(app(GuideMissionPayloadFactory::class)->make($item)['remuneration'])->toBe(18000);
});

it('uses the existing private API authentication and validates the response', function () {
    config()->set('services.guide_planning.base_url', 'https://guides.test/wp-json/pm/v1');
    config()->set('services.guide_planning.api_key', 'private-test-key');
    Http::fake(['https://guides.test/wp-json/pm/v1/missions' => Http::response(['success' => true, 'data' => ['id' => 123]], 201)]);

    $result = app(GuidePlanningApi::class)->syncMission(['external_reference' => 'folder_item_456']);

    expect($result['id'])->toBe(123);
    Http::assertSent(fn ($request) => $request->hasHeader('X-PM-Api-Key', 'private-test-key'));
});

it('turns an unavailable guide API into a retryable exception', function () {
    config()->set('services.guide_planning.base_url', 'https://guides.test/wp-json/pm/v1');
    config()->set('services.guide_planning.api_key', 'private-test-key');
    Http::fake(['*' => Http::response(['message' => 'Unavailable'], 503)]);

    app(GuidePlanningApi::class)->syncMission(['external_reference' => 'folder_item_456']);
})->throws(RuntimeException::class, 'HTTP 503');

it('cancels a guide mission through the private API', function () {
    config()->set('services.guide_planning.base_url', 'https://guides.test/wp-json/pm/v1');
    config()->set('services.guide_planning.api_key', 'private-test-key');
    Http::fake(['https://guides.test/wp-json/pm/v1/missions/123/cancel' => Http::response(['success' => true], 200)]);

    app(GuidePlanningApi::class)->cancelMission(123, 456);

    Http::assertSent(fn ($request) => $request->url() === 'https://guides.test/wp-json/pm/v1/missions/123/cancel'
        && $request->hasHeader('X-PM-Api-Key', 'private-test-key')
        && $request['ticketing_order_item_id'] === 456);
});

it('rejects an unauthenticated guide status callback', function () {
    config()->set('services.guide_planning.callback_token', 'callback-secret');

    $this->postJson('/api/integrations/guide-missions/status', [
        'mission_id' => 123,
        'ticketing_order_item_id' => 456,
        'status' => 'confirmed',
    ])->assertUnauthorized();
});

it('excludes unavailable services from billable queries', function () {
    $query = FolderItem::query()->notCancelled();

    expect($query->getBindings())->toContain('%annul%', '%pas de disponibilit%');
    // Sans parenthèses, le OR échappe à la jointure du whereDoesntHave et exclut toutes les prestations.
    expect($query->toSql())->toContain('(LOWER(name) LIKE ? OR LOWER(name) LIKE ?)');
});

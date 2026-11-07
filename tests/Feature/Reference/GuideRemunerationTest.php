<?php

use App\Models\Agency;
use App\Models\Folder;
use App\Models\FolderItem;
use App\Models\Product;
use App\Services\GuideMissionPayloadFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\Catalogue;

uses(RefreshDatabase::class);

/*
 * Vérification de la rémunération guide envoyée à WordPress sur le catalogue réel.
 * Aucune prestation guide n'existe encore en production (vérifié le 2026-11-07) :
 * on contrôle donc la cohérence entre le prix d'achat calculé à la saisie
 * (moteur tarifaire) et la rémunération de repli calculée à l'envoi, quand le
 * prix d'achat n'a pas été enregistré sur la ligne.
 */
it("envoie au guide la même rémunération que le prix d'achat calculé à la saisie", function () {
    Catalogue::load();

    $checked = 0;
    $differences = [];

    foreach (Product::with('productOptions')->where('category_id', 4)->orderBy('id')->get() as $product) {
        foreach ($product->productOptions as $option) {
            foreach (['2027-01-15', '2027-07-20', '2027-10-10'] as $date) {
                foreach ([1, 4, 9] as $quantity) {
                    $priced = Catalogue::price([
                        'product_id' => $product->id,
                        'service_date' => $date,
                        'quantity' => $quantity,
                        'selected_options' => [['product_option_id' => $option->id, 'quantity' => 1]],
                        'custom_values' => [],
                    ]);

                    if ($priced['purchase_total_price'] === null) {
                        continue; // pas de prix d'achat guide pour cette combinaison
                    }

                    $folder = new Folder(['folder_name' => 'TEST', 'lead_traveler_name' => 'Test', 'first_hotel_name' => 'Hotel']);
                    $folder->setRelation('agency', new Agency(['name' => 'Agence']));
                    $item = new FolderItem(['service_date' => $date, 'quantity' => $quantity, 'pax_adults' => $quantity, 'purchase_total_price' => 0, 'product_option_id' => $option->id]);
                    $item->forceFill(['id' => 1]);
                    $item->setRelation('folder', $folder);
                    $item->setRelation('product', $product);
                    $item->setRelation('productOption', $option);

                    try {
                        $remuneration = app(GuideMissionPayloadFactory::class)->make($item)['remuneration'];
                    } catch (ValidationException) {
                        continue; // horaires de mission incomplets : la mission ne part pas
                    }

                    $checked++;
                    if ((int) round($priced['purchase_total_price']) !== (int) $remuneration) {
                        $differences["p{$product->id}|o{$option->id}|{$date}|q{$quantity}"] = ['PA saisie' => $priced['purchase_total_price'], 'rémunération envoyée' => $remuneration];
                    }
                }
            }
        }
    }

    // 477 combinaisons contrôlées sur le catalogue du 2026-11-07.
    expect($checked)->toBeGreaterThan(0)
        ->and($differences)->toBe([], json_encode(array_slice($differences, 0, 10, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
});

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prix saisis à la main par le commercial, pour un dossier seulement : le produit du catalogue n'est jamais modifié.
 * Un prix surchargé n'est ni recalculé au tarif ni remplacé par lui à la commande. Additive et réversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_planned_items', function (Blueprint $t) {
            $t->boolean('sale_price_overridden')->default(false);
            $t->boolean('purchase_price_overridden')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('folder_planned_items', fn (Blueprint $t) => $t->dropColumn(['sale_price_overridden', 'purchase_price_overridden']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Création de la table de configuration par fournisseur
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('email_subject')->nullable();
            $table->json('fax_header')->nullable();
            $table->text('email_template')->nullable();
            $table->timestamps();
        });

        // 2. Ajout de la sélection du fournisseur dans les prestations du dossier
        Schema::table('folder_items', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
        });

        // 3. Migration automatique des données existantes (ZÉRO RÉGRESSION)
        $products = DB::table('products')->whereNotNull('supplier_id')->get();
        foreach ($products as $product) {
            DB::table('product_suppliers')->insert([
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'email_subject' => $product->supplier_email_subject,
                'fax_header' => $product->supplier_fax_header,
                'email_template' => $product->supplier_email_template,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });
        Schema::dropIfExists('product_suppliers');
    }
};
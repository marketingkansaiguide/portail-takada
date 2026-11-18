<?php

use App\Filament\Pages\IwaOrders;
use App\Models\FolderItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Statut « envoyé à IWA » distinct de label_exported_at (étiquettes, Seven Tickets, import).
 * Remplissage initial : tout ce qui était marqué exporté et qui porte un trajet IWA passe en « envoyé »,
 * sinon ces prestations réapparaîtraient « à envoyer ».
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('folder_items', 'iwa_exported_at')) {
            Schema::table('folder_items', function (Blueprint $table) {
                $table->timestamp('iwa_exported_at')->nullable()->after('label_exported_at');
            });
        }

        FolderItem::query()
            ->whereNotNull('label_exported_at')
            ->whereNull('iwa_exported_at')
            ->whereNotNull('custom_values')
            ->orderBy('id')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $routes = $item->custom_values['transport_routes'] ?? [];
                    if (! is_array($routes) || $routes === []) {
                        continue;
                    }
                    foreach ($routes as $route) {
                        if (is_array($route) && IwaOrders::isIwaRoute($route, $item)) {
                            DB::table('folder_items')->where('id', $item->id)
                                ->update(['iwa_exported_at' => $item->label_exported_at]);
                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('folder_items', function (Blueprint $table) {
            $table->dropColumn('iwa_exported_at');
        });
    }
};

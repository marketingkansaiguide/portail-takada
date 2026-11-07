<?php

namespace App\Filament\Resources\Folders;

use App\Models\Folder;
use App\Models\FolderOrderBatch;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

/**
 * Section « Prestations en préparation » d'un dossier interne : même formulaire et
 * même moteur tarifaire que les prestations commandées, sans aucun automatisme.
 */
final class PlannedItemsSection
{
    public static function make(bool $commercial = false): Section
    {
        return Section::make(__('Prestations en préparation'))
            ->extraAttributes(['data-tk-anchor' => 'prestations'])
            ->description(__('Préparez librement les prestations (budget prévisionnel). Rien n’est commandé ni transmis tant que vous n’utilisez pas « Commander les prestations sélectionnées ». Enregistrez le dossier avant de commander.'))
            ->visible(fn (?Folder $record): bool => (bool) $record?->isInternal())
            ->columnSpanFull()
            ->schema([
                Placeholder::make('planned_budget')
                    ->hiddenLabel()
                    ->content(fn ($get) => $commercial ? self::commercialBudget($get('plannedItems') ?? []) : self::budget($get('plannedItems') ?? [])),

                Repeater::make('plannedItems')
                    ->relationship('plannedItems')
                    ->hiddenLabel()
                    ->addActionLabel(__('Préparer une prestation'))
                    ->collapsible()
                    ->collapsed()
                    ->live()
                    ->defaultItems(0)
                    ->itemLabel(function (array $state) {
                        if (empty($state['product_id'])) {
                            return __('Nouvelle prestation en préparation');
                        }
                        $name = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                        $date = ! empty($state['service_date']) ? \Carbon\Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                        $sale = number_format((float) ($state['total_price'] ?? 0), 0, ',', ' ');

                        return "📝 {$date} · {$name} · " . (int) ($state['quantity'] ?? 1) . " pax · {$sale} ¥ · " . __('En préparation');
                    })
                    ->schema([
                        ...FolderResource::folderItemSchema($commercial ? 'commercial' : 'planned'),
                        ...($commercial
                            ? self::commercialFields()
                            : [Textarea::make('notes')->label(__('Notes opérationnelles'))->rows(2)->columnSpanFull()]),
                    ]),

                Placeholder::make('order_history')
                    ->label(__('Historique des commandes'))
                    ->content(fn (?Folder $record) => self::history($record)),
            ]);
    }

    /**
     * Champs propres au commercial : rendez-vous (réutilisant hôtels et vols déjà saisis dans le dossier)
     * et consignes pour la billetterie. Aucun envoi externe n'en découle.
     *
     * @return array<int, mixed>
     */
    private static function commercialFields(): array
    {
        return [
            \Filament\Schemas\Components\Group::make()->columns(3)->columnSpanFull()->schema([
            \Filament\Forms\Components\Select::make('meeting_suggestion')
                ->label(__('Lieu de rendez-vous : choisir dans le dossier'))
                ->helperText(__('Hôtels et vols déjà saisis, le plus proche de la date de la prestation en premier. Vous pouvez toujours saisir un autre lieu ci-contre.'))
                ->placeholder(__('Hôtel ou vol du dossier…'))
                ->options(fn ($get) => \App\Support\MeetingSuggestions::options($get('service_date'), $get('../../stays') ?? [], $get('../../flightSegments') ?? []))
                ->searchable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function ($state, $set) {
                    if (filled($state)) {
                        $set('meeting_point', $state);
                    }
                })
                ->columnSpan(1),
            \Filament\Forms\Components\TextInput::make('meeting_point')
                ->label(__('Rendez-vous / point de prise en charge'))
                ->helperText(__('Valeur conservée telle quelle sur la commande, même si la fiche hôtel change ensuite.'))
                ->maxLength(500)
                ->columnSpan(1),
            \Filament\Forms\Components\TextInput::make('meeting_time')
                ->label(__('Heure'))
                ->placeholder('09:30')
                ->rule('regex:/^([01]\d|2[0-3]):[0-5]\d$/')
                ->maxLength(5)
                ->columnSpan(1),
            ]),
            Textarea::make('ticketing_notes')
                ->label(__('Informations complémentaires pour la billetterie'))
                ->helperText(__('Facultatif. Horaire souhaité, hôtel de prise en charge, préférences du client, contraintes, consignes de réservation. Visible de la billetterie sur la prestation commandée ; jamais transmis automatiquement à un fournisseur, un guide ou une agence.'))
                ->rows(4)
                ->columnSpanFull(),
        ];
    }

    private static function commercialBudget(array $items): HtmlString
    {
        $sale = array_sum(array_map(fn ($i) => (float) ($i['total_price'] ?? 0), $items));

        return new HtmlString('<div style="font-size:0.9rem"><strong>' . e(__('Vente prévisionnelle')) . '</strong> : ' . number_format($sale, 0, ',', ' ') . ' ¥ · '
            . e(trans_choice(':count prestation en préparation|:count prestations en préparation', count($items), ['count' => count($items)])) . ' · <span style="color:#6b7280">' . e(__('Coûts et marge : onglet Finances et suivi')) . '</span></div>');
    }

    private static function budget(array $items): HtmlString
    {
        $sale = 0;
        $purchase = 0;
        $missing = 0;
        foreach ($items as $item) {
            $sale += (float) ($item['total_price'] ?? 0);
            if (($item['purchase_total_price'] ?? null) === null || $item['purchase_total_price'] === '') {
                $missing++;
            } else {
                $purchase += (float) $item['purchase_total_price'];
            }
        }
        $fmt = fn ($v) => number_format($v, 0, ',', ' ') . ' ¥';
        $warn = $missing ? ' · <span style="color:#b45309">⚠ ' . e(__(':n prix d’achat manquant(s) : marge incomplète', ['n' => $missing])) . '</span>' : '';

        return new HtmlString('<div style="font-size:0.9rem"><strong>' . e(__('Budget prévisionnel (préparation)')) . '</strong> : '
            . e(__('vente')) . ' ' . $fmt($sale) . ' · ' . e(__('achat')) . ' ' . $fmt($purchase) . ' · '
            . e(__('marge estimée')) . ' ' . $fmt($sale - $purchase) . $warn . '</div>');
    }

    private static function history(?Folder $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }
        $batches = FolderOrderBatch::with('user')->where('folder_id', $record->id)->latest('id')->limit(20)->get();
        if ($batches->isEmpty()) {
            return new HtmlString('<span style="color:#6b7280">' . e(__('Aucune commande pour l’instant.')) . '</span>');
        }
        $rows = $batches->map(function (FolderOrderBatch $batch) {
            $labels = collect($batch->result ?? [])->pluck('label')->map(fn ($l) => e($l))->implode('<br>');

            return '<tr><td style="padding:4px 8px;white-space:nowrap">' . e($batch->created_at->format('d/m/Y H:i')) . '</td><td style="padding:4px 8px">'
                . e($batch->user?->name ?? '—') . '</td><td style="padding:4px 8px">' . $labels . '</td></tr>';
        })->implode('');

        return new HtmlString('<table style="font-size:0.85rem"><thead><tr><th style="text-align:left;padding:4px 8px">' . e(__('Date')) . '</th><th style="text-align:left;padding:4px 8px">'
            . e(__('Commandé par')) . '</th><th style="text-align:left;padding:4px 8px">' . e(__('Prestations')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>');
    }
}

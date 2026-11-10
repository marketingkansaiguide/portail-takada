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
            ->description($commercial
                ? __('Cochez les lignes à commander, puis confirmez dans la barre en bas de l’écran. Rien n’est transmis avant cette confirmation.')
                : __('Préparez librement les prestations (budget prévisionnel). Rien n’est commandé ni transmis tant que vous n’utilisez pas « Commander les prestations sélectionnées ». Enregistrez le dossier avant de commander.'))
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
                    ->label(__('Prestations en préparation'))
                    ->deleteAction(fn (\Filament\Actions\Action $action) => $commercial ? $action->requiresConfirmation()->extraAttributes(['style' => 'margin-left:1rem']) : $action)
                    ->itemLabel(function (array $state, string $key, $livewire) use ($commercial) {
                        if (empty($state['product_id'])) {
                            $text = __('Nouvelle prestation en préparation');
                        } else {
                            $name = \App\Models\Product::find($state['product_id'])?->name ?? __('Produit inconnu');
                            if ($commercial) {
                                $name = \App\Support\EntryProfile::itemLabel($name, (array) ($state['custom_values'] ?? []), $state['route_from'] ?? null, $state['route_to'] ?? null);
                            }
                            $date = ! empty($state['service_date']) ? \Carbon\Carbon::parse($state['service_date'])->format('d/m/Y') : '---';
                            $sale = number_format((float) ($state['total_price'] ?? 0), 0, ',', ' ');
                            $text = "📝 {$date} · {$name} · " . (int) ($state['quantity'] ?? 1) . " pax · {$sale} ¥ · " . __('En préparation');
                        }

                        return $commercial ? self::selectableLabel($text, $key, $livewire) : $text;
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
     * Libellé de ligne avec une vraie case à cocher (sélection portée par la page, jamais enregistrée).
     * Une ligne pas encore enregistrée n'a pas d'identifiant : sa case est désactivée.
     */
    private static function selectableLabel(string $text, string $key, $livewire): \Illuminate\Support\HtmlString
    {
        $id = preg_match('/^record-(\d+)$/', $key, $m) ? (int) $m[1] : null;
        $checked = $id !== null && in_array($id, $livewire->selectedPlannedIds, true);
        $attrs = $id === null
            ? 'disabled title="' . e(__('Enregistrez d’abord cette ligne pour pouvoir la commander.')) . '"'
            : 'wire:click="togglePlannedSelection(' . $id . ')"' . ($checked ? ' checked' : '');

        return new \Illuminate\Support\HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:.5rem">'
            . '<label x-on:click.stop style="display:inline-flex;align-items:center;justify-content:center;min-width:36px;min-height:36px;cursor:pointer">'
            . '<input type="checkbox" class="fi-checkbox-input" style="width:1.125rem;height:1.125rem" ' . $attrs
            . ' aria-label="' . e(__('Sélectionner pour commander')) . ' : ' . e($text) . '"></label>'
            . '<span>' . e($text) . '</span></span>'
        );
    }

    /**
     * Champs propres au commercial : rendez-vous (réutilisant hôtels et vols déjà saisis dans le dossier)
     * et consignes pour la billetterie. Aucun envoi externe n'en découle.
     *
     * @return array<int, mixed>
     */
    public static function commercialFields(): array
    {
        $profileOf = fn (\Filament\Schemas\Components\Utilities\Get $get): string => \App\Support\EntryProfile::for(
            $get('product_id') ? \App\Models\Product::with('category')->find($get('product_id')) : null
        );
        // Un produit à trajets (billets train / bus) a déjà son bloc « Trajets » : seule source du trajet et des heures.
        $hasRoutes = fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('product_id') && \App\Models\Product::whereKey($get('product_id'))->value('product_type') === 'transport';
        $show = fn (string $field) => fn (\Filament\Schemas\Components\Utilities\Get $get): bool => ! $hasRoutes($get)
            && in_array($field, \App\Support\EntryProfile::fields($profileOf($get)), true);
        $timeRule = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';
        $places = fn ($get) => \App\Support\MeetingSuggestions::options($get('service_date'), $get('../../stays') ?? [], $get('../../flightSegments') ?? []);

        return [
            \Filament\Schemas\Components\Group::make()->columns(3)->columnSpanFull()->schema([
                \Filament\Forms\Components\Select::make('flight')
                    ->label(__('Vol du dossier'))
                    ->placeholder(__('Choisir le vol d’arrivée…'))
                    ->options(function ($get) {
                        $options = [];
                        foreach (($get('../../flightSegments') ?? []) as $flight) {
                            if (blank($flight['arrival_airport'] ?? null)) {
                                continue;
                            }
                            $label = trim(($flight['flight_number'] ?? '') . ' → ' . $flight['arrival_airport']);
                            $options[$flight['arrival_airport'] . '|' . ($flight['flight_number'] ?? '') . '|' . substr((string) ($flight['arrival_local'] ?? ''), 11, 5)] = $label
                                . (filled($flight['arrival_local'] ?? null) ? ' · ' . substr((string) $flight['arrival_local'], 11, 5) : '');
                        }

                        return $options;
                    })
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, $set) {
                        if (filled($state)) {
                            [$airport, $number, $time] = array_pad(explode('|', $state), 3, '');
                            $set('meeting_point', trim(__('Aéroport :a', ['a' => $airport]) . ($number ? ' — ' . __('vol :n', ['n' => $number]) : '')));
                            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                                $set('meeting_time', $time);
                            }
                        }
                    })
                    ->visible($show('flight'))
                    ->columnSpan(3),
                \Filament\Forms\Components\TextInput::make('meeting_time')
                    ->label(fn ($get) => $profileOf($get) === \App\Support\EntryProfile::TRAIN_BUS ? __('Heure de départ') : __('Heure'))
                    ->placeholder('09:30')->rule($timeRule)->maxLength(5)
                    ->visible($show('meeting_time'))->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('end_time')
                    ->label(__('Heure d’arrivée'))->placeholder('11:52')->rule($timeRule)->maxLength(5)
                    ->visible($show('end_time'))->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('route_from')
                    ->label(fn ($get) => $profileOf($get) === \App\Support\EntryProfile::TRAIN_BUS ? __('Gare de départ') : __('Départ'))
                    ->datalist(fn ($get) => array_values(array_unique(array_map(fn ($v) => trim(explode(' — ', $v)[0]), array_keys($places($get))))))
                    ->maxLength(150)->visible($show('route_from'))->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('route_to')
                    ->label(fn ($get) => $profileOf($get) === \App\Support\EntryProfile::TRAIN_BUS ? __('Gare d’arrivée') : __('Arrivée'))
                    ->datalist(fn ($get) => array_values(array_unique(array_map(fn ($v) => trim(explode(' — ', $v)[0]), array_keys($places($get))))))
                    ->maxLength(150)->visible($show('route_to'))->columnSpan(1),
                \Filament\Forms\Components\DatePicker::make('end_date')
                    ->label(__('Date de fin'))->native(false)->displayFormat('d/m/Y')->afterOrEqual('service_date')
                    ->visible($show('end_date'))->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('meeting_point')
                    ->label(fn ($get) => match ($profileOf($get)) {
                        \App\Support\EntryProfile::AIRPORT_WELCOME => __('Lieu de prise en charge'),
                        \App\Support\EntryProfile::RENTAL => __('Lieu de remise'),
                        default => __('Rendez-vous'),
                    })
                    ->helperText(__('Suggestions : hôtels et vols du dossier. Valeur conservée sur la commande.'))
                    ->datalist(fn ($get) => array_keys($places($get)))
                    ->maxLength(500)->visible($show('meeting_point'))->columnSpan(2),
            ]),
            Textarea::make('ticketing_notes')
                ->label(__('Note pour la billetterie'))
                ->helperText(__('Visible de la billetterie sur la prestation commandée ; jamais transmise à un fournisseur, un guide ou une agence.'))
                ->rows(2)
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

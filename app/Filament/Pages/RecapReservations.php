<?php

namespace App\Filament\Pages;

use App\Models\Agency;
use App\Models\FolderItem;
use App\Models\ItemStatus;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Récap réservations : toutes les prestations de tous les dossiers sur un seul écran,
 * avec les quatre dates de suivi de la billetterie (équivalent de l'onglet « RÉCAP RÉSAS » de l'Excel).
 */
class RecapReservations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Récap réservations';
    protected static ?string $title = 'Récap réservations';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.recap-reservations';

    public static function canAccess(): bool
    {
        return AdminAccess::allows('operations.recap');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FolderItem::query()
                    ->with(['folder.agency', 'product', 'itemStatus', 'supplier'])
                    ->orderBy('service_date')
                    ->orderBy('id')
            )
            ->defaultPaginationPageOption(50)
            ->columns([
                Tables\Columns\TextColumn::make('folder.reference')
                    ->label('Dossier')
                    ->searchable()
                    ->description(fn (FolderItem $r) => $r->folder?->lead_traveler_name)
                    ->url(fn (FolderItem $r) => \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $r->folder_id])),
                Tables\Columns\TextColumn::make('folder.agency.name')->label('Agence')->placeholder('Interne')->toggleable(),
                Tables\Columns\TextColumn::make('service_date')->label('Date')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Prestation')
                    ->getStateUsing(fn (FolderItem $r) => $r->title ?: $r->product?->name)
                    ->description(fn (FolderItem $r) => trim(($r->route_from ? $r->route_from.' → '.$r->route_to : '').' '.$r->ticketing_notes))
                    ->wrap()
                    ->searchable(query: fn (Builder $q, string $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%")))),
                Tables\Columns\TextColumn::make('quantity')->label('Pax')->alignCenter(),
                Tables\Columns\TextColumn::make('purchase_total_price')->label('PA total')->numeric(0, ',', ' ')->suffix(' ¥')->alignEnd(),
                Tables\Columns\TextColumn::make('itemStatus.name')->label('Statut')->badge()
                    ->color(fn (FolderItem $r) => $r->itemStatus?->color ?? 'gray'),
                Tables\Columns\TextColumn::make('requested_at')->label('Demande envoyée')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('supplier_confirmed_at')->label('Confirmée')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('invoice_received_at')->label('Facture reçue')->date('d/m/Y')->placeholder('—'),
                Tables\Columns\TextColumn::make('purchased_at')->label('Acheté (sans facture)')->date('d/m/Y')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('delivered_at')->label('Billetterie envoyée')->date('d/m/Y')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('item_status_id')->label('Statut')
                    ->options(fn () => ItemStatus::pluck('name', 'id'))->multiple(),
                SelectFilter::make('agency')->label('Agence')
                    ->options(fn () => Agency::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data["value"] ?? null)
                        ? $query->whereHas("folder", fn ($f) => $f->where("agency_id", $data["value"])) : $query),
                Filter::make('to_do')->label('À faire')->toggle()
                    ->query(fn (Builder $query) => $query->where(fn ($w) => $w->whereNull('supplier_confirmed_at')->orWhereNull('delivered_at'))),
                Filter::make('period')
                    ->form([DatePicker::make('from')->label('Du'), DatePicker::make('until')->label('Au')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data["from"] ?? null, fn ($q, $v) => $q->whereDate('service_date', '>=', $v))
                        ->when($data["until"] ?? null, fn ($q, $v) => $q->whereDate('service_date', '<=', $v))),
            ])
            ->recordActions([
                Action::make('followup')
                    ->label('Suivi')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->fillForm(fn (FolderItem $r) => $r->only(['requested_at', 'supplier_confirmed_at', 'invoice_received_at', 'purchased_at', 'delivered_at']))
                    ->form([
                        DatePicker::make('requested_at')->label('Demande envoyée au fournisseur (fax / mail)'),
                        DatePicker::make('supplier_confirmed_at')->label('Confirmation du fournisseur'),
                        DatePicker::make('invoice_received_at')->label('Facture reçue'),
                        DatePicker::make('purchased_at')->label('Achat sans facture (CB / espèces)'),
                        DatePicker::make('delivered_at')->label('Billetterie envoyée à l\'hôtel / au guide'),
                    ])
                    ->action(function (FolderItem $record, array $data) {
                        // Écriture silencieuse : une simple date de suivi ne doit déclencher ni mission guide ni Calendar.
                        $record->forceFill($data)->saveQuietly();
                        Notification::make()->title('Suivi mis à jour')->success()->send();
                    }),
            ]);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\FolderMessage;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action; 
use Filament\Widgets\TableWidget as BaseWidget;

class LatestMessagesWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    protected function getTableHeading(): string|null
    {
        return __('💬 Aperçu des derniers messages (Messagerie)');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FolderMessage::query()
                    ->with(['folder', 'user'])
                    ->latest() 
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Date & Heure'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('folder.folder_name')
                    ->label(__('Nom du dossier'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Auteur'))
                    ->badge()
                    ->color(function ($record) {
                        return ($record->user && $record->user->role === 'agency') ? 'warning' : 'info';
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('Message'))
                    ->limit(80) 
                    ->searchable()
                    ->tooltip(fn ($record) => $record->message),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Voir / Répondre'))
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->url(fn (FolderMessage $record): string => $record->folder_id ? \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record->folder_id]) : '#')
                    ->button()
                    ->color('primary'),
            ])
            ->paginated([5, 10, 'all'])
            ->defaultPaginationPageOption(5);
    }
}
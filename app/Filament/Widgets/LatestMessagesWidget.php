<?php

namespace App\Filament\Widgets;

use App\Models\FolderMessage;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action; // L'import d'origine qui fonctionne !
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Str;

class LatestMessagesWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 1;
    protected static ?int $sort = 2;

    protected function getTableHeading(): string|null
    {
        return __('💬 Derniers messages');
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('Auteur'))
                    ->weight('bold')
                    ->wrap()
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->color(fn ($record) => ($record->user && $record->user->role === 'agency') ? 'warning' : 'info'),

                Tables\Columns\TextColumn::make('folder.folder_name')
                    ->label(__('Dossier'))
                    ->weight('bold')
                    ->wrap()
                    ->limit(20)
                    ->description(fn ($record) => Str::limit($record->message, 30))
                    ->tooltip(fn ($record) => $record->message),
            ])
            ->recordActions([
                Action::make('open')
                    ->hiddenLabel()
                    ->tooltip(__('Voir / Répondre'))
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->url(fn (FolderMessage $record): string => $record->folder_id ? \App\Filament\Resources\Folders\FolderResource::getUrl('edit', ['record' => $record->folder_id]) : '#')
                    ->color('primary'),
            ])
            ->paginated([5])
            ->defaultPaginationPageOption(5)
            // L'astuce Flexbox pour forcer le 100% de hauteur
            ->extraAttributes([
                'class' => 'h-full flex flex-col',
            ]);
    }
}
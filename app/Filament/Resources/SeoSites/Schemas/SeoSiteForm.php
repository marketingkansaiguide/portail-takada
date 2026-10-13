<?php

namespace App\Filament\Resources\SeoSites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SeoSiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Site WordPress'))
                ->description(__('Le site doit avoir l\'extension « Takada SEO Suivi » installée. La clé se génère dans WordPress : Suivi SEO > Export IA > Clé d\'accès tableau de bord.'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Nom'))
                        ->placeholder('Séjour au Japon')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('url')
                        ->label(__('Adresse du site'))
                        ->placeholder('https://www.sejouraujapon.com')
                        ->url()
                        ->required()
                        ->maxLength(255)
                        ->dehydrateStateUsing(fn (?string $state): string => rtrim(trim((string) $state), '/')),

                    TextInput::make('api_key')
                        ->label(__('Clé d\'accès (X-TSS-Key)'))
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? __('Laisser vide pour conserver la clé actuelle. Elle est stockée chiffrée.')
                            : __('Elle est stockée chiffrée et n\'est plus affichée ensuite.')),

                    TextInput::make('sort_order')
                        ->label(__('Ordre d\'affichage'))
                        ->numeric()
                        ->default(0),

                    Toggle::make('is_active')
                        ->label(__('Afficher dans le tableau de bord'))
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}

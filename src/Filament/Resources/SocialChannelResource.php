<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Components\CheckboxList;
use Dashed\DashedMarketing\Models\SocialChannel;
use Illuminate\Support\Facades\Schema as DbSchema;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource\Pages\EditSocialChannel;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource\Pages\ListSocialChannels;
use Dashed\DashedMarketing\Filament\Resources\SocialChannelResource\Pages\CreateSocialChannel;

class SocialChannelResource extends Resource
{
    protected static ?string $model = SocialChannel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static string|UnitEnum|null $navigationGroup = 'Social media';

    protected static ?string $navigationLabel = 'Kanalen';

    protected static ?string $label = 'Kanaal';

    protected static ?string $pluralLabel = 'Kanalen';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('Kanaal'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Naam'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, callable $set, callable $get) {
                                if ($context === 'create' && $state && ! $get('slug')) {
                                    $set('slug', Str::slug($state, '_'));
                                }
                            }),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?SocialChannel $record) => $record !== null)
                            ->dehydrated()
                            ->helperText(__('Intern identifier. Kan na aanmaken niet worden gewijzigd.')),
                        CheckboxList::make('accepted_types')
                            ->label(__('Toegestane types'))
                            ->options([
                                'post' => __('Post'),
                                'reel' => __('Reel / Short'),
                                'story' => __('Story'),
                            ])
                            ->required()
                            ->minItems(1)
                            ->columns(3)
                            ->columnSpanFull(),
                        TextInput::make('order')
                            ->label(__('Volgorde'))
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('Actief'))
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make(__('Omnisocials koppeling'))
                    ->schema([
                        Select::make('omnisocials_account_id')
                            ->label(__('Omnisocials account'))
                            ->options(function () {
                                $cached = Customsetting::get('omnisocials_accounts');
                                $accounts = is_array($cached) ? $cached : (is_string($cached) ? json_decode($cached, true) : []);

                                return collect($accounts ?: [])
                                    ->mapWithKeys(fn (array $account) => [
                                        $account['id'] => ($account['display_name'] ?? $account['username'] ?? $account['id']).' - '.($account['platform'] ?? '?'),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $cached = Customsetting::get('omnisocials_accounts');
                                $accounts = is_array($cached) ? $cached : (is_string($cached) ? json_decode($cached, true) : []);
                                $match = collect($accounts)->firstWhere('id', $state);
                                $set('omnisocials_platform', $match['platform'] ?? null);
                            })
                            ->helperText(__('Selecteer het Omnisocials account dat aan dit kanaal gekoppeld moet worden. Sync eerst accounts in Omnisocials instellingen.')),
                        TextInput::make('omnisocials_platform')
                            ->label(__('Omnisocials Platform'))
                            ->disabled()
                            ->dehydrated()
                            ->helperText(__('Wordt automatisch ingevuld op basis van het geselecteerde account.')),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn () => DbSchema::hasColumn('dashed__social_channels', 'omnisocials_account_id')),

                Section::make(__('Limieten en tips'))
                    ->schema([
                        TextInput::make('meta.caption_min')
                            ->label(__('Caption min'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.caption_max')
                            ->label(__('Caption max'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.hashtags_min')
                            ->label(__('Hashtags min'))
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.hashtags_max')
                            ->label(__('Hashtags max'))
                            ->numeric()
                            ->default(0),
                        Textarea::make('meta.tips')
                            ->label(__('Tips'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Naam'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->badge(),
                TextColumn::make('accepted_types')
                    ->label(__('Types'))
                    ->badge(),
                TextColumn::make('omnisocials_platform')
                    ->label(__('Omnisocials'))
                    ->badge()
                    ->color('info')
                    ->visible(fn () => DbSchema::hasColumn('dashed__social_channels', 'omnisocials_account_id')),
                TextColumn::make('order')
                    ->label(__('Volgorde'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('Actief'))
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSocialChannels::route('/'),
            'create' => CreateSocialChannel::route('/create'),
            'edit' => EditSocialChannel::route('/{record}/edit'),
        ];
    }
}

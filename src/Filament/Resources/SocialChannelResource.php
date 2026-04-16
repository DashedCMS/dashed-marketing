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
use Dashed\DashedCore\Models\Customsetting;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Kanalen';

    protected static ?string $label = 'Kanaal';

    protected static ?string $pluralLabel = 'Kanalen';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Kanaal')
                    ->schema([
                        TextInput::make('name')
                            ->label('Naam')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $context, $state, callable $set, callable $get) {
                                if ($context === 'create' && $state && ! $get('slug')) {
                                    $set('slug', Str::slug($state, '_'));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?SocialChannel $record) => $record !== null)
                            ->dehydrated()
                            ->helperText('Intern identifier. Kan na aanmaken niet worden gewijzigd.'),
                        CheckboxList::make('accepted_types')
                            ->label('Toegestane types')
                            ->options([
                                'post' => 'Post',
                                'reel' => 'Reel / Short',
                                'story' => 'Story',
                            ])
                            ->required()
                            ->minItems(1)
                            ->columns(3)
                            ->columnSpanFull(),
                        TextInput::make('order')
                            ->label('Volgorde')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Actief')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Omnisocials koppeling')
                    ->schema([
                        Select::make('omnisocials_account_id')
                            ->label('Omnisocials account')
                            ->options(function () {
                                $cached = Customsetting::get('omnisocials_accounts_cache');
                                $accounts = is_array($cached) ? $cached : [];

                                return collect($accounts)
                                    ->mapWithKeys(fn (array $account) => [
                                        $account['id'] => ($account['handle'] ?? $account['name'] ?? $account['id']) . ' (' . ($account['platform'] ?? '?') . ')',
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $cached = Customsetting::get('omnisocials_accounts_cache');
                                $accounts = is_array($cached) ? $cached : [];
                                $match = collect($accounts)->firstWhere('id', $state);
                                $set('omnisocials_platform', $match['platform'] ?? null);
                            })
                            ->helperText('Selecteer het Omnisocials account dat aan dit kanaal gekoppeld moet worden. Sync eerst accounts in Omnisocials instellingen.'),
                        TextInput::make('omnisocials_platform')
                            ->label('Omnisocials Platform')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Wordt automatisch ingevuld op basis van het geselecteerde account.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn () => DbSchema::hasColumn('dashed__social_channels', 'omnisocials_account_id')),

                Section::make('Limieten en tips')
                    ->schema([
                        TextInput::make('meta.caption_min')
                            ->label('Caption min')
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.caption_max')
                            ->label('Caption max')
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.hashtags_min')
                            ->label('Hashtags min')
                            ->numeric()
                            ->default(0),
                        TextInput::make('meta.hashtags_max')
                            ->label('Hashtags max')
                            ->numeric()
                            ->default(0),
                        Textarea::make('meta.tips')
                            ->label('Tips')
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
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge(),
                TextColumn::make('accepted_types')
                    ->label('Types')
                    ->badge(),
                TextColumn::make('omnisocials_platform')
                    ->label('Omnisocials')
                    ->badge()
                    ->color('info')
                    ->visible(fn () => DbSchema::hasColumn('dashed__social_channels', 'omnisocials_account_id')),
                TextColumn::make('order')
                    ->label('Volgorde')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actief')
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

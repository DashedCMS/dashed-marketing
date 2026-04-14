<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\CreateSocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\EditSocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\ListSocialPosts;
use Dashed\DashedMarketing\Models\SocialPost;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class SocialPostResource extends Resource
{
    protected static ?string $model = SocialPost::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Social posts';

    protected static ?string $label = 'Social post';

    protected static ?string $pluralLabel = 'Social posts';

    protected static ?int $navigationSort = 10;

    public static function getPlatformOptions(): array
    {
        $platforms = config('dashed-marketing.platforms', []);

        return array_map(fn ($p) => $p['label'], $platforms);
    }

    public static function getTypeOptions(): array
    {
        return array_map(
            fn ($t) => $t['label'],
            config('dashed-marketing.types', [])
        );
    }

    public static function getChannelOptions(?string $forType = null): array
    {
        $options = [];
        foreach (config('dashed-marketing.channels', []) as $key => $channel) {
            if ($forType && ! in_array($forType, $channel['accepted_types'] ?? [], true)) {
                continue;
            }
            $options[$key] = $channel['label'];
        }

        return $options;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Post inhoud')
                    ->schema([
                        Select::make('type')
                            ->label('Type post')
                            ->options(static::getTypeOptions())
                            ->default('post')
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('channels', [])),
                        CheckboxList::make('channels')
                            ->label('Kanalen')
                            ->options(fn (callable $get) => static::getChannelOptions($get('type')))
                            ->columns(2)
                            ->columnSpanFull()
                            ->required(),
                        Select::make('status')
                            ->label('Status')
                            ->options(SocialPost::STATUSES)
                            ->required()
                            ->default('concept'),
                        Textarea::make('caption')
                            ->label('Caption')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                        TagsInput::make('hashtags')
                            ->label('Hashtags')
                            ->placeholder('#hashtag')
                            ->columnSpanFull(),
                        Textarea::make('alt_text')
                            ->label('Alt-tekst afbeelding')
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('image_prompt')
                            ->label('Afbeelding prompt (AI)')
                            ->columnSpanFull(),
                        Placeholder::make('generated_image')
                            ->label('Gegenereerde afbeelding')
                            ->content(function (?SocialPost $record): HtmlString|string {
                                if (! $record || ! $record->image_path) {
                                    return '—';
                                }

                                $url = str_starts_with($record->image_path, 'http')
                                    ? $record->image_path
                                    : asset($record->image_path);

                                return new HtmlString(
                                    '<img src="'.e($url).'" alt="Gegenereerde afbeelding" '
                                    .'style="max-width:320px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);" />'
                                );
                            })
                            ->visible(fn (?SocialPost $record) => $record && (bool) $record->image_path)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Planning')
                    ->schema([
                        Select::make('pillar_id')
                            ->label('Content pijler')
                            ->relationship('pillar', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('campaign_id')
                            ->label('Campagne')
                            ->relationship('campaign', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        DateTimePicker::make('scheduled_at')
                            ->label('Ingepland op')
                            ->nullable(),
                        DateTimePicker::make('posted_at')
                            ->label('Gepost op')
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Resultaat')
                    ->schema([
                        TextInput::make('post_url')
                            ->label('Post URL')
                            ->url()
                            ->nullable()
                            ->columnSpanFull(),
                        KeyValue::make('performance_data')
                            ->label('Prestaties')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->height(48)
                    ->square()
                    ->getStateUsing(fn (SocialPost $record) => $record->image_path
                        ? (str_starts_with($record->image_path, 'http') ? $record->image_path : asset($record->image_path))
                        : null),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => $state ? config("dashed-marketing.types.{$state}.label", $state) : '-')
                    ->badge()
                    ->sortable(),
                TextColumn::make('channels')
                    ->label('Kanalen')
                    ->formatStateUsing(function ($state): string {
                        $channels = is_array($state) ? $state : (json_decode((string) $state, true) ?: []);
                        if (empty($channels)) {
                            return '-';
                        }

                        return collect($channels)
                            ->map(fn ($c) => config("dashed-marketing.channels.{$c}.label", $c))
                            ->implode(', ');
                    })
                    ->wrap(),
                TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => SocialPost::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => SocialPost::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('pillar.name')
                    ->label('Pijler')
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->label('Ingepland')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(static::getTypeOptions()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SocialPost::STATUSES),
                SelectFilter::make('pillar_id')
                    ->label('Pijler')
                    ->relationship('pillar', 'name'),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->recordActions([
                EditAction::make(),
                Action::make('markPosted')
                    ->label('Markeer als gepost')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SocialPost $record) => $record->status !== 'posted')
                    ->form([
                        TextInput::make('post_url')
                            ->label('Post URL (optioneel)')
                            ->url()
                            ->nullable(),
                    ])
                    ->action(function (SocialPost $record, array $data): void {
                        $record->markAsPosted($data['post_url'] ?? null);
                    }),
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
            'index' => ListSocialPosts::route('/'),
            'create' => CreateSocialPost::route('/create'),
            'edit' => EditSocialPost::route('/{record}/edit'),
        ];
    }
}

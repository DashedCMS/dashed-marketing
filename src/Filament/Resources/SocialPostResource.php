<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\CreateSocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\EditSocialPost;
use Dashed\DashedMarketing\Filament\Resources\SocialPostResource\Pages\ListSocialPosts;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedMarketing\Models\SocialChannel;
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
        $channels = SocialChannel::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        if ($forType) {
            $channels = $channels->filter(
                fn (SocialChannel $channel) => in_array($forType, $channel->accepted_types ?? [], true)
            );
        }

        return $channels->pluck('name', 'slug')->toArray();
    }

    public static function resolveChannelLabel(string $slug): string
    {
        static $cache = [];

        $key = Sites::getActive() . ':' . $slug;

        return $cache[$key] ??= SocialChannel::query()
            ->where('slug', $slug)
            ->value('name') ?? $slug;
    }

    /**
     * Resolve an image path to a public URL, handling both legacy 'storage/...'
     * paths and new disk-relative paths (e.g. 'social-generated/foo.png').
     */
    public static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        return asset('storage/'.$path);
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
                            ->helperText('Wordt gebruikt door "Genereer afbeelding met AI" als basisprompt. Pas aan om volgende generaties te sturen.')
                            ->columnSpanFull(),
                        Placeholder::make('generated_images_preview')
                            ->label('Afbeeldingen')
                            ->helperText('Upload nieuwe afbeeldingen via "Upload afbeelding" of laat AI ze genereren via "Genereer afbeelding met AI". Sleep nog niet ondersteund - gebruik de knoppen om volgorde te veranderen.')
                            ->content(function (?SocialPost $record): HtmlString|string {
                                if (! $record) {
                                    return new HtmlString('<em>Sla de post eerst op om afbeeldingen toe te voegen.</em>');
                                }

                                $images = is_array($record->images) ? $record->images : [];
                                if (empty($images) && $record->image_path) {
                                    $images = [$record->image_path];
                                }

                                if (empty($images)) {
                                    return new HtmlString('<em>Nog geen afbeeldingen. Klik op "Upload afbeelding" of "Genereer afbeelding met AI" hierboven.</em>');
                                }

                                $count = count($images);

                                $gridCls = 'grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5';
                                $cardCls = 'flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800';
                                $thumbCls = 'block overflow-hidden rounded-lg ring-1 ring-gray-200 dark:ring-gray-700';
                                $imgCls = 'aspect-square w-full object-cover transition-transform duration-200 hover:scale-105';
                                $btnRowCls = 'flex gap-1 text-xs';
                                $btnNeutralCls = 'flex-1 rounded-md bg-gray-100 px-2 py-1 text-center text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600';
                                $btnDisabledCls = 'flex-1 select-none rounded-md bg-gray-50 px-2 py-1 text-center text-gray-300 dark:bg-gray-900/50 dark:text-gray-600';
                                $btnDangerCls = 'flex-1 rounded-md bg-red-100 px-2 py-1 text-center text-red-800 transition hover:bg-red-200 dark:bg-red-900/30 dark:text-red-200 dark:hover:bg-red-900/50';

                                $html = '<div class="'.$gridCls.'">';

                                foreach ($images as $i => $img) {
                                    if (! is_string($img) || ! $img) {
                                        continue;
                                    }
                                    $url = static::imageUrl($img);
                                    $safe = e($url);

                                    $html .= '<div class="'.$cardCls.'">';

                                    $html .= '<a href="'.$safe.'" target="_blank" rel="noopener" class="'.$thumbCls.'">';
                                    $html .= '<img src="'.$safe.'" alt="" class="'.$imgCls.'" />';
                                    $html .= '</a>';

                                    $html .= '<div class="'.$btnRowCls.'">';
                                    $html .= '<a href="'.$safe.'" target="_blank" rel="noopener" class="'.$btnNeutralCls.'">Open ↗</a>';
                                    $html .= '<a href="'.$safe.'" download class="'.$btnNeutralCls.'">Download ⬇</a>';
                                    $html .= '</div>';

                                    $html .= '<div class="'.$btnRowCls.'">';
                                    if ($i > 0) {
                                        $html .= '<button type="button" wire:click="moveImage('.$i.', '.($i - 1).')" class="'.$btnNeutralCls.'" title="Naar links">←</button>';
                                    } else {
                                        $html .= '<span class="'.$btnDisabledCls.'">←</span>';
                                    }
                                    if ($i < $count - 1) {
                                        $html .= '<button type="button" wire:click="moveImage('.$i.', '.($i + 1).')" class="'.$btnNeutralCls.'" title="Naar rechts">→</button>';
                                    } else {
                                        $html .= '<span class="'.$btnDisabledCls.'">→</span>';
                                    }
                                    $html .= '<button type="button" wire:click="deleteImage('.$i.')" wire:confirm="Weet je zeker dat je deze afbeelding wilt verwijderen?" class="'.$btnDangerCls.'" title="Verwijderen">×</button>';
                                    $html .= '</div>';

                                    $html .= '</div>';
                                }

                                $html .= '</div>';

                                return new HtmlString($html);
                            })
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
                    ->getStateUsing(function (SocialPost $record): ?string {
                        $first = (is_array($record->images) && ! empty($record->images))
                            ? $record->images[0]
                            : $record->image_path;

                        return static::imageUrl($first);
                    }),
                TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => $state ? config("dashed-marketing.types.{$state}.label", $state) : '-')
                    ->badge()
                    ->sortable(),
                TextColumn::make('channels')
                    ->label('Kanalen')
                    ->getStateUsing(function (SocialPost $record): string {
                        $raw = $record->channels;
                        $channels = is_array($raw)
                            ? $raw
                            : (is_string($raw) ? (json_decode($raw, true) ?: []) : []);

                        if (empty($channels)) {
                            return '-';
                        }

                        return collect($channels)
                            ->map(fn ($c) => static::resolveChannelLabel($c))
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

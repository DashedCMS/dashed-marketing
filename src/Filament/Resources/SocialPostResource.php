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
                            ->helperText('Upload nieuwe afbeeldingen via "Upload afbeelding" of laat AI ze genereren via "Genereer afbeelding met AI". Sleep nog niet ondersteund — gebruik de knoppen om volgorde te veranderen.')
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
                                $html = '<div style="display:flex;flex-wrap:wrap;gap:1rem;">';

                                foreach ($images as $i => $img) {
                                    if (! is_string($img) || ! $img) {
                                        continue;
                                    }
                                    $url = static::imageUrl($img);
                                    $safe = e($url);

                                    $html .= '<div style="display:flex;flex-direction:column;gap:.4rem;align-items:stretch;width:200px;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:.6rem;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.05);">';
                                    $html .= '<a href="'.$safe.'" target="_blank" rel="noopener" style="display:block;">';
                                    $html .= '<img src="'.$safe.'" alt="" style="width:100%;height:180px;object-fit:cover;border-radius:6px;" />';
                                    $html .= '</a>';

                                    $html .= '<div style="display:flex;gap:.3rem;flex-wrap:wrap;font-size:.75rem;">';
                                    $html .= '<a href="'.$safe.'" target="_blank" rel="noopener" style="flex:1;text-align:center;padding:.35rem;border-radius:5px;background:#f3f4f6;text-decoration:none;color:#111;">Open ↗</a>';
                                    $html .= '<a href="'.$safe.'" download style="flex:1;text-align:center;padding:.35rem;border-radius:5px;background:#f3f4f6;text-decoration:none;color:#111;">Download ⬇</a>';
                                    $html .= '</div>';

                                    $html .= '<div style="display:flex;gap:.3rem;flex-wrap:wrap;font-size:.75rem;">';
                                    if ($i > 0) {
                                        $html .= '<button type="button" wire:click="moveImage('.$i.', '.($i - 1).')" style="flex:1;padding:.35rem;border-radius:5px;background:#e5e7eb;border:0;cursor:pointer;">↑</button>';
                                    } else {
                                        $html .= '<span style="flex:1;padding:.35rem;border-radius:5px;background:#f9fafb;color:#9ca3af;text-align:center;">↑</span>';
                                    }
                                    if ($i < $count - 1) {
                                        $html .= '<button type="button" wire:click="moveImage('.$i.', '.($i + 1).')" style="flex:1;padding:.35rem;border-radius:5px;background:#e5e7eb;border:0;cursor:pointer;">↓</button>';
                                    } else {
                                        $html .= '<span style="flex:1;padding:.35rem;border-radius:5px;background:#f9fafb;color:#9ca3af;text-align:center;">↓</span>';
                                    }
                                    $html .= '<button type="button" wire:click="deleteImage('.$i.')" wire:confirm="Weet je zeker dat je deze afbeelding wilt verwijderen?" style="flex:1;padding:.35rem;border-radius:5px;background:#fee2e2;color:#991b1b;border:0;cursor:pointer;">×</button>';
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

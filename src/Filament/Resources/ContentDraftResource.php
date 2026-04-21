<?php

namespace Dashed\DashedMarketing\Filament\Resources;

use BackedEnum;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\CreateContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\EditContentDraft;
use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages\ListContentDrafts;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Dashed\DashedMarketing\Jobs\RegenerateSectionHeadingJob;
use Dashed\DashedMarketing\Models\ContentDraft;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ContentDraftResource extends Resource
{
    protected static ?string $model = ContentDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Content concepten';

    protected static ?string $label = 'Content concept';

    protected static ?string $pluralLabel = 'Content concepten';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Algemeen')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Titel (H1)')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if (empty($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(255),
                    Select::make('subject_type')
                        ->label('Target type')
                        ->options(function () {
                            $options = [];
                            try {
                                foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                    $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                    $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                    if ($class) {
                                        $options[$class] = $name;
                                    }
                                }
                            } catch (\Throwable) {
                                //
                            }

                            return $options;
                        })
                        ->nullable()
                        ->live(),
                    Select::make('subject_id')
                        ->label('Target record')
                        ->placeholder('Nieuw record aanmaken')
                        ->options(function ($get) {
                            $class = $get('subject_type');
                            if (! $class || ! class_exists($class)) {
                                return [];
                            }
                            try {
                                return $class::query()->limit(50)->get()->mapWithKeys(function ($m) {
                                    $name = $m->name ?? $m->title ?? 'Record #'.$m->getKey();
                                    if (is_array($name)) {
                                        $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$m->getKey());
                                    }

                                    return [$m->getKey() => $name];
                                })->all();
                            } catch (\Throwable) {
                                return [];
                            }
                        })
                        ->nullable(),
                    Select::make('locale')
                        ->label('Taal')
                        ->options([
                            'nl' => 'Nederlands',
                            'en' => 'Engels',
                            'de' => 'Duits',
                            'fr' => 'Frans',
                        ])
                        ->required()
                        ->default('nl'),
                    Select::make('status')
                        ->label('Status')
                        ->options([
                            'concept' => 'Concept',
                            'pending' => 'In wachtrij',
                            'planning' => 'Planning...',
                            'writing' => 'Schrijven...',
                            'ready' => 'Klaar',
                            'applied' => 'Toegepast',
                            'failed' => 'Mislukt',
                        ])
                        ->default('concept')
                        ->disabled(),
                ]),

            Section::make('Zoekwoorden')
                ->schema([
                    Select::make('keywords')
                        ->label('Gekoppelde zoekwoorden')
                        ->multiple()
                        ->relationship('keywords', 'keyword')
                        ->preload()
                        ->searchable()
                        ->getOptionLabelFromRecordUsing(function ($record) {
                            $volume = $record->volume_exact ? $record->volume_exact.'/mnd' : ($record->volume_indication ?? '');

                            return "{$record->keyword}".($volume ? " · {$volume}" : '').' · '.$record->type;
                        })
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

            Section::make('Structuur en inhoud')
                ->schema([
                    Repeater::make('h2_sections')
                        ->label('')
                        ->schema([
                            TextInput::make('heading')->label('H2 titel')->required(),
                            Textarea::make('intent')->label('Waar gaat deze sectie over')->rows(2),
                            RichEditor::make('body')
                                ->label('Inhoud')
                                ->toolbarButtons(['bold', 'italic', 'link', 'orderedList', 'bulletList', 'undo'])
                                ->columnSpanFull(),
                        ])
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => $state['heading'] ?? 'Nieuwe sectie')
                        ->addable()
                        ->deletable()
                        ->extraItemActions([
                            Action::make('regenerate_heading')
                                ->label('Herschrijf heading')
                                ->icon('heroicon-o-arrow-path')
                                ->color('gray')
                                ->action(function (array $arguments, $livewire) {
                                    $uuid = $arguments['item'] ?? null;
                                    if (! $uuid) {
                                        return;
                                    }
                                    $state = $livewire->data['h2_sections'][$uuid] ?? null;
                                    if (! $state || empty($state['id'])) {
                                        Notification::make()
                                            ->title('Sla eerst op voor je een bestaande sectie regenereert')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                    RegenerateSectionHeadingJob::dispatchSync($livewire->record->id, $state['id']);
                                    $livewire->record->refresh();
                                    $livewire->fillForm();
                                    Notification::make()->title('Heading vernieuwd')->success()->send();
                                }),
                            Action::make('generate_body')
                                ->label('Genereer inhoud')
                                ->icon('heroicon-o-sparkles')
                                ->color('primary')
                                ->action(function (array $arguments, $livewire) {
                                    $uuid = $arguments['item'] ?? null;
                                    if (! $uuid) {
                                        return;
                                    }
                                    $state = $livewire->data['h2_sections'][$uuid] ?? null;
                                    if (! $state || empty($state['id'])) {
                                        Notification::make()
                                            ->title('Sla eerst op voor je inhoud genereert')
                                            ->warning()
                                            ->send();

                                        return;
                                    }
                                    GenerateSectionBodyJob::dispatch($livewire->record->id, $state['id']);
                                    Notification::make()
                                        ->title('Inhoud wordt gegenereerd')
                                        ->body('Ververs de pagina over een moment.')
                                        ->success()
                                        ->send();
                                }),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('Keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('locale')
                    ->label('Taal'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record->status_label)
                    ->color(fn ($record) => $record->status_color),
                TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'In wachtrij',
                        'planning' => 'Planning...',
                        'writing' => 'Schrijven...',
                        'ready' => 'Klaar',
                        'applied' => 'Toegepast',
                        'failed' => 'Mislukt',
                    ]),
                SelectFilter::make('locale')
                    ->label('Taal')
                    ->options([
                        'nl' => 'Nederlands',
                        'en' => 'Engels',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => ListContentDrafts::route('/'),
            'create' => CreateContentDraft::route('/create'),
            'edit' => EditContentDraft::route('/{record}/edit'),
        ];
    }
}

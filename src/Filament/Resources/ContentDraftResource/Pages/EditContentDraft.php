<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Jobs\GenerateDraftFaqsJob;
use Dashed\DashedMarketing\Jobs\GenerateDraftMetaJob;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Dashed\DashedMarketing\Services\ContentDraftPublisher;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class EditContentDraft extends EditRecord
{
    protected static string $resource = ContentDraftResource::class;

    public function pollDraft(): void
    {
        // Cheap status-only query - avoids hydrating relations on every poll.
        // Livewire serialises requests per-component, so keeping this short
        // lets user clicks (e.g. opening modals) slip between polls instead
        // of queueing behind a full record refresh + form re-fill.
        $freshStatus = DB::table('dashed__content_drafts')
            ->where('id', $this->record->getKey())
            ->value('status');

        if ($freshStatus === $this->record->status) {
            return;
        }

        $this->record->refresh();
        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_all_bodies')
                ->label('Genereer alle inhoud')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->schema([
                    Toggle::make('overwrite')
                        ->label('Overschrijf al gevulde secties')
                        ->helperText('Uit: alleen lege secties worden gevuld.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $overwrite = (bool) ($data['overwrite'] ?? false);
                    $sections = $this->record->sections()->orderBy('sort_order')->get();

                    $jobs = [];
                    $skipped = 0;

                    foreach ($sections as $section) {
                        if (! $overwrite && ! empty($section->body)) {
                            $skipped++;

                            continue;
                        }

                        $jobs[] = new GenerateSectionBodyJob($section->id);
                    }

                    if (! empty($jobs)) {
                        $jobs[] = new GenerateDraftFaqsJob($this->record->id);
                        $jobs[] = new GenerateDraftMetaJob($this->record->id, overwrite: $overwrite);
                        $this->record->update(['status' => 'writing']);
                        Bus::chain($jobs)->dispatch();
                        $this->fillForm();
                    }

                    $queued = count($jobs);

                    Notification::make()
                        ->title("{$queued} secties worden één voor één gegenereerd, {$skipped} overgeslagen")
                        ->body('Deze pagina ververst automatisch zodra de eerste body binnen is.')
                        ->success()
                        ->send();
                }),
            Action::make('generate_meta')
                ->label('Genereer SEO meta')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->schema([
                    Toggle::make('overwrite')
                        ->label('Overschrijf bestaande meta')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    GenerateDraftMetaJob::dispatch(
                        $this->record->id,
                        overwrite: (bool) ($data['overwrite'] ?? true),
                    );

                    Notification::make()
                        ->title('Meta title en description worden gegenereerd')
                        ->body('Ververs de pagina over een moment.')
                        ->success()
                        ->send();
                }),
            Action::make('resync')
                ->label(fn () => 'Opnieuw synchroniseren naar '.class_basename($this->record->subject_type ?? ''))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => $this->record->subject_type && $this->record->subject_id)
                ->modalDescription('De gekoppelde record wordt overschreven met de huidige draft-inhoud. Laat een veld leeg om dat bloktype niet mee te publiceren.')
                ->schema(self::blockChoiceSchema())
                ->action(function (array $data) {
                    $draft = $this->record;

                    if (! $draft->subject_type || ! $draft->subject_id) {
                        Notification::make()->title('Geen gekoppeld record')->danger()->send();

                        return;
                    }

                    $class = $draft->subject_type;
                    $target = $class::find($draft->subject_id);
                    $recreated = false;

                    if (! $target) {
                        if (! class_exists($class)) {
                            Notification::make()->title('Onbekend target type')->danger()->send();

                            return;
                        }

                        $locale = $draft->locale ?? 'nl';
                        $target = new $class;
                        $target->setTranslation('name', $locale, (string) $draft->name);
                        $target->setTranslation('slug', $locale, (string) $draft->slug);
                        $target->save();

                        $draft->subject_id = $target->getKey();
                        $recreated = true;
                    }

                    app(ContentDraftPublisher::class)->publish(
                        $draft,
                        $target,
                        locale: null,
                        choices: self::choicesFromData($data),
                    );

                    $draft->update([
                        'subject_id' => $target->getKey(),
                        'applied_at' => now(),
                        'applied_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title($recreated
                            ? 'Oude record was verwijderd - nieuwe '.class_basename($class).' aangemaakt en gevuld'
                            : 'Gesynchroniseerd naar '.class_basename($class))
                        ->success()
                        ->send();
                }),

            Action::make('publish')
                ->label(fn () => $this->record->subject_id ? 'Naar ander record publiceren' : 'Publiceer')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->visible(fn () => $this->record->status === 'ready')
                ->schema([
                    Select::make('target_type')
                        ->label('Target type')
                        ->options(function () {
                            $options = [];
                            try {
                                foreach ((array) cms()->builder('routeModels') as $key => $entry) {
                                    $name = is_array($entry) ? ($entry['name'] ?? $key) : $key;
                                    $options[$key] = $name;
                                }
                            } catch (\Throwable) {
                                //
                            }

                            return $options;
                        })
                        ->required()
                        ->live(),
                    Select::make('target_id')
                        ->label('Bestaand record bijwerken')
                        ->placeholder('Nieuw record aanmaken')
                        ->searchable()
                        ->preload()
                        ->getSearchResultsUsing(fn (string $search, $get) => ContentDraftResource::searchTargetRecords(self::resolveTargetClass($get('target_type')), $search))
                        ->getOptionLabelUsing(function ($value, $get) {
                            $class = self::resolveTargetClass($get('target_type'));
                            if (! $class || ! $value) {
                                return null;
                            }

                            $record = $class::find($value);

                            return $record ? self::recordLabel($record) : null;
                        })
                        ->options(fn ($get) => ContentDraftResource::searchTargetRecords(self::resolveTargetClass($get('target_type'))))
                        ->nullable(),
                    ...self::blockChoiceSchema(),
                ])
                ->action(function (array $data) {
                    $typeKey = $data['target_type'];
                    $entry = cms()->builder('routeModels')[$typeKey] ?? null;
                    $class = is_array($entry) ? ($entry['class'] ?? null) : null;

                    if (! $class || ! class_exists($class)) {
                        Notification::make()->title('Onbekend target type')->danger()->send();

                        return;
                    }

                    $draft = $this->record;
                    $locale = $draft->locale ?? 'nl';

                    if (! empty($data['target_id'])) {
                        $record = $class::findOrFail($data['target_id']);
                    } else {
                        $record = new $class;
                        $record->setTranslation('name', $locale, (string) $draft->name);
                        $record->setTranslation('slug', $locale, (string) $draft->slug);
                        $record->save();
                    }

                    app(ContentDraftPublisher::class)->publish(
                        $draft,
                        $record,
                        $locale,
                        self::choicesFromData($data),
                    );

                    $draft->update([
                        'subject_type' => $class,
                        'subject_id' => $record->getKey(),
                        'status' => 'applied',
                        'applied_at' => now(),
                        'applied_by' => auth()->id(),
                    ]);

                    Notification::make()
                        ->title('Gepubliceerd naar '.class_basename($class))
                        ->success()
                        ->send();
                }),
            Action::make('reject')
                ->label('Reject draft')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    $this->record->update(['status' => 'failed']);
                    $this->redirect(ContentDraftResource::getUrl('index'));
                }),
        ];
    }

    private static function resolveTargetClass(?string $typeKey): ?string
    {
        if (! $typeKey) {
            return null;
        }

        try {
            $entry = cms()->builder('routeModels')[$typeKey] ?? null;
            $class = is_array($entry) ? ($entry['class'] ?? null) : null;

            return ($class && class_exists($class)) ? $class : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function recordLabel($record): string
    {
        $name = $record->name ?? $record->title ?? 'Record #'.$record->getKey();
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #'.$record->getKey());
        }

        return (string) $name;
    }

    /**
     * @return array<int, Select>
     */
    private static function blockChoiceSchema(): array
    {
        $options = ContentDraftPublisher::availableBlockOptions();
        $defaults = ContentDraftPublisher::defaultChoices();

        return [
            Select::make('publish_header_block')
                ->label('Header blok')
                ->helperText('Leeg = geen header blok toevoegen.')
                ->options($options)
                ->searchable()
                ->nullable()
                ->default($defaults['header']),
            Select::make('publish_content_block')
                ->label('Content blok per sectie')
                ->helperText('Leeg = sectie-bodies worden niet mee gepubliceerd.')
                ->options($options)
                ->searchable()
                ->nullable()
                ->default($defaults['content']),
            Select::make('publish_faq_block')
                ->label('FAQ blok')
                ->helperText('Leeg = FAQs worden niet mee gepubliceerd.')
                ->options($options)
                ->searchable()
                ->nullable()
                ->default($defaults['faq']),
        ];
    }

    /**
     * @return array{header: ?string, content: ?string, faq: ?string}
     */
    private static function choicesFromData(array $data): array
    {
        return [
            'header' => $data['publish_header_block'] ?? null,
            'content' => $data['publish_content_block'] ?? null,
            'faq' => $data['publish_faq_block'] ?? null,
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Dashed\DashedMarketing\Jobs\GenerateSectionBodyJob;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContentDraft extends EditRecord
{
    protected static string $resource = ContentDraftResource::class;

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
                    $sections = (array) ($this->record->h2_sections ?? []);

                    $queued = 0;
                    $skipped = 0;

                    foreach ($sections as $section) {
                        if (empty($section['id'] ?? null)) {
                            continue;
                        }
                        if (! $overwrite && ! empty($section['body'] ?? null)) {
                            $skipped++;

                            continue;
                        }

                        GenerateSectionBodyJob::dispatch($this->record->id, $section['id']);
                        $queued++;
                    }

                    if ($queued > 0) {
                        $this->record->update(['status' => 'writing']);
                        $this->fillForm();
                    }

                    Notification::make()
                        ->title("{$queued} secties worden gegenereerd op de achtergrond, {$skipped} overgeslagen")
                        ->body('Ververs de pagina over een moment om de bodies te zien.')
                        ->success()
                        ->send();
                }),
            Action::make('publish')
                ->label('Publiceer')
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
                        ->getSearchResultsUsing(function (string $search, $get) {
                            $class = self::resolveTargetClass($get('target_type'));
                            if (! $class) {
                                return [];
                            }

                            $locale = app()->getLocale();

                            return $class::query()
                                ->where(function ($q) use ($search, $locale) {
                                    $q->where('name', 'like', "%{$search}%")
                                        ->orWhere('name->' . $locale, 'like', "%{$search}%");
                                })
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($m) => [$m->getKey() => self::recordLabel($m)])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value, $get) {
                            $class = self::resolveTargetClass($get('target_type'));
                            if (! $class || ! $value) {
                                return null;
                            }

                            $record = $class::find($value);

                            return $record ? self::recordLabel($record) : null;
                        })
                        ->options(function ($get) {
                            $class = self::resolveTargetClass($get('target_type'));
                            if (! $class) {
                                return [];
                            }

                            return $class::query()
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($m) => [$m->getKey() => self::recordLabel($m)])
                                ->all();
                        })
                        ->nullable(),
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
                    $bodyHtml = collect($draft->h2_sections ?? [])
                        ->map(fn ($s) => '<h2>'.e($s['heading'] ?? '').'</h2>'.($s['body'] ?? ''))
                        ->implode("\n");

                    $nameValue = [$locale => $draft->name];
                    $slugValue = [$locale => $draft->slug];
                    $contentValue = [$locale => $bodyHtml];

                    if (! empty($data['target_id'])) {
                        $record = $class::findOrFail($data['target_id']);
                        $record->name = array_merge((array) ($record->name ?: []), $nameValue);
                        $record->slug = array_merge((array) ($record->slug ?: []), $slugValue);
                        $record->content = array_merge((array) ($record->content ?: []), $contentValue);
                        $record->save();
                    } else {
                        $record = new $class;
                        $record->name = $nameValue;
                        $record->slug = $slugValue;
                        $record->content = $contentValue;
                        $record->save();
                    }

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
        $name = $record->name ?? $record->title ?? 'Record #' . $record->getKey();
        if (is_array($name)) {
            $name = $name[app()->getLocale()] ?? reset($name) ?? ('Record #' . $record->getKey());
        }

        return (string) $name;
    }

}

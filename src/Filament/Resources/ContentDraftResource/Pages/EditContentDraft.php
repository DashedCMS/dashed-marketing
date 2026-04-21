<?php

namespace Dashed\DashedMarketing\Filament\Resources\ContentDraftResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\ContentDraftResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContentDraft extends EditRecord
{
    protected static string $resource = ContentDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                        ->options(function ($get) {
                            $type = $get('target_type');
                            if (! $type) {
                                return [];
                            }
                            try {
                                $entry = cms()->builder('routeModels')[$type] ?? null;
                                $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                                if (! $class || ! class_exists($class)) {
                                    return [];
                                }

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
}

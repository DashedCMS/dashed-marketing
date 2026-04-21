<?php

namespace Dashed\DashedMarketing\Filament\Pages\Settings;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedMarketing\Services\ContentDraftPublisher;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class ContentPublishSettingsPage extends Page implements HasSchemas
{
    use HasSettingsPermission;
    use InteractsWithSchemas;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Content publicatie instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'marketing_publish_header_block' => Customsetting::get('marketing_publish_header_block'),
            'marketing_publish_content_block' => Customsetting::get('marketing_publish_content_block'),
            'marketing_publish_faq_block' => Customsetting::get('marketing_publish_faq_block'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $options = ContentDraftPublisher::availableBlockOptions();

        return $schema->schema([
            Section::make('Standaard blokken bij publiceren')
                ->description('Deze keuzes worden vooringevuld in de publiceer-popup op elke content draft. Laat leeg om standaard geen blok van die soort toe te voegen.')
                ->schema([
                    Select::make('marketing_publish_header_block')
                        ->label('Header blok')
                        ->helperText('Bovenaan het artikel komt dit blok met de titel.')
                        ->options($options)
                        ->searchable()
                        ->nullable(),
                    Select::make('marketing_publish_content_block')
                        ->label('Content blok')
                        ->helperText('Eén blok per sectie met de heading en body.')
                        ->options($options)
                        ->searchable()
                        ->nullable(),
                    Select::make('marketing_publish_faq_block')
                        ->label('FAQ blok')
                        ->helperText('Aan het eind komt dit blok met de gegenereerde FAQs.')
                        ->options($options)
                        ->searchable()
                        ->nullable(),
                ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            Customsetting::set('marketing_publish_header_block', $formData['marketing_publish_header_block'] ?? null, $site['id']);
            Customsetting::set('marketing_publish_content_block', $formData['marketing_publish_content_block'] ?? null, $site['id']);
            Customsetting::set('marketing_publish_faq_block', $formData['marketing_publish_faq_block'] ?? null, $site['id']);
        }

        Notification::make()
            ->title('Content publicatie instellingen opgeslagen')
            ->success()
            ->send();
    }
}

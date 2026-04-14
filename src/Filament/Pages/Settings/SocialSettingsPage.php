<?php

namespace Dashed\DashedMarketing\Filament\Pages\Settings;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Dashed\DashedMarketing\Jobs\GenerateSocialContextJob;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class SocialSettingsPage extends Page implements HasSchemas
{
    use HasSettingsPermission;
    use InteractsWithSchemas;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Social media instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $channels = Customsetting::get('social_channels')
            ?: Customsetting::get('social_platforms');

        $this->form->fill([
            'social_channels' => $channels ? (is_array($channels) ? $channels : json_decode($channels, true)) : [],
            'social_target_audience' => Customsetting::get('social_target_audience'),
            'social_usps' => Customsetting::get('social_usps'),
            'social_fal_api_key' => Customsetting::get('social_fal_api_key'),
            'social_notification_email' => Customsetting::get('social_notification_email'),
            'social_notify_due' => (bool) Customsetting::get('social_notify_due', null, true),
            'social_notify_missed' => (bool) Customsetting::get('social_notify_missed', null, true),
            'social_notify_weekly_gaps' => (bool) Customsetting::get('social_notify_weekly_gaps', null, true),
            'social_notify_holidays' => (bool) Customsetting::get('social_notify_holidays', null, true),
        ]);
    }

    public function generateSocialContextAction(): Action
    {
        $hasProvider = Ai::hasProvider();

        return Action::make('generateSocialContext')
            ->label('AI context genereren')
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->disabled(! $hasProvider)
            ->tooltip($hasProvider ? null : 'Configureer eerst een AI provider in AI Settings.')
            ->requiresConfirmation()
            ->modalHeading('AI context genereren?')
            ->modalDescription('Dit dispatcht een job per site. Lege velden worden automatisch ingevuld op basis van je website content. Bestaande waarden blijven staan.')
            ->modalSubmitActionLabel('Genereer')
            ->action(function () {
                $sites = Sites::getSites();

                foreach ($sites as $site) {
                    GenerateSocialContextJob::dispatch($site['id'], auth()->id());
                }

                Notification::make()
                    ->title('AI context generatie gestart')
                    ->body('Job gestart voor '.count($sites).' site(s). Je krijgt een melding zodra elke site klaar is.')
                    ->success()
                    ->send();
            });
    }

    public function form(Schema $schema): Schema
    {
        $platformOptions = array_map(
            fn ($p) => $p['label'],
            config('dashed-marketing.platforms', [])
        );

        $channelOptions = [];
        foreach (config('dashed-marketing.channels', []) as $key => $channel) {
            $accepted = $channel['accepted_types'] ?? [];
            $channelOptions[$key] = $channel['label'].' ('.implode(', ', $accepted).')';
        }

        return $schema->schema([
            Section::make('Actieve kanalen')
                ->description('Vink aan welke kanalen je daadwerkelijk gebruikt. Alleen aangevinkte kanalen worden meegegeven aan de AI als context en verschijnen als selectie-optie bij nieuwe posts.')
                ->schema([
                    CheckboxList::make('social_channels')
                        ->label('Kanalen')
                        ->options($channelOptions)
                        ->columns(2),
                ]),

            Section::make('AI context')
                ->schema([
                    Actions::make([
                        $this->generateSocialContextAction(),
                    ]),
                    Textarea::make('social_target_audience')
                        ->label('Doelgroep')
                        ->helperText('Beschrijf je doelgroep voor social media posts.')
                        ->rows(3),
                    Textarea::make('social_usps')
                        ->label('Unique Selling Points')
                        ->helperText('De belangrijkste USPs van je product/dienst.')
                        ->rows(3),
                ]),

            Section::make('Afbeelding generatie (FAL.ai)')
                ->schema([
                    TextInput::make('social_fal_api_key')
                        ->label('FAL.ai API sleutel')
                        ->password()
                        ->revealable()
                        ->placeholder('fal-...')
                        ->helperText('Je vindt je API sleutel op fal.ai → API Keys.'),
                ]),

            Section::make('Meldingen')
                ->schema([
                    TextInput::make('social_notification_email')
                        ->label('Notificatie e-mailadres')
                        ->email()
                        ->helperText('Laat leeg om het standaard beheerder e-mailadres te gebruiken.'),
                    Toggle::make('social_notify_due')
                        ->label('Dagelijkse herinnering voor posts die vandaag geplaatst moeten worden'),
                    Toggle::make('social_notify_missed')
                        ->label('Melding bij gemiste posts'),
                    Toggle::make('social_notify_weekly_gaps')
                        ->label('Wekelijkse melding bij lege slots'),
                    Toggle::make('social_notify_holidays')
                        ->label('Herinnering bij aankomende feestdagen'),
                ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            Customsetting::set('social_channels', json_encode($formData['social_channels'] ?? []), $site['id']);
            Customsetting::set('social_target_audience', $formData['social_target_audience'] ?? null, $site['id']);
            Customsetting::set('social_usps', $formData['social_usps'] ?? null, $site['id']);
            Customsetting::set('social_fal_api_key', $formData['social_fal_api_key'] ?? null, $site['id']);
            Customsetting::set('social_notification_email', $formData['social_notification_email'] ?? null, $site['id']);
            Customsetting::set('social_notify_due', (int) ($formData['social_notify_due'] ?? true), $site['id']);
            Customsetting::set('social_notify_missed', (int) ($formData['social_notify_missed'] ?? true), $site['id']);
            Customsetting::set('social_notify_weekly_gaps', (int) ($formData['social_notify_weekly_gaps'] ?? true), $site['id']);
            Customsetting::set('social_notify_holidays', (int) ($formData['social_notify_holidays'] ?? true), $site['id']);
        }

        Notification::make()
            ->title('Social media instellingen opgeslagen')
            ->success()
            ->send();

        redirect(SocialSettingsPage::getUrl());
    }
}

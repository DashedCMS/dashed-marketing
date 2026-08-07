<?php

namespace Dashed\DashedMarketing\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Forms\Components\CheckboxList;
use Dashed\DashedMarketing\Models\SocialChannel;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedMarketing\Jobs\GenerateSocialContextJob;
use Dashed\DashedMarketing\Managers\PublishingAdapterRegistry;

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
            'social_publishing_adapter' => Customsetting::get('social_publishing_adapter') ?: 'manual',
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
            ->label(__('AI context genereren'))
            ->icon('heroicon-o-sparkles')
            ->color('primary')
            ->disabled(! $hasProvider)
            ->tooltip($hasProvider ? null : __('Configureer eerst een AI provider in AI Settings.'))
            ->requiresConfirmation()
            ->modalHeading(__('AI context genereren?'))
            ->modalDescription(__('Dit dispatcht een job per site. Lege velden worden automatisch ingevuld op basis van je website content. Bestaande waarden blijven staan.'))
            ->modalSubmitActionLabel(__('Genereer'))
            ->action(function () {
                $sites = Sites::getSites();

                foreach ($sites as $site) {
                    GenerateSocialContextJob::dispatch($site['id'], auth()->id());
                }

                Notification::make()
                    ->title(__('AI context generatie gestart'))
                    ->body(__('Job gestart voor :aantal site(s). Je krijgt een melding zodra elke site klaar is.', ['aantal' => count($sites)]))
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

        $channelOptions = SocialChannel::query()
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function (SocialChannel $ch): array {
                $accepted = $ch->accepted_types ?? [];

                return [$ch->slug => $ch->name.' ('.implode(', ', $accepted).')'];
            })
            ->toArray();

        return $schema->schema([
            Section::make(__('Publicatie adapter'))
                ->schema([
                    Select::make('social_publishing_adapter')
                        ->label(__('Publicatie adapter'))
                        ->options(PublishingAdapterRegistry::all())
                        ->default('manual')
                        ->required()
                        ->helperText(__('Selecteer hoe social posts gepubliceerd worden. \'Handmatig\' markeert alleen als gepost zonder externe API-call.')),
                ]),

            Section::make(__('Actieve kanalen'))
                ->description(__('Vink aan welke kanalen je daadwerkelijk gebruikt. Alleen aangevinkte kanalen worden meegegeven aan de AI als context en verschijnen als selectie-optie bij nieuwe posts.'))
                ->schema([
                    CheckboxList::make('social_channels')
                        ->label(__('Kanalen'))
                        ->options($channelOptions)
                        ->columns(2),
                ]),

            Section::make(__('AI context'))
                ->schema([
                    Actions::make([
                        $this->generateSocialContextAction(),
                    ]),
                    Textarea::make('social_target_audience')
                        ->label(__('Doelgroep'))
                        ->helperText(__('Beschrijf je doelgroep voor social media posts.'))
                        ->rows(3),
                    Textarea::make('social_usps')
                        ->label(__('Unique Selling Points'))
                        ->helperText(__('De belangrijkste USPs van je product/dienst.'))
                        ->rows(3),
                ]),

            Section::make(__('Meldingen'))
                ->schema([
                    TextInput::make('social_notification_email')
                        ->label(__('Notificatie e-mailadres'))
                        ->email()
                        ->helperText(__('Laat leeg om het standaard beheerder e-mailadres te gebruiken.')),
                    Toggle::make('social_notify_due')
                        ->label(__('Dagelijkse herinnering voor posts die vandaag geplaatst moeten worden')),
                    Toggle::make('social_notify_missed')
                        ->label(__('Melding bij gemiste posts')),
                    Toggle::make('social_notify_weekly_gaps')
                        ->label(__('Wekelijkse melding bij lege slots')),
                    Toggle::make('social_notify_holidays')
                        ->label(__('Herinnering bij aankomende feestdagen')),
                ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            Customsetting::set('social_channels', json_encode($formData['social_channels'] ?? []), $site['id']);
            Customsetting::set('social_publishing_adapter', $formData['social_publishing_adapter'] ?? 'manual', $site['id']);
            Customsetting::set('social_target_audience', $formData['social_target_audience'] ?? null, $site['id']);
            Customsetting::set('social_usps', $formData['social_usps'] ?? null, $site['id']);
            Customsetting::set('social_notification_email', $formData['social_notification_email'] ?? null, $site['id']);
            Customsetting::set('social_notify_due', (int) ($formData['social_notify_due'] ?? true), $site['id']);
            Customsetting::set('social_notify_missed', (int) ($formData['social_notify_missed'] ?? true), $site['id']);
            Customsetting::set('social_notify_weekly_gaps', (int) ($formData['social_notify_weekly_gaps'] ?? true), $site['id']);
            Customsetting::set('social_notify_holidays', (int) ($formData['social_notify_holidays'] ?? true), $site['id']);
        }

        Notification::make()
            ->title(__('Social media instellingen opgeslagen'))
            ->success()
            ->send();

        redirect(SocialSettingsPage::getUrl());
    }
}

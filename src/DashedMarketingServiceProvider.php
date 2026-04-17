<?php

namespace Dashed\DashedMarketing;

use Spatie\LaravelPackageTools\Package;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Console\Scheduling\Schedule;
use Dashed\DashedMarketing\Facades\ContentTemplates;
use Dashed\DashedMarketing\Templates\ProductTemplate;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedMarketing\Contracts\PublishingAdapter;
use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Dashed\DashedMarketing\Adapters\ManualPublishAdapter;
use Dashed\DashedMarketing\Templates\BlogArticleTemplate;
use Dashed\DashedMarketing\Templates\LandingPageTemplate;
use Dashed\DashedMarketing\Commands\SocialNotifyDueCommand;
use Dashed\DashedMarketing\Commands\SocialWeeklyGapsCommand;
use Dashed\DashedMarketing\Contracts\KeywordResearchAdapter;
use Dashed\DashedMarketing\Managers\ContentTemplateRegistry;
use Dashed\DashedMarketing\Commands\SocialKeywordSyncCommand;
use Dashed\DashedMarketing\Templates\ProductCategoryTemplate;
use Dashed\DashedMarketing\Commands\SocialNotifyMissedCommand;
use Dashed\DashedMarketing\Managers\PublishingAdapterRegistry;
use Dashed\DashedMarketing\Commands\SocialCheckHolidaysCommand;
use Dashed\DashedMarketing\Commands\PublishDueSocialPostsCommand;
use Dashed\DashedMarketing\Observers\VisitableModelEmbeddingObserver;
use Dashed\DashedMarketing\Filament\Pages\Settings\SocialSettingsPage;

class DashedMarketingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-marketing';

    public function bootingPackage()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('social:publish-due')->everyMinute()->withoutOverlapping();
            $schedule->command('social:notify-due')->dailyAt('08:00');
            $schedule->command('social:notify-missed')->dailyAt('09:00');
            $schedule->command('social:check-holidays')->dailyAt('07:00');
            $schedule->command('social:weekly-gaps')->weeklyOn(1, '08:30'); // Monday
            $schedule->command('social:keyword-sync')->dailyAt('03:00');
        });

        cms()->builder('plugins', [
            new DashedMarketingPlugin(),
        ]);

        cms()->registerSettingsPage(
            SocialSettingsPage::class,
            'Social media',
            'share',
            'Social media platforms, AI context en notificaties'
        );

        PublishingAdapterRegistry::register('manual', ManualPublishAdapter::class, 'Handmatig');

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\ContentClusterResource::class,
            title: 'Content clusters',
            intro: 'Een content cluster bundelt gerelateerde onderwerpen rond een thema. Je koppelt er keywords en content concepten aan, zodat je in een oogopslag ziet welke stukken samen bij een thema horen. Ideaal om je content marketing planning overzichtelijk te houden.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe clusters aanmaken rond een thema of onderwerp.
- Keywords aan een cluster koppelen.
- Content concepten binnen een cluster plannen.
- De voortgang per cluster volgen.
- Clusters hernoemen of opschonen als een thema afgerond is.
MARKDOWN,
                ],
            ],
            tips: [
                'Begin elk cluster met een helder kernonderwerp voordat je keywords koppelt.',
                'Een cluster werkt sterker als de artikelen onderling naar elkaar verwijzen.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\ContentDraftResource::class,
            title: 'Content concepten',
            intro: 'Hier vind je AI-gegenereerde concept artikelen met een duidelijke opbouw in H2 secties. Elk concept doorloopt een cyclus van in afwachting, planning, schrijven, klaar en toegepast. Van elke wijziging wordt een snapshot bewaard zodat je altijd terug kunt naar een eerdere versie.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- AI laten voorstellen doen voor nieuwe artikelen.
- De status van een concept aanpassen tijdens het schrijfproces.
- Per sectie de tekst controleren en bijsturen.
- Terugvallen op een eerdere versie via de historie.
- Een afgerond concept toepassen als definitief artikel.
MARKDOWN,
                ],
                [
                    'heading' => 'Wanneer gebruik je dit?',
                    'body' => 'Gebruik dit scherm als je regelmatig nieuwe content publiceert en je productie wilt versnellen. De AI levert een stevige basis aan, waarna jij het verhaal persoonlijk maakt en publiceert.',
                ],
            ],
            tips: [
                'Loop ieder concept eerst volledig door voordat je het toepast.',
                'Pas de toon aan zodat het bij je merk past.',
                'Gebruik de historie als een wijziging toch niet goed uitpakt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\KeywordResource::class,
            title: 'Keywords',
            intro: 'Beheer de SEO keywords die de basis vormen van je content planning en vindbaarheid. Per keyword zie je het maandelijks zoekvolume, de moeilijkheid en de zoekintentie. Zo bepaal je snel welke termen kansrijk zijn om op in te zetten.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe keywords toevoegen aan je lijst.
- Zoekvolume, moeilijkheid en intentie per keyword bekijken.
- Keywords koppelen aan clusters of concepten.
- Kansrijke keywords markeren voor opvolging.
- Oude of irrelevante keywords opschonen.
MARKDOWN,
                ],
            ],
            tips: [
                'Focus op keywords met een lage moeilijkheid en een duidelijke intentie.',
                'Combineer brede en specifieke zoektermen voor een gezonde mix.',
                'Een lager zoekvolume met hoge intentie is vaak waardevoller dan een populair keyword.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SeoImprovementResource::class,
            title: 'SEO verbeteringen',
            intro: 'Hier zie je AI-gegenereerde verbetervoorstellen voor bestaande content. Per voorstel krijg je een heldere vergelijking tussen de huidige tekst en de voorgestelde versie. Je kunt elk voorstel accepteren, weigeren of later alsnog terugdraaien.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Voorgestelde SEO verbeteringen inzien per pagina of artikel.
- Een vergelijking bekijken tussen de huidige en de nieuwe tekst.
- Voorstellen accepteren en direct doorvoeren.
- Voorstellen weigeren als ze niet passen.
- Een eerder doorgevoerde verbetering terugdraaien.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat is er bijzonder?',
                    'body' => 'Deze module heeft een eigen reviewpagina met een diff-weergave en een veiligheidscheck die voorkomt dat twee mensen tegelijk hetzelfde voorstel bewerken.',
                ],
            ],
            tips: [
                'Beoordeel elk voorstel op toon en merkgevoel, niet alleen op SEO-winst.',
                'Draai een verbetering gerust terug als een voorstel toch niet werkt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SocialCampaignResource::class,
            title: 'Social campagnes',
            intro: 'Bundel je social posts onder een gezamenlijk doel in een campagne. Per campagne leg je een naam, beschrijving, looptijd, doelen en status vast. Zo hou je grip op welke posts bij welk verhaal horen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe campagnes starten met een duidelijke naam en looptijd.
- Doelen per campagne beschrijven.
- Social posts aan een campagne koppelen.
- De status van een campagne bijwerken.
- Afgeronde campagnes archiveren.
MARKDOWN,
                ],
            ],
            tips: [
                'Formuleer vooraf een concreet doel zoals extra verkeer of aanmeldingen.',
                'Spreid posts binnen een campagne over meerdere dagen.',
                'Sluit een campagne altijd af met een korte evaluatie.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SocialHolidayResource::class,
            title: 'Feestdagen',
            intro: 'Hou overzicht over land-specifieke feestdagen zodat je op tijd je social media content kunt voorbereiden. Per feestdag leg je een datum, een land en optioneel een herinnering met voorlooptijd vast.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Feestdagen toevoegen per land.
- Een herinnering instellen met een voorlooptijd die bij jou past.
- Onderscheid maken tussen nationale en internationale feestdagen.
- Feestdagen koppelen aan je content planning.
- Oude of niet relevante feestdagen verwijderen.
MARKDOWN,
                ],
            ],
            tips: [
                'Plan posts rond feestdagen minstens een week vooruit.',
                'Beperk je tot feestdagen die echt bij je doelgroep passen.',
                'Gebruik feestdagen als haakje voor een actie of aanbieding.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SocialIdeaResource::class,
            title: 'Social ideeen',
            intro: 'Een plek voor ruwe social media ideeen die je later verder uitwerkt. Per idee leg je een titel, notities, tags, een type en de kanalen vast. Zo verlies je geen enkel inzicht, ook al heb je op dat moment nog geen tijd om er echt iets mee te doen.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe ideeen snel vastleggen zodra ze opkomen.
- Notities en tags toevoegen om het idee later terug te vinden.
- Het type en de kanalen kiezen die bij het idee passen.
- Ideeen uitwerken tot volledige posts.
- Verouderde ideeen opschonen.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat is er bijzonder?',
                    'body' => 'Met de knop "Maak post" zet je een idee met behulp van AI direct om in drie kant en klare caption varianten. Zo kies je de versie die het beste past bij je toon en doelgroep.',
                ],
            ],
            tips: [
                'Leg een idee vast zodra het opkomt, hoe kort ook.',
                'Gebruik tags zodat je ideeen later makkelijk kunt filteren.',
                'Werk ideeen wekelijks uit zodat je inbox niet volloopt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SocialPillarResource::class,
            title: 'Social pijlers',
            intro: 'Pijlers zijn de kernthema\'s waar je social strategie op rust. Door elke post en elk idee aan een pijler te koppelen, zorg je voor een herkenbare mix en een duidelijk verhaal richting je volgers.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe pijlers definieren voor je strategie.
- Per pijler omschrijven waar die over gaat.
- Pijlers koppelen aan posts en ideeen.
- De balans tussen pijlers in de gaten houden.
- Pijlers aanpassen als je strategie verandert.
MARKDOWN,
                ],
            ],
            tips: [
                'Werk met drie tot vijf pijlers, meer wordt onoverzichtelijk.',
                'Kijk regelmatig of elke pijler genoeg aandacht krijgt.',
            ],
        );

        cms()->registerResourceDocs(
            resource: \Dashed\DashedMarketing\Filament\Resources\SocialPostResource::class,
            title: 'Social posts',
            intro: 'Hier beheer je AI-gegenereerde social media posts inclusief planning, kanalen, hashtags, beeld en prestaties. Per post kies je een type, zoals content, promotie, educatief, vermaak of engagement, en de kanalen waar de post verschijnt.',
            sections: [
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Nieuwe posts aanmaken met AI-ondersteuning.
- Een type en kanalen kiezen per post.
- Hashtags en beeldmateriaal toevoegen.
- Posts inplannen voor een specifieke datum en tijd.
- Performance data terugkijken van eerdere posts.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat is er bijzonder?',
                    'body' => 'Met de actie "Markeer als gepost" kun je een post in een klik afvinken zodra die live staat. Je kunt er optioneel een link naar de geplaatste post bij invullen zodat je hem later makkelijk terugvindt.',
                ],
            ],
            tips: [
                'Plan posts op momenten dat je doelgroep echt online is.',
                'Kies bewust per post welke kanalen passen, niet alles hoeft overal.',
                'Gebruik performance data om te leren wat werkt en wat niet.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedMarketing\Filament\Pages\SocialDashboardPage::class,
            title: 'Social dashboard',
            intro: 'Het strategie overzicht voor je social media. Hier zie je in een oogopslag hoeveel je post, of je content mix klopt met je targets en welke feestdagen eraan komen.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
Het dashboard bestaat uit drie onderdelen:

- **Vier stat-kaarten voor de huidige week** met het aantal geposte berichten, ingeplande berichten, posts die te laat zijn en concepten die nog wachten op afronding.
- **Pijler mix** met progress balken per content pijler, waarbij je het huidige percentage afzet tegen het streefpercentage. Zo zie je of je in balans zit of te veel van een soort post de lucht in gaat.
- **Aankomende feestdagen** voor de komende 30 dagen, handig om tijdig content voor te bereiden.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => 'Deze pagina is vooral een overzicht om te lezen en op basis daarvan te plannen. Je voert hier zelf geen acties uit op berichten. Gebruik de social kalender of de posts zelf om daadwerkelijk iets aan te passen.',
                ],
            ],
            tips: [
                'Check dit dashboard aan het begin van elke week. Zie je veel concepten of te late posts, maak dan eerst die slag voordat je nieuwe ideeen toevoegt.',
                'Als een pijler ver onder het target staat, plan dan bewust een paar posts voor dat onderwerp in.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedMarketing\Filament\Pages\SocialCalendarPage::class,
            title: 'Social kalender',
            intro: 'De maandkalender voor je social media planning. Hier zie je alle geplande en geposte berichten per dag en kun je ze slepen naar een andere datum.',
            sections: [
                [
                    'heading' => 'Wat zie je hier?',
                    'body' => <<<MARKDOWN
De kalender toont een hele maand in een raster:

- **Maand- en jaarnavigatie** om vooruit en terug te bladeren.
- **Dagvakken** met daarin de posts die op die dag staan.
- **Statuskleuren** per post: grijs voor concept, oranje voor review, blauw voor goedgekeurd, paars voor gepland, groen voor gepost en rood voor verlopen.
- **Platform en tijd** direct zichtbaar bij elke post.
MARKDOWN,
                ],
                [
                    'heading' => 'Wat kun je hier doen?',
                    'body' => <<<MARKDOWN
- Bladeren tussen maanden om ver vooruit te plannen of terug te kijken naar wat je hebt gepost.
- Een post naar een andere dag slepen om hem te herplannen zonder het bericht te openen.
- Op een post klikken om hem te bewerken, bijvoorbeeld om de tekst, media of het tijdstip aan te passen.
- In een oogopslag gaten in je planning zien en die opvullen.
MARKDOWN,
                ],
            ],
            tips: [
                'Gebruik de statuskleuren als checklist. Staat er nog veel grijs of oranje vlak voor de publicatiedatum, dan weet je dat er nog werk ligt.',
                'Sleep posts bij voorkeur binnen dezelfde week. Een goede ritme in je planning werkt beter dan losse pieken.',
            ],
        );

        cms()->registerSettingsDocs(
            page: \Dashed\DashedMarketing\Filament\Pages\Settings\SocialSettingsPage::class,
            title: 'Social media instellingen',
            intro: 'Hier bepaal je hoe de AI posts voor je social kanalen schrijft en welke beelden er bij gegenereerd worden. Je legt vast op welke kanalen je actief bent, voor wie je schrijft en wat de unieke punten van je merk zijn. Daarnaast stel je in wanneer je notificaties wil ontvangen over geplande, gemiste of ontbrekende posts.',
            sections: [
                [
                    'heading' => 'Wat kun je hier instellen?',
                    'body' => 'Je kiest de actieve social kanalen, beschrijft je doelgroep en USP\'s en koppelt FAL.ai voor automatische beeldgeneratie. Verder zet je de notificatie e-mail en de gewenste herinneringen aan of uit.',
                ],
                [
                    'heading' => 'Hoe vul je dit goed in?',
                    'body' => <<<MARKDOWN
1. Vink alleen de kanalen aan waar je daadwerkelijk posts naartoe wil sturen.
2. Schrijf de doelgroep alsof je hem aan een nieuwe collega uitlegt (leeftijd, interesses, toon die past).
3. Zet de belangrijkste 3 tot 5 USP\'s op een rij, kort en concreet.
4. Maak een account op [fal.ai](https://fal.ai), ga naar **API Keys** en plak je sleutel in het FAL veld.
5. Vul een notificatie e-mailadres in en kies welke meldingen je dagelijks of wekelijks wil ontvangen.
MARKDOWN,
                ],
            ],
            fields: [
                'Actieve social kanalen' => 'De social media kanalen waar je posts op publiceert. Alleen aangevinkte kanalen verschijnen in de planning en krijgen content toegewezen.',
                'Doelgroep' => 'Beschrijving van de mensen die je wil bereiken. De AI gebruikt dit voor de toon, woordkeuze en aansprekingsvorm in elke post.',
                'Unique Selling Points' => 'De kernpunten waarmee jouw merk of product zich onderscheidt. De AI verwerkt deze regelmatig in de teksten zodat je verhaal consistent blijft.',
                'FAL.ai API sleutel' => 'API sleutel van FAL.ai voor het automatisch genereren van beelden bij posts. Je vindt deze sleutel in je FAL.ai account onder API Keys, het formaat begint met fal_. Zonder geldige sleutel worden er geen beelden aangemaakt.',
                'Notificatie e-mailadres' => 'E-mailadres dat alle social meldingen ontvangt. Vul hier het adres in van de persoon die de planning beheert.',
                'Dagelijkse herinnering' => 'Aan betekent dat je elke ochtend een mail krijgt met de posts die vandaag op de planning staan.',
                'Melding bij gemiste posts' => 'Stuurt een melding zodra een geplande post niet is gepubliceerd, zodat je snel kunt ingrijpen.',
                'Wekelijkse melding lege slots' => 'Wekelijkse mail met een overzicht van lege plekken in je content schema, handig om op tijd nieuwe posts in te plannen.',
                'Feestdag herinneringen' => 'Herinnert je aan aankomende feestdagen zodat je daar tijdig posts voor kunt voorbereiden.',
            ],
            tips: [
                'Hoe specifieker je doelgroep en USP\'s zijn, hoe beter de AI in jouw stem schrijft.',
                'Test FAL.ai eerst met een post voordat je grote hoeveelheden beelden laat genereren.',
                'Zet in elk geval de meldingen voor gemiste posts aan, zo voorkom je dat je publicaties stilvallen.',
            ],
        );

        cms()->registerRolePermissions('Marketing', [
            'view_social_post' => 'Social posts bekijken',
            'edit_social_post' => 'Social posts bewerken',
            'delete_social_post' => 'Social posts verwijderen',
            'view_social_idea' => 'Ideeën bekijken',
            'edit_social_idea' => 'Ideeën bewerken',
            'view_keyword_research' => 'Zoekwoord onderzoek bekijken',
            'edit_keyword_research' => 'Zoekwoord onderzoek bewerken',
            'view_content_draft' => 'Content concepten bekijken',
            'edit_content_draft' => 'Content concepten bewerken',
            'view_seo_improvement' => 'SEO verbeteringen bekijken',
            'edit_seo_improvement' => 'SEO verbeteringen bewerken',
        ]);

        if (class_exists(BlogArticleTemplate::TARGET)) {
            ContentTemplates::register('blog', BlogArticleTemplate::class);
        }
        if (class_exists(LandingPageTemplate::TARGET)) {
            ContentTemplates::register('landing_page', LandingPageTemplate::class);
        }
        if (class_exists(ProductCategoryTemplate::TARGET)) {
            ContentTemplates::register('category', ProductCategoryTemplate::class);
        }
        if (class_exists(ProductTemplate::TARGET)) {
            ContentTemplates::register('product', ProductTemplate::class);
        }

        try {
            foreach ((array) cms()->builder('routeModels') as $entry) {
                $class = is_array($entry) ? ($entry['class'] ?? null) : null;
                if ($class && class_exists($class)) {
                    $class::observe(VisitableModelEmbeddingObserver::class);
                }
            }
        } catch (\Throwable) {
            // cms() helper or routeModels builder unavailable during early boot - skip.
        }
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->hasConfigFile(['dashed-marketing', 'dashed-marketing-content'])
            ->hasViews('dashed-marketing')
            ->hasCommands([
                PublishDueSocialPostsCommand::class,
                SocialNotifyDueCommand::class,
                SocialNotifyMissedCommand::class,
                SocialCheckHolidaysCommand::class,
                SocialWeeklyGapsCommand::class,
                SocialKeywordSyncCommand::class,
            ])
            ->name(self::$name);
    }

    public function registeringPackage()
    {
        $keywordResearchAdapter = config('dashed-marketing.adapters.keyword_research');
        if ($keywordResearchAdapter) {
            $this->app->bind(KeywordResearchAdapter::class, fn () => new $keywordResearchAdapter());
        }

        $this->app->bind(PublishingAdapter::class, function ($app, array $parameters = []) {
            $siteId = $parameters['site_id'] ?? null;
            $slug = Customsetting::get('social_publishing_adapter', $siteId) ?: 'manual';
            $entry = PublishingAdapterRegistry::get($slug);

            return $entry ? new $entry['class']() : new ManualPublishAdapter();
        });

        $this->app->singleton(KeywordDataManager::class, function () {
            return new KeywordDataManager();
        });

        $this->app->singleton(ContentTemplateRegistry::class);
    }
}

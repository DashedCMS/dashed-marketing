<?php

namespace Dashed\DashedMarketing;

use Dashed\DashedMarketing\Commands\SocialCheckHolidaysCommand;
use Dashed\DashedMarketing\Commands\SocialKeywordSyncCommand;
use Dashed\DashedMarketing\Commands\SocialNotifyDueCommand;
use Dashed\DashedMarketing\Commands\SocialNotifyMissedCommand;
use Dashed\DashedMarketing\Commands\SocialWeeklyGapsCommand;
use Dashed\DashedMarketing\Contracts\KeywordResearchAdapter;
use Dashed\DashedMarketing\Contracts\PublishingAdapter;
use Dashed\DashedMarketing\Facades\ContentTemplates;
use Dashed\DashedMarketing\Filament\Pages\Settings\SocialSettingsPage;
use Dashed\DashedMarketing\Managers\ContentTemplateRegistry;
use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Dashed\DashedMarketing\Observers\VisitableModelEmbeddingObserver;
use Dashed\DashedMarketing\Templates\BlogArticleTemplate;
use Dashed\DashedMarketing\Templates\LandingPageTemplate;
use Dashed\DashedMarketing\Templates\ProductCategoryTemplate;
use Dashed\DashedMarketing\Templates\ProductTemplate;
use Illuminate\Console\Scheduling\Schedule;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DashedMarketingServiceProvider extends PackageServiceProvider
{
    public static string $name = 'dashed-marketing';

    public function bootingPackage()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('social:notify-due')->dailyAt('08:00');
            $schedule->command('social:notify-missed')->dailyAt('09:00');
            $schedule->command('social:check-holidays')->dailyAt('07:00');
            $schedule->command('social:weekly-gaps')->weeklyOn(1, '08:30'); // Monday
            $schedule->command('social:keyword-sync')->dailyAt('03:00');
        });

        cms()->builder('plugins', [
            new DashedMarketingPlugin,
        ]);

        cms()->registerSettingsPage(
            SocialSettingsPage::class,
            'Social media',
            'share',
            'Social media platforms, AI context en notificaties'
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
            // cms() helper or routeModels builder unavailable during early boot — skip.
        }
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->hasConfigFile(['dashed-marketing', 'dashed-marketing-content'])
            ->hasViews('dashed-marketing')
            ->hasCommands([
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
            $this->app->bind(KeywordResearchAdapter::class, fn () => new $keywordResearchAdapter);
        }

        $this->app->bind(PublishingAdapter::class, function () {
            return new (config('dashed-marketing.adapters.publishing'));
        });

        $this->app->singleton(KeywordDataManager::class, function () {
            return new KeywordDataManager;
        });

        $this->app->singleton(ContentTemplateRegistry::class);
    }
}

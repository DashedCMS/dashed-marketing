<?php

namespace Dashed\DashedMarketing\Filament\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Dashed\DashedMarketing\Filament\Widgets\SocialCalendarWidget;

class SocialCalendarPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Social kalender';

    protected static ?string $title = 'Social kalender';

    protected static ?int $navigationSort = 12;

    protected string $view = 'dashed-marketing::pages.social-calendar';

    public function getSubheading(): ?string
    {
        return 'Maandoverzicht van al je ingeplande en geposte content. Sleep posts naar een andere datum om ze te herplannen. Kleurcodes: grijs = concept, oranje = in review, blauw = goedgekeurd, paars = ingepland, groen = gepost, rood = verlopen.';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SocialCalendarWidget::class,
        ];
    }
}

<?php

namespace Dashed\DashedMarketing\Facades;

use Illuminate\Support\Facades\Facade;
use Dashed\DashedMarketing\Managers\KeywordDataManager;

/**
 * @method static void register(\Dashed\DashedMarketing\Contracts\KeywordDataProvider $provider)
 * @method static void setDefault(string $name)
 * @method static \Dashed\DashedMarketing\Contracts\KeywordDataProvider provider(?string $name = null)
 * @method static array enrich(array $keywords, string $locale, ?string $provider = null)
 * @method static array<int, string> providerNames()
 */
class KeywordData extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return KeywordDataManager::class;
    }
}

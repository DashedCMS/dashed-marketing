<?php

namespace Dashed\DashedMarketing\Facades;

use Dashed\DashedMarketing\Managers\KeywordDataManager;
use Illuminate\Support\Facades\Facade;

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

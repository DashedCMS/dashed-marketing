<?php

namespace Dashed\DashedMarketing\Facades;

use Dashed\DashedMarketing\Managers\ContentTemplateRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void register(string $contentType, string $templateClass)
 * @method static bool has(string $contentType)
 * @method static \Dashed\DashedMarketing\Contracts\ContentTemplate make(string $contentType)
 * @method static array<int, string> registeredTypes()
 */
class ContentTemplates extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContentTemplateRegistry::class;
    }
}

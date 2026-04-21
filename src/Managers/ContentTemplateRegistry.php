<?php

namespace Dashed\DashedMarketing\Managers;

use Dashed\DashedMarketing\Contracts\ContentTemplate;
use InvalidArgumentException;

class ContentTemplateRegistry
{
    /** @var array<string, class-string<ContentTemplate>> */
    protected array $templates = [];

    public function register(string $contentType, string $templateClass): void
    {
        if (! is_subclass_of($templateClass, ContentTemplate::class)) {
            throw new InvalidArgumentException("{$templateClass} must implement ContentTemplate");
        }
        $this->templates[$contentType] = $templateClass;
    }

    public function has(string $contentType): bool
    {
        return isset($this->templates[$contentType]);
    }

    public function make(string $contentType): ContentTemplate
    {
        if (! $this->has($contentType)) {
            throw new InvalidArgumentException("No template registered for [{$contentType}]");
        }

        return app($this->templates[$contentType]);
    }

    /** @return array<int, string> */
    public function registeredTypes(): array
    {
        return array_keys($this->templates);
    }
}

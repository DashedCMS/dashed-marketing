<?php

namespace Dashed\DashedMarketing\Managers;

use Dashed\DashedMarketing\Contracts\PublishingAdapter;

class PublishingAdapterRegistry
{
    /**
     * @var array<string, array{label: string, class: class-string<PublishingAdapter>}>
     */
    protected static array $adapters = [];

    public static function register(string $slug, string $class, string $label): void
    {
        self::$adapters[$slug] = [
            'label' => $label,
            'class' => $class,
        ];
    }

    public static function exists(string $slug): bool
    {
        return isset(self::$adapters[$slug]);
    }

    public static function get(string $slug): ?array
    {
        return self::$adapters[$slug] ?? null;
    }

    public static function all(): array
    {
        return array_map(fn (array $adapter) => $adapter['label'], self::$adapters);
    }

    public static function clear(): void
    {
        self::$adapters = [];
    }
}

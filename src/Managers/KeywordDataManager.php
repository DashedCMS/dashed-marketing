<?php

namespace Dashed\DashedMarketing\Managers;

use InvalidArgumentException;
use Dashed\DashedMarketing\Contracts\KeywordDataProvider;
use Dashed\DashedMarketing\Adapters\ManualKeywordDataProvider;

class KeywordDataManager
{
    /** @var array<string, KeywordDataProvider> */
    protected array $providers = [];

    protected string $default = 'manual';

    public function __construct()
    {
        $this->register(new ManualKeywordDataProvider());
    }

    public function register(KeywordDataProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function setDefault(string $name): void
    {
        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown keyword data provider [{$name}]");
        }
        $this->default = $name;
    }

    public function provider(?string $name = null): KeywordDataProvider
    {
        $name ??= $this->default;

        if (! isset($this->providers[$name])) {
            throw new InvalidArgumentException("Unknown keyword data provider [{$name}]");
        }

        return $this->providers[$name];
    }

    public function enrich(array $keywords, string $locale, ?string $provider = null): array
    {
        return $this->provider($provider)->enrich($keywords, $locale);
    }

    /** @return array<int, string> */
    public function providerNames(): array
    {
        return array_keys($this->providers);
    }
}

<?php

namespace Dashed\DashedMarketing\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ContentTemplate
{
    public function contentType(): string;

    /** @return array<int, array{key: string, type: string, required: bool}> */
    public function blocks(): array;

    /** @return array<int, array{key: string, type: string}> */
    public function optionalBlocks(): array;

    public function promptContext(): string;

    /** @return array<string, mixed> */
    public function outputSchema(): array;

    public function applyTo(Model $subject, array $content): void;

    public function canTarget(string $modelClass): bool;
}

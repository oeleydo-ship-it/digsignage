<?php

namespace App\Widgets;

interface Widget
{
    public function key(): string;

    public function label(): string;

    public function description(): string;

    /**
     * @return list<WidgetField>
     */
    public function schema(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function resolve(array $settings, WidgetContext $context): array;
}

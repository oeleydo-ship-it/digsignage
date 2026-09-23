<?php

namespace App\Widgets;

class SchemaWidget implements Widget
{
    /**
     * @param  list<WidgetField>  $fields
     * @param  (callable(array<string, mixed>, WidgetContext): array<string, mixed>)|null  $resolver
     */
    public function __construct(
        protected string $widgetKey,
        protected string $widgetLabel,
        protected string $widgetDescription,
        protected array $fields,
        protected mixed $resolver = null,
    ) {}

    public function key(): string
    {
        return $this->widgetKey;
    }

    public function label(): string
    {
        return $this->widgetLabel;
    }

    public function description(): string
    {
        return $this->widgetDescription;
    }

    /**
     * @return list<WidgetField>
     */
    public function schema(): array
    {
        return $this->fields;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields as $field) {
            $defaults[$field->name] = $field->default;
        }

        return $defaults;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function resolve(array $settings, WidgetContext $context): array
    {
        if (! is_callable($this->resolver)) {
            return $settings;
        }

        /** @var callable(array<string, mixed>, WidgetContext): array<string, mixed> $resolver */
        $resolver = $this->resolver;

        return $resolver($settings, $context);
    }
}

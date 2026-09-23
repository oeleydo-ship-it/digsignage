<?php

namespace App\Widgets;

readonly class WidgetField
{
    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $label,
        public mixed $default = null,
        public bool $required = false,
        public array $options = [],
        public ?string $help = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'label' => $this->label,
            'default' => $this->default,
            'required' => $this->required,
            'options' => $this->options,
            'help' => $this->help,
        ];
    }
}

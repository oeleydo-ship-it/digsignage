<?php

namespace App\Enums;

enum TemplateCategory: string
{
    case Corporate = 'corporate';
    case Healthcare = 'healthcare';
    case Restaurant = 'restaurant';
    case Retail = 'retail';
    case Education = 'education';
    case Hospitality = 'hospitality';
    case Events = 'events';
    case Transportation = 'transportation';
    case Announcements = 'announcements';
    case MenuBoards = 'menu_boards';
    case Emergency = 'emergency';
    case News = 'news';
    case Meeting = 'meeting';
    case Portrait = 'portrait';
    case Vertical = 'vertical';

    /**
     * Get the display label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::MenuBoards => 'Menu boards',
            default => ucfirst($this->value),
        };
    }
}

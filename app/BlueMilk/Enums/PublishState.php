<?php

namespace App\BlueMilk\Enums;

enum PublishState: string
{
    case DRAFT = 'draft';
    case PUBLIC = 'published';

    public function description(): string
    {
        return match ($this) {
            self::DRAFT => 'Bozza',
            self::PUBLIC => 'Pubblicato',
        };
    }

    public function getLabel(): ?string
    {
        return $this->description();
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::PUBLIC => 'success',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DRAFT => 'heroicon-m-pencil',
            self::PUBLIC => 'heroicon-m-check',
        };
    }
}

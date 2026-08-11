<?php

namespace App\Enums;

enum CheckinStatus: string
{
    case CALLED_PENDING = 'pending';
    case COMPLETED_OK = 'ok';
    case CALLED_ALERT = 'alert';
    case COMPLETED_NO_ANSWER = 'no_answer';

    public function description(): string
    {
        return match ($this) {
            self::CALLED_PENDING => 'Chiamata in sospeso',
            self::COMPLETED_OK => 'Completato con successo',
            self::CALLED_ALERT => 'Chiamata con alert',
            self::COMPLETED_NO_ANSWER => 'Completato senza risposta',
        };
    }

    public function getLabel(): ?string
    {
        return $this->description();
    }

    public function getColor(): ?string
    {
        return match ($this) {
            self::CALLED_PENDING => 'gray',
            self::COMPLETED_OK => 'success',
            self::CALLED_ALERT => 'red',
            self::COMPLETED_NO_ANSWER => 'yellow',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CALLED_PENDING => 'heroicon-m-clock',
            self::COMPLETED_OK => 'heroicon-m-check',
            self::CALLED_ALERT => 'heroicon-m-exclamation',
            self::COMPLETED_NO_ANSWER => 'heroicon-m-x',
        };
    }
}

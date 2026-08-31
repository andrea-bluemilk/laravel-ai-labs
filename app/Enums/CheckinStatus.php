<?php

namespace App\Enums;

enum CheckinStatus: string
{
    case CALLED_PENDING = 'pending';
    case COMPLETED_OK = 'ok';
    case CALLED_ALERT = 'alert';
    case COMPLETED_NO_ANSWER = 'no_answer';
    case FAILED_TECHNICAL = 'failed_technical';

    public function description(): string
    {
        return match ($this) {
            self::CALLED_PENDING => 'Chiamata in sospeso',
            self::COMPLETED_OK => 'Completato con successo',
            self::CALLED_ALERT => 'Chiamata con alert',
            self::COMPLETED_NO_ANSWER => 'Completato senza risposta',
            self::FAILED_TECHNICAL => 'Guasto tecnico, da riprovare',
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
            self::FAILED_TECHNICAL => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::CALLED_PENDING => 'heroicon-m-clock',
            self::COMPLETED_OK => 'heroicon-m-check',
            self::CALLED_ALERT => 'heroicon-m-exclamation',
            self::COMPLETED_NO_ANSWER => 'heroicon-m-x',
            self::FAILED_TECHNICAL => 'heroicon-m-wrench-screwdriver',
        };
    }

    /**
     * Un guasto tecnico non dice nulla sullo stato della guardia: la chiamata va rifatta.
     */
    public function isRetryable(): bool
    {
        return $this === self::FAILED_TECHNICAL;
    }
}

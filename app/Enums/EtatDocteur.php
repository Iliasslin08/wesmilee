<?php

namespace App\Enums;

enum EtatDocteur: string
{
    case Pret = 'pret';
    case Occupe = 'occupe';
    case Pause = 'pause';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Pret => 'Prêt',
            self::Occupe => 'Occupé',
            self::Pause => 'Pause',
            self::Absent => 'Absent',
        };
    }
}

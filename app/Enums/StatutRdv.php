<?php

namespace App\Enums;

enum StatutRdv: string
{
    case Planifie = 'planifie';
    case Arrive = 'arrive';
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case Annule = 'annule';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Planifie => 'Planifié',
            self::Arrive => 'Arrivé',
            self::EnCours => 'En séance',
            self::Termine => 'Terminé',
            self::Annule => 'Annulé',
            self::Absent => 'Absent',
        };
    }

    /** Statuts qui occupent un créneau. */
    public static function actifs(): array
    {
        return [self::Planifie->value, self::Arrive->value, self::EnCours->value, self::Termine->value];
    }
}

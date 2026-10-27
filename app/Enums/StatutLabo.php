<?php

namespace App\Enums;

enum StatutLabo: string
{
    case AFaire = 'a_faire';
    case EnFabrication = 'en_fabrication';
    case Pret = 'pret';
    case Livre = 'livre';
    case Pose = 'pose';

    public function label(): string
    {
        return match ($this) {
            self::AFaire => 'À commencer',
            self::EnFabrication => 'En fabrication',
            self::Pret => 'Prêt',
            self::Livre => 'Livré au centre',
            self::Pose => 'Posé',
        };
    }
}

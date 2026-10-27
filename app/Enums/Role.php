<?php

namespace App\Enums;

enum Role: string
{
    case Reception = 'secretaire';
    case Docteur = 'docteur';
    case Finance = 'admin_finance';
    case Labo = 'docteur_labo';
    case Photographe = 'photographe';
    case Radio = 'radiologue';
    case Admin = 'admin';
    // Développeuse de la plateforme : accès total, invisible dans la liste des utilisateurs
    case Developpeur = 'developpeur';

    public function label(): string
    {
        return match ($this) {
            self::Reception => 'Réception',
            self::Docteur => 'Docteur',
            self::Finance => 'Finance',
            self::Labo => 'Laboratoire',
            self::Photographe => 'Photographe',
            self::Radio => 'Radiologie',
            self::Admin => 'Chef / Admin',
            self::Developpeur => 'Chef / Admin',
        };
    }

    /** Page d'accueil après connexion. */
    public function home(): string
    {
        return match ($this) {
            self::Reception => 'salle-attente.index',
            self::Docteur => 'docteur.patients',
            self::Finance => 'patients.index',
            self::Labo => 'labo.index',
            self::Photographe => 'patients.index',
            self::Radio => 'radio.index',
            self::Admin, self::Developpeur => 'admin.tableau-de-bord',
        };
    }

    public static function options(): array
    {
        // le rôle développeur ne se choisit jamais dans l'écran Utilisateurs
        return array_values(array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], array_filter(self::cases(), fn ($r) => $r !== self::Developpeur)));
    }
}

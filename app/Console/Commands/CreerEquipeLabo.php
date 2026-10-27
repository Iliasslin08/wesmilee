<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Crée (ou met à jour) les comptes de l'équipe du laboratoire, avec la mission de chacun. */
class CreerEquipeLabo extends Command
{
    protected $signature = 'cabinet:equipe-labo';

    protected $description = 'Crée les comptes du laboratoire (prothésistes, céramistes, CAD/CAM)';

    /** [nom, prénom, tâches principales, tâches secondaires, salle] */
    public const EQUIPE = [
        ['MOSTAFI', 'Sofia', 'Cheffe du labo · Prothésiste', 'CAD / CAM', 'Labo'], // accepte et confie les demandes
        ['EL MAATAOUI', 'Walid', 'Prothésiste', 'CAD / CAM', 'Labo'],
        ['AIT LAHSEN', 'Brahim', 'Céramiste', null, 'Labo'],
        ['TAISS', 'Yassir', 'Céramiste', null, 'Labo'],
        ['BOUICHA', 'Morad', 'Prothésiste', 'CAD / CAM', 'Labo'],
        ['CHRIYAA', 'Zaid', 'Prothésiste', null, 'Labo / stérilisation'],
        ['BELFKIH', 'Mohssine', 'Prothésiste', 'Céramiste', 'Labo / stérilisation'],
    ];

    public function handle(): int
    {
        $lignes = [];
        foreach (self::EQUIPE as [$nom, $prenom, $principales, $secondaires, $salle]) {
            $email = Str::slug($prenom.'.'.$nom, '.').'@wesmile.ma';
            $user = User::withTrashed()->where('email', $email)->first();
            $mdp = null;
            if ($user?->trashed()) { // compte supprimé pendant les tests : on le remet
                $user->restore();
                $mdp = Str::password(10, symbols: false);
                $user->fill(['password' => $mdp, 'actif' => true]);
            }
            if (! $user) {
                $mdp = Str::password(10, symbols: false);
                $user = new User(['email' => $email, 'password' => $mdp, 'actif' => true]);
                $user->email_verified_at = now();
            }
            $user->fill([
                'name' => $prenom.' '.$nom,
                'role' => Role::Labo,
                'taches_principales' => $principales,
                'taches_secondaires' => $secondaires,
                'salle' => $salle,
                'chef_labo' => $nom === 'MOSTAFI',
            ])->save();
            $lignes[] = [$prenom.' '.$nom, $principales.($secondaires ? ' · '.$secondaires : ''), $email, $mdp ?? '(inchangé)'];
        }
        $this->table(['Nom', 'Mission', 'Identifiant', 'Mot de passe provisoire'], $lignes);
        $this->warn('Notez les mots de passe et donnez-les à chacun : ils ne seront plus affichés.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/** Problème de connexion : affiche l'état d'un compte et permet de le réparer (restaurer, activer, nouveau mot de passe). */
class VerifierCompte extends Command
{
    protected $signature = 'cabinet:compte {email? : e-mail du compte (vide = liste de tous les comptes)} {--mdp= : nouveau mot de passe}';

    protected $description = 'Vérifie un compte et le répare (connexion impossible)';

    public function handle(): int
    {
        $email = $this->argument('email');
        if (! $email) {
            $this->table(['Nom', 'E-mail', 'Rôle', 'Actif', 'Supprimé'], User::withTrashed()->where('role', '!=', 'developpeur')->orderBy('role')->get()
                ->map(fn ($u) => [$u->name, $u->email, $u->role?->label(), $u->actif ? 'oui' : 'NON', $u->trashed() ? 'OUI' : '']));

            return self::SUCCESS;
        }

        $user = User::withTrashed()->where('email', trim($email))->first();
        if (! $user) {
            $proches = User::withTrashed()->where('email', 'like', '%'.Str::before($email, '@').'%')->pluck('email');
            $this->error("Aucun compte avec l'e-mail « {$email} ».".($proches->isNotEmpty() ? ' Comptes proches : '.$proches->implode(', ') : ''));
            $this->line('Tapez « php artisan cabinet:compte » pour voir tous les comptes.');

            return self::FAILURE;
        }

        $this->table(['Nom', 'Rôle', 'Actif', 'Supprimé', 'Mot de passe « password » ?'], [[
            $user->name, $user->role?->label(), $user->actif ? 'oui' : 'NON', $user->trashed() ? 'OUI' : 'non',
            Hash::check('password', $user->password) ? 'oui' : 'non',
        ]]);

        if ($user->trashed()) {
            $user->restore();
            $this->info('Compte restauré (il avait été supprimé).');
        }
        if (! $user->actif) {
            $user->update(['actif' => true]);
            $this->info('Compte réactivé.');
        }
        $mdp = $this->option('mdp') ?: ($this->confirm('Mettre un nouveau mot de passe ?', true) ? $this->secret('Nouveau mot de passe (8 caractères minimum)') : null);
        if ($mdp) {
            if (strlen($mdp) < 8) {
                $this->error('Mot de passe trop court.');

                return self::FAILURE;
            }
            $user->update(['password' => $mdp]);
            $this->info('Mot de passe changé.');
        }
        // Débloque les tentatives de connexion ratées (« trop de tentatives »)
        foreach (['127.0.0.1', '::1'] as $ip) {
            RateLimiter::clear(Str::transliterate(Str::lower($user->email)).'|'.$ip);
        }
        $this->info("Vous pouvez vous connecter avec {$user->email}.");

        return self::SUCCESS;
    }
}

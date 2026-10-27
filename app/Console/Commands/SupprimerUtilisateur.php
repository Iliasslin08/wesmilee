<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\CommandeLabo;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Supprime un compte (ou toute l'équipe labo de test). Le compte est désactivé et caché ;
 * ses travaux labo redeviennent « à attribuer ». « cabinet:equipe-labo » peut les recréer.
 */
class SupprimerUtilisateur extends Command
{
    protected $signature = 'cabinet:supprimer-utilisateur {email? : e-mail du compte} {--labo : supprimer les 7 comptes de l\'équipe labo}';

    protected $description = 'Supprime un utilisateur, ou toute l\'équipe du labo (--labo)';

    public function handle(): int
    {
        if ($this->option('labo')) {
            $emails = collect(CreerEquipeLabo::EQUIPE)->map(fn ($l) => Str::slug($l[1].'.'.$l[0], '.').'@wesmile.ma');
        } elseif ($this->argument('email')) {
            $emails = collect([$this->argument('email')]);
        } else {
            $this->error('Indiquez un e-mail, ou --labo pour toute l\'équipe du labo.');

            return self::FAILURE;
        }

        $users = User::whereIn('email', $emails)->where('role', '!=', Role::Developpeur->value)->get();
        if ($users->isEmpty()) {
            $this->warn('Aucun compte trouvé.');

            return self::SUCCESS;
        }
        $this->table(['Nom', 'E-mail', 'Rôle'], $users->map(fn ($u) => [$u->name, $u->email, $u->role?->label()]));
        if (! $this->confirm('Supprimer ces comptes ?', true)) {
            return self::FAILURE;
        }
        foreach ($users as $u) {
            CommandeLabo::where('technicien_id', $u->id)->update(['technicien_id' => null]);
            $u->update(['actif' => false]);
            $u->delete();
        }
        $this->info($users->count().' compte(s) supprimé(s). Leurs travaux labo sont « à attribuer ».');

        return self::SUCCESS;
    }
}

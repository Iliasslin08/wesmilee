<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Crée / met à jour les comptes des docteurs du cabinet (config/docteurs.php)
 * et supprime les docteurs de démonstration (…@wesmile.ma).
 * On peut le relancer : les docteurs déjà créés sont mis à jour, leur mot de passe ne change pas.
 */
class CreerDocteurs extends Command
{
    protected $signature = 'cabinet:docteurs {--garder-demo : ne pas supprimer les docteurs de démonstration} {--reset : remettre le mot de passe « password » à tous les docteurs} {--test-24h : TEST : horaires lundi–samedi 9h–18h et docteurs « Prêt »}';

    protected $description = 'Crée les comptes des docteurs du cabinet et supprime les docteurs de démonstration';

    public function handle(): int
    {
        $liste = config('docteurs');
        $emails = collect($liste)->pluck('email')->map(fn ($e) => strtolower(trim($e)));

        if (! $this->option('garder-demo')) {
            // Tous les docteurs qui ne sont pas dans la liste du cabinet sont supprimés
            $demo = User::where('role', Role::Docteur->value)->whereNotIn('email', $emails)->get();
            foreach ($demo as $u) {
                $u->update(['actif' => false]);
                $u->delete();
            }
            if ($demo->isNotEmpty()) {
                $this->info('Anciens docteurs supprimés : '.$demo->pluck('name')->implode(', '));
                // Leurs RDV à venir passent dans « RDV à fixer » : la réception les replace chez un vrai docteur
                $aVenir = \App\Models\RendezVous::whereIn('docteur_id', $demo->pluck('id'))->where('debut', '>=', now()->startOfDay())
                    ->where('statut', 'planifie')->get();
                foreach ($aVenir as $r) {
                    \App\Models\RdvAFixer::create([
                        'patient_id' => $r->patient_id, 'acte_id' => $r->acte_id, 'mission' => $r->mission ?: 'Consultation',
                        'notes' => 'Était prévu le '.$r->debut->format('d/m/Y H:i').' (ancien docteur supprimé)',
                    ]);
                    $r->update(['statut' => 'annule']);
                }
                if ($aVenir->isNotEmpty()) {
                    $this->warn($aVenir->count().' rendez-vous à venir sont maintenant dans « RDV à fixer » (agenda) : à replacer chez un docteur.');
                }
            }
        }

        $lignes = [];
        foreach ($liste as $d) {
            $email = strtolower(trim($d['email']));
            $user = User::withTrashed()->where('email', $email)->first();
            $nouveau = ! $user || $user->trashed();
            // Phase de test : mot de passe « password » (à changer avant la mise en ligne)
            $mdp = 'password';
            $user ??= new User(['email' => $email]);
            if ($user->trashed()) {
                $user->restore();
            }
            $user->fill([
                'name' => $d['nom'],
                'role' => Role::Docteur,
                'telephone' => $d['telephone'],
                'specialite' => $d['specialite'],
                'taches_principales' => $d['soins'],
                'autres_taches' => $d['autres'] ?? null,
                'fait_consultation' => (bool) $d['consultation'],
                'duree_rdv' => $d['duree'],
                'assistante' => $d['assistante'] ?? null,
                'couleur' => $user->couleur ?: ($d['couleur'] ?? '#04a3b5'),
                'actif' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            if ($nouveau || $this->option('reset')) {
                $user->password = $mdp; // haché automatiquement
            }
            $user->save();

            if ($this->option('test-24h')) {
                // Phase de test : horaires du centre (lundi–samedi 9h–18h) et docteur « Prêt »
                $user->disponibilites()->delete();
                foreach (range(1, 6) as $jour) {
                    $user->disponibilites()->create(['jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '18:00']);
                }
                $user->update(['etat' => \App\Enums\EtatDocteur::Pret, 'etat_depuis' => now()]);
            } elseif (! $user->disponibilites()->exists()) {
                // Horaires du centre : lundi–samedi 9h–18h (modifiable dans « Mes horaires »)
                foreach (range(1, 6) as $jour) {
                    $user->disponibilites()->create(['jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '18:00']);
                }
            }
            $lignes[] = [$d['nom'], $email, $nouveau || $this->option('reset') ? $mdp : '(inchangé)'];
        }
        $this->table(['Docteur', 'Identifiant', 'Mot de passe'], $lignes);
        $this->warn('Avant la mise en ligne : chaque docteur change son mot de passe (Paramètres > Mot de passe).');

        return self::SUCCESS;
    }
}

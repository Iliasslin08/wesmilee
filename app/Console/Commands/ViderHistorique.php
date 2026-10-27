<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Efface tout l'historique (actes, séances, rendez-vous, labo, radios, ordonnances, paiements…)
 * mais GARDE les patients, leurs dossiers (n°, notes) et leurs documents (CIN, dossiers papier scannés).
 */
class ViderHistorique extends Command
{
    protected $signature = 'cabinet:vider-historique {--force : ne pas demander de confirmation}';

    protected $description = 'Supprime actes, séances, rendez-vous, labo, radios, ordonnances et paiements ; garde les patients et leurs documents';

    private const TABLES = [
        'sms' => 'SMS envoyés',
        'avis' => 'commentaires patients',
        'plans_a_valider' => 'devis à présenter',
        'rdv_a_fixer' => 'rendez-vous à fixer',
        'demandes_radio' => 'demandes de radio',
        'ordonnances' => 'ordonnances',
        'radios' => 'radios',
        'paiements' => 'paiements',
        'clotures_caisse' => 'clôtures de caisse',
        'commandes_labo' => 'commandes labo',
        'etat_dents' => 'schémas dentaires',
        'actes' => 'actes',
        'seances' => 'séances',
        'rendez_vous' => 'rendez-vous',
    ];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Plateforme en production : relancez avec --force si vous êtes sûr.');

            return self::FAILURE;
        }
        $tables = array_filter(self::TABLES, fn ($t) => Schema::hasTable($t), ARRAY_FILTER_USE_KEY);
        $this->table(['À supprimer', 'Nombre'], collect($tables)->map(fn ($lib, $t) => [$lib, DB::table($t)->count()])->values()->all());
        $this->info('Conservés : '.DB::table('patients')->whereNull('deleted_at')->count().' patients, leurs dossiers et '.DB::table('documents')->count().' documents (CIN, dossiers papier scannés…).');

        if (! $this->option('force') && ! $this->confirm('Supprimer tout cet historique ? (impossible à annuler)')) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($tables) {
            foreach (array_keys($tables) as $t) {
                DB::table($t)->delete();
            }
            DB::table('dossiers')->update(['statut' => 'en_suivi']);
            DB::table('journal')->whereIn('sujet_type', ['RendezVous', 'Seance', 'Acte', 'CommandeLabo', 'Paiement', 'Radio', 'ClotureCaisse', 'DemandeRadio', 'Ordonnance', 'RdvAFixer', 'PlanAValider'])->delete();
            DB::table('users')->where('role', 'docteur')->update(['etat' => 'pret', 'etat_depuis' => null]);
        });

        $this->info('Historique supprimé. Les patients, leurs dossiers et leurs documents sont conservés.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\CatalogueActe;
use App\Models\DemandeRadio;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\Radio;
use App\Models\RdvAFixer;
use App\Models\RendezVous;
use App\Models\Seance;
use App\Models\User;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Console\Command;

/**
 * Crée UN patient fictif « TEST Patient » avec un dossier très complet, pour tester toutes les pages
 * (actes à faire / en cours / faits, séances, RDV, labo, radio, ordonnance, paiements, devis, commentaire…).
 * Les vrais patients ne sont pas touchés.
 */
class CreerPatientTest extends Command
{
    protected $signature = 'cabinet:patient-test';

    protected $description = 'Crée un seul patient fictif « TEST » avec un dossier très riche pour les tests';

    private const TEL = '0600000099';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Interdit en production.');

            return self::FAILURE;
        }
        if ($deja = Patient::where('telephone', self::TEL)->first()) {
            $this->warn('Le patient TEST existe déjà : dossier n° '.$deja->dossier?->numero.'.');

            return self::SUCCESS;
        }
        $docteurs = User::docteurs()->orderBy('name')->get();
        if ($docteurs->isEmpty()) {
            $this->error('Aucun docteur dans la plateforme.');

            return self::FAILURE;
        }
        $cons = $docteurs->firstWhere('fait_consultation', true) ?? $docteurs->first();
        $autres = $docteurs->where('id', '!=', $cons->id)->values();
        $doc1 = $autres->get(0) ?? $cons;
        $doc2 = $autres->get(1) ?? $doc1;
        $rec = User::where('role', Role::Reception->value)->first();
        $fin = User::where('role', Role::Finance->value)->first();
        $radio = User::where('role', Role::Radio->value)->first();
        $labo = User::where('role', Role::Labo->value)->where('chef_labo', false)->first() ?? User::where('role', Role::Labo->value)->first();
        $j = fn (int $jours, int $h, int $m = 0) => now()->startOfDay()->addDays($jours)->setTime($h, $m);

        // Identité complète
        $p = Patient::create([
            'nom' => 'TEST', 'prenom' => 'Patient', 'telephone' => self::TEL, 'date_naissance' => '1985-05-15', 'sexe' => 'H',
            'cin' => 'TEST001', 'profession' => 'Enseignant', 'email' => 'patient.test@exemple.ma', 'ville' => 'Rabat', 'adresse' => '1 rue du Test, Agdal',
            'antecedents' => ['diabete', 'hypertension'], 'antecedents_autres' => 'Opéré du genou en 2015', 'allergies' => 'Pénicilline', 'traitements' => 'Metformine 500 mg',
            'statut_fiche' => 'validee', 'valide_par' => $rec?->id, 'valide_le' => now(),
        ]);
        $dossier = Dossier::create(['patient_id' => $p->id, 'cree_par' => $rec?->id, 'docteur_consultation_id' => $cons->id,
            'notes' => "PATIENT DE TEST (fictif) : ne pas facturer.\nPréfère les rendez-vous le matin.", 'diagnostic' => 'Carie profonde 36, absence 46, détartrage nécessaire', 'statut' => 'en_traitement']);
        $p = $p->fresh('dossier');

        // 1 · Consultation il y a 30 jours
        $s1 = $this->seance($dossier, $cons, $j(-30, 10), 20, 'Examen clinique complet + panoramique', 'Plan : détartrage, canal 36, couronne 36, implant 46');
        $this->acte($dossier, 'Consultation + plan de traitement', null, 'fait', $cons, $s1);

        // 2 · Détartrage fait il y a 20 jours
        $s2 = $this->seance($dossier, $doc2, $j(-20, 11), 45, 'Détartrage complet, saignement gingival', 'Contrôle dans 6 mois');
        $det = $this->acte($dossier, 'Détartrage', null, 'fait', $doc2, $s2);
        RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc2->id, 'mission' => 'Détartrage', 'debut' => $j(-20, 11), 'fin' => $j(-20, 11, 45), 'statut' => 'termine', 'acte_id' => $det->id, 'cree_par' => $rec?->id]);

        // 3 · Traitement de canal 36 commencé il y a 7 jours (en cours)
        $s3 = $this->seance($dossier, $doc1, $j(-7, 15), 60, 'Ouverture + mise en forme canalaire 36', 'Obturation à la prochaine séance');
        $canal = $this->acte($dossier, 'Traitement de canal', '36', 'en_cours', $cons, null);
        RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc1->id, 'mission' => 'Traitement de canal · 36', 'debut' => $j(-7, 15), 'fin' => $j(-7, 16), 'statut' => 'termine', 'acte_id' => $canal->id, 'cree_par' => $rec?->id]);
        $dossier->ordonnances()->create(['docteur_id' => $doc1->id, 'date' => $j(-7, 15)->toDateString(), 'lignes' => [
            ['medicament' => 'Amoxicilline 1 g', 'posologie' => '1 comprimé matin et soir', 'duree' => '6 jours'],
            ['medicament' => 'Paracétamol 1 g', 'posologie' => '1 comprimé si douleur, 3 fois par jour maximum', 'duree' => '3 jours'],
        ]]);

        // 4 · À faire : couronne 36 (au labo), implant 46
        $cour = $this->acte($dossier, 'Couronne zircone', '36', 'a_faire', $cons);
        $impl = $this->acte($dossier, 'Implant', '46', 'a_faire', $cons);
        $dossier->commandesLabo()->create(['acte_id' => $cour->id, 'travail' => 'Couronne zircone', 'dents' => '36', 'teinte' => 'A2',
            'instructions' => "Couronne zircone 36\nTeinte A2, forme naturelle", 'statut' => 'en_fabrication', 'envoye_par' => $doc1->id,
            'technicien_id' => $labo?->id, 'acceptee_le' => now(), 'date_prete' => $j(5, 9)->toDateString(), 'date_pose' => $j(7, 10)->toDateString()]);

        // 5 · Acte repris de l'ancien dossier papier
        $dossier->actes()->create(['libelle' => 'Extraction', 'dents' => '46', 'statut' => 'fait', 'prix_convenu' => 0, 'notes' => 'Ancien dossier papier', 'fait_le' => '2019-03-12']);

        // 6 · Schéma dentaire
        foreach ([36 => 'carie', 46 => 'absente', 18 => 'a_surveiller', 11 => 'couronne'] as $dent => $etat) {
            $dossier->etatsDents()->updateOrCreate(['dent' => $dent], ['etat' => $etat]);
        }

        // 7 · Radios : une faite (envoyée au labo), une demandée
        Radio::create(['dossier_id' => $dossier->id, 'type' => 'panoramique', 'mesures' => 'Os 10 mm en 46', 'faite_par' => $radio?->id]);
        DemandeRadio::create(['dossier_id' => $dossier->id, 'type' => 'cone_beam', 'notes' => 'Bilan osseux avant implant 46', 'demande_par' => $doc1->id]);

        // 8 · Paiements (un reste à payer)
        foreach ([[400, 'especes', 20], [1000, 'carte', 7]] as [$montant, $methode, $ilYa]) {
            $dossier->paiements()->create(['montant' => $montant, 'methode' => $methode, 'date' => now()->subDays($ilYa)->toDateString(),
                'reste_apres' => max(0, $dossier->reste() - $montant), 'encaisse_par' => $fin?->id]);
        }

        // 9 · Rendez-vous à venir + un RDV à fixer + un devis chez la finance
        RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc1->id, 'mission' => 'Traitement de canal · 36 (fin)', 'debut' => $j(3, 10), 'fin' => $j(3, 11), 'statut' => 'planifie', 'acte_id' => $canal->id, 'cree_par' => $rec?->id]);
        RdvAFixer::create(['patient_id' => $p->id, 'docteur_id' => $doc2->id, 'acte_id' => $impl->id, 'mission' => 'Implant · 46', 'notes' => 'Après la couronne 36, plutôt le matin', 'demande_par' => $cons->id]);
        \App\Models\PlanAValider::create(['dossier_id' => $dossier->id, 'propose_par' => $cons->id, 'docteur_id' => $doc2->id,
            'actes' => "Blanchiment\nFacette céramique (Hollywood Smile) · 11, 21", 'notes' => 'Le patient veut un devis esthétique', 'statut' => 'en_attente']);

        // 10 · Commentaire du patient
        \App\Models\Avis::create(['patient_id' => $p->id, 'commentaire' => 'Commentaire de test : très bon accueil, merci !']);

        $this->info('Patient TEST créé : dossier n° '.$dossier->numero.' · téléphone '.self::TEL.' · né le 15/05/1985.');

        return self::SUCCESS;
    }

    private function seance(Dossier $d, User $doc, Carbon $debut, int $min, string $cr, ?string $suite): Seance
    {
        return Seance::create(['dossier_id' => $d->id, 'docteur_id' => $doc->id, 'debut' => $debut, 'fin' => $debut->copy()->addMinutes($min),
            'duree_minutes' => $min, 'compte_rendu' => $cr, 'suite' => $suite]);
    }

    private function acte(Dossier $d, string $libelle, ?string $dents, string $statut, User $par, ?Seance $s = null)
    {
        $cat = CatalogueActe::where('libelle', $libelle)->first();

        return $d->actes()->create(['catalogue_acte_id' => $cat?->id, 'libelle' => $libelle, 'dents' => $dents, 'statut' => $statut,
            'prix_convenu' => $cat?->prix_defaut ?? 500, 'prevu_par' => $par->id, 'realise_par' => $statut === 'fait' ? $par->id : null,
            'seance_id' => $s?->id, 'fait_le' => $statut === 'fait' ? ($s?->fin ?? now()) : null]);
    }
}

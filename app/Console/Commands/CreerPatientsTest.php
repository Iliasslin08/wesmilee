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
use Illuminate\Console\Command;
use Carbon\CarbonInterface as Carbon;

/**
 * TEST : crée 24 patients FICTIFS avec des dossiers et des rendez-vous variés
 * (téléphone, salle d'attente, urgence, séance, labo, radio, paiements, en ligne…).
 * Jamais en production. Pour tout effacer : php artisan cabinet:vider-patients --force
 */
class CreerPatientsTest extends Command
{
    protected $signature = 'cabinet:patients-test';

    protected $description = 'TEST : crée 24 patients fictifs avec dossiers et rendez-vous variés';

    private array $doc = [];

    private ?User $rec;

    private ?User $fin;

    private ?User $ikram;

    private ?User $labo;

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Interdit en production : patients fictifs seulement sur le PC de test.');

            return self::FAILURE;
        }
        $mail = fn ($e) => User::where('email', $e)->first();
        $tous = User::docteurs()->get();
        if ($tous->isEmpty()) {
            $this->error('Aucun docteur : lancez d’abord php artisan cabinet:docteurs');

            return self::FAILURE;
        }
        $parDefaut = $tous->first();
        foreach ([
            'nassim' => 'nassimenasma92@gmail.com', 'malika' => 'malikaelbakkouri.ma@gmail.com', 'omar' => 'ouizzou2001@gmail.com',
            'ilham' => 'ilhamsoulaimi07@gmail.com', 'yasmine' => 'yasmine.ennaciri98@gmail.com', 'oualid' => 'dr.elmaachioualid@outlook.com',
        ] as $k => $e) {
            $this->doc[$k] = $mail($e) ?? $parDefaut;
        }
        $this->rec = User::where('role', Role::Reception->value)->first();
        $this->fin = User::where('role', Role::Finance->value)->first();
        $this->ikram = User::where('role', Role::Radio->value)->first();
        // Le travail de test est confié à quelqu'un du labo (pas à la cheffe) pour tester « mes travaux »
        $this->labo = User::where('role', Role::Labo->value)->where('chef_labo', false)->orderBy('name')->first() ?? User::where('role', Role::Labo->value)->first();
        $d = $this->doc;
        $now = now();
        $j = fn (int $jours, int $h, int $m = 0) => now()->startOfDay()->addDays($jours)->setTime($h, $m);

        // 1 · Pris au téléphone ce matin, vient aujourd'hui (fiche à remplir sur la tablette)
        $p = $this->patient('EL AMRANI', 'Hiba', '0612000101', null, [], ['fiche_a_completer' => true]);
        $this->rdv($p, $d['nassim'], 'Consultation · douleur dent de sagesse', $now->copy()->addMinutes(40), 10);

        // 2 · En salle d'attente (arrivé), consultation
        $p = $this->patient('BOUZIDI', 'Omar', '0612000102', '1984-03-11', ['ville' => 'Marrakech']);
        $this->rdv($p, $d['nassim'], 'Consultation', $now->copy()->subMinutes(10), 10, 'arrive', ['numero_appel' => 1, 'arrive_le' => $now->copy()->subMinutes(15)]);

        // 3 · Urgence sans RDV, en attente, et il a AUSSI un RDV la semaine prochaine
        $p = $this->patient('CHAKIR', 'Yassine', '0612000103', '1991-12-02', ['allergies' => 'Pénicilline']);
        $this->rdv($p, null, 'Urgence · abcès 46', $now->copy()->subMinutes(5), 30, 'arrive', ['urgence' => true, 'sans_rdv' => true, 'numero_appel' => 2, 'arrive_le' => $now->copy()->subMinutes(5)]);
        $this->rdv($p, $d['ilham'], 'Détartrage', $j(7, 10), 60);

        // 4 · En séance maintenant chez Dr Ilham (traitement de canal)
        $p = $this->patient('DAOUDI', 'Salma', '0612000104', '1979-06-25', ['antecedents' => ['hypertension']]);
        $this->consultationPassee($p, $d['nassim'], 14, 'Carie profonde 36, traitement de canal');
        $canal = $this->acte($p, 'Traitement de canal', '36', 'en_cours', $d['nassim']);
        $r = $this->rdv($p, $d['ilham'], 'Traitement de canal · 36', $now->copy()->subMinutes(20), 60, 'en_cours', ['acte_id' => $canal->id, 'numero_appel' => 3, 'arrive_le' => $now->copy()->subMinutes(30)]);
        Seance::create(['dossier_id' => $p->dossier->id, 'docteur_id' => $d['ilham']->id, 'rendez_vous_id' => $r->id, 'debut' => $now->copy()->subMinutes(18)]);
        $d['ilham']->update(['etat' => \App\Enums\EtatDocteur::Occupe, 'etat_depuis' => $now->copy()->subMinutes(18)]);

        // 5 · Venu ce matin, séance terminée, payé en partie
        $p = $this->patient('ESSAIDI', 'Karim', '0612000105', '1970-01-14');
        $s = $this->seance($p, $d['yasmine'], $j(0, 9), 45, 'Détartrage complet', 'Contrôle dans 6 mois');
        $a = $this->acte($p, 'Détartrage', null, 'fait', $d['yasmine'], $s);
        $this->rdv($p, $d['yasmine'], 'Détartrage', $j(0, 9), 45, 'termine', ['acte_id' => $a->id]);
        $this->payer($p, 200, 'especes', 0);
        $p->dossier->update(['prochain_controle' => now()->addMonths(6)->toDateString(), 'statut' => 'en_suivi']);

        // 6 · Absent hier (RDV manqué) → à reprogrammer
        $p = $this->patient('FASSI', 'Nadia', '0612000106', '1988-09-30');
        $this->consultationPassee($p, $d['nassim'], 20, 'Caries multiples');
        $c = $this->acte($p, 'Soin conservateur (composite)', '15, 25', 'a_faire', $d['nassim']);
        $this->rdv($p, $d['ilham'], 'Soin carie · 15, 25', $j(-1, 11), 60, 'absent', ['acte_id' => $c->id]);

        // 7 · Implant : consultation faite, radio faite, implant chez Dr Malika la semaine prochaine
        $p = $this->patient('GHAZALI', 'Ahmed', '0612000107', '1965-04-08', ['antecedents' => ['diabete'], 'traitements' => 'Metformine']);
        $this->consultationPassee($p, $d['nassim'], 10, 'Absence 46, os suffisant');
        $this->radio($p, 'panoramique', 'Os 11 mm en 46');
        $this->dent($p, 46, 'absente');
        $i = $this->acte($p, 'Implant', '46', 'a_faire', $d['nassim']);
        $this->acte($p, 'Couronne zircone', '46', 'a_faire', $d['nassim']);
        $this->rdv($p, $d['malika'], 'Implant · 46', $j(6, 9), 120, 'planifie', ['acte_id' => $i->id]);
        $this->payer($p, 3000, 'cheque', 0);

        // 8 · Prothèse : couronne au labo « en fabrication », pose fixée chez Dr Omar
        $p = $this->patient('HAJJI', 'Fatima', '0612000108', '1958-11-19');
        $this->consultationPassee($p, $d['nassim'], 30, 'Dent 21 fracturée');
        $s = $this->seance($p, $d['ilham'], $j(-15, 15), 60, 'Dévitalisation 21 + préparation', 'Couronne E.max au labo');
        $this->acte($p, 'Traitement de canal', '21', 'fait', $d['ilham'], $s);
        $cour = $this->acte($p, 'Couronne E.max', '21', 'a_faire', $d['ilham']);
        $p->dossier->commandesLabo()->create(['acte_id' => $cour->id, 'travail' => 'Couronne E.max', 'dents' => '21', 'teinte' => 'A1', 'statut' => 'en_fabrication', 'envoye_par' => $d['ilham']->id, 'technicien_id' => $this->labo?->id, 'date_pose' => $j(4, 15)->toDateString(), 'date_prete' => $j(3, 9)->toDateString(), 'acceptee_le' => now()]);
        $this->rdv($p, $d['omar'], 'Pose couronne · 21', $j(4, 15), 60, 'planifie', ['acte_id' => $cour->id]);
        $this->payer($p, 2000, 'especes', 0);

        // 9 · Labo « Prêt » sans RDV de pose → demande « RDV à fixer » chez la réception
        $p = $this->patient('IDRISSI', 'Rachid', '0612000109', '1973-02-02');
        $this->consultationPassee($p, $d['nassim'], 25, 'Bridge 24-26');
        $b = $this->acte($p, 'Bridge céramo-métallique', '24-26', 'en_cours', $d['omar']);
        $p->dossier->commandesLabo()->create(['acte_id' => $b->id, 'travail' => 'Bridge céramo-métallique', 'dents' => '24-26', 'teinte' => 'A3', 'statut' => 'pret', 'pret_le' => now(), 'envoye_par' => $d['omar']->id, 'technicien_id' => $this->labo?->id, 'acceptee_le' => now()]);
        RdvAFixer::create(['patient_id' => $p->id, 'docteur_id' => $d['omar']->id, 'acte_id' => $b->id, 'mission' => 'Pose Bridge céramo-métallique · 24-26', 'notes' => 'Travail prêt au labo : appeler le patient pour la pose.', 'demande_par' => $this->labo?->id]);

        // 10 · Orthodontie chez Dr Oualid (suivi mensuel)
        $p = $this->patient('JABRI', 'Lina', '0612000110', now()->subYears(14)->toDateString(), ['ville' => 'Marrakech']);
        $this->consultationPassee($p, $d['nassim'], 60, 'Encombrement dentaire, orthodontie');
        $s = $this->seance($p, $d['oualid'], $j(-30, 16), 60, 'Pose appareil orthodontique', 'Contrôle chaque mois');
        $this->acte($p, 'Orthodontie (appareil fixe)', null, 'en_cours', $d['oualid'], $s);
        $this->rdv($p, $d['oualid'], 'Contrôle ortho', $j(2, 17), 30);

        // 11 · Enfant 8 ans : dents de lait (schéma mixte)
        $p = $this->patient('KETTANI', 'Adam', '0612000111', now()->subYears(8)->toDateString());
        $this->dent($p, 75, 'carie');
        $this->dent($p, 84, 'carie');
        $this->dent($p, 16, 'a_surveiller');
        $this->rdv($p, $d['yasmine'], 'Soin carie dents de lait · 75, 84', $j(1, 10), 45);

        // 12 · Réservé EN LIGNE par le patient pour demain
        $p = $this->patient('LAHLOU', 'Meryem', '0612000112', null, [], ['fiche_a_completer' => true]);
        $this->rdv($p, $d['nassim'], 'Consultation', $j(1, 11), 10, 'planifie', ['en_ligne' => true, 'cree_par' => null]);

        // 13 · Le docteur a demandé un RDV que la réception doit fixer
        $p = $this->patient('MANSOURI', 'Driss', '0612000113', '1969-07-07');
        $this->consultationPassee($p, $d['nassim'], 3, 'Extraction 38 incluse');
        $e = $this->acte($p, 'Extraction chirurgicale', '38', 'a_faire', $d['nassim']);
        RdvAFixer::create(['patient_id' => $p->id, 'docteur_id' => $d['malika']->id, 'acte_id' => $e->id, 'mission' => 'Extraction chirurgicale · 38', 'notes' => 'Plutôt le matin, patient diabétique : à jeun interdit', 'demande_par' => $d['nassim']->id]);

        // 14 · Radio demandée, en attente chez Ikram (patient en salle d'attente)
        $p = $this->patient('NACIRI', 'Imane', '0612000114', '1996-05-21');
        DemandeRadio::create(['dossier_id' => $p->dossier->id, 'type' => 'panoramique', 'notes' => 'Bilan avant soins', 'demande_par' => $d['nassim']->id]);
        $this->rdv($p, $d['nassim'], 'Consultation', $now->copy()->subMinutes(2), 10, 'arrive', ['numero_appel' => 4, 'arrive_le' => $now->copy()->subMinutes(12)]);

        // 15 · Impayé important, allergie
        $p = $this->patient('OUAZZANI', 'Hamid', '0612000115', '1960-10-10', ['allergies' => 'Latex, aspirine', 'antecedents' => ['cardiaque', 'anticoagulants']]);
        $this->consultationPassee($p, $d['nassim'], 90, 'Édentement partiel');
        $s = $this->seance($p, $d['omar'], $j(-40, 10), 60, 'Empreintes prothèse', null);
        $this->acte($p, 'Prothèse amovible (stellite)', null, 'fait', $d['omar'], $s);
        $this->payer($p, 1000, 'especes', 0);

        // 16 · Tout soldé, dossier « en suivi »
        $p = $this->patient('QADIRI', 'Sanae', '0612000116', '1990-08-08');
        $s = $this->seance($p, $d['yasmine'], $j(-60, 10), 60, 'Blanchiment', 'Contrôle dans 1 an');
        $this->acte($p, 'Blanchiment', null, 'fait', $d['yasmine'], $s);
        $this->payer($p, 2500, 'carte', 0);
        $p->dossier->update(['statut' => 'en_suivi', 'prochain_controle' => now()->addMonths(10)->toDateString()]);

        // 17 · Fiche remplie sur la tablette, à valider par la réception
        Patient::create(['nom' => 'RAMI', 'prenom' => 'Youssef', 'telephone' => '0612000117', 'date_naissance' => '1987-03-03', 'ville' => 'Marrakech', 'statut_fiche' => 'a_valider', 'antecedents' => ['fumeur']]);

        // 18 · Exception : consultation avec Dr Malika (au lieu de Dr Nassime) à la demande de la patiente
        $p = $this->patient('SBAI', 'Khadija', '0612000118', '1975-12-12');
        $this->rdv($p, $d['malika'], 'Consultation (demande de la patiente)', $j(2, 9), 30);

        // 19 · Patient à rappeler : acte à faire sans aucun RDV
        $p = $this->patient('TAHIRI', 'Mehdi', '0612000119', '1982-01-27');
        $this->consultationPassee($p, $d['nassim'], 45, 'Couronne à refaire 14');
        $this->acte($p, 'Couronne céramo-métallique', '14', 'a_faire', $d['nassim']);

        // 20 · Ordonnance faite, contrôle dans 1 semaine
        $p = $this->patient('WAHBI', 'Amal', '0612000120', '1993-04-17');
        $s = $this->seance($p, $d['malika'], $j(-2, 10), 60, 'Extraction 48', 'Contrôle J+7');
        $this->acte($p, 'Extraction chirurgicale', '48', 'fait', $d['malika'], $s);
        $p->dossier->ordonnances()->create(['docteur_id' => $d['malika']->id, 'date' => $j(-2, 10)->toDateString(), 'lignes' => [
            ['medicament' => 'Amoxicilline 1 g', 'posologie' => '1 comprimé matin et soir', 'duree' => '6 jours'],
            ['medicament' => 'Paracétamol 1 g', 'posologie' => '1 comprimé jusqu’à 3 fois par jour si douleur', 'duree' => '3 jours'],
        ]]);
        $this->rdv($p, $d['malika'], 'Contrôle', $j(5, 11), 30);

        // 21 · RDV annulé par le patient, nouveau RDV dans 2 semaines
        $p = $this->patient('YAMANI', 'Soufiane', '0612000121', '1999-09-09');
        $this->rdv($p, $d['nassim'], 'Consultation', $j(-3, 15), 10, 'annule');
        $this->rdv($p, $d['nassim'], 'Consultation', $j(14, 15), 10);

        // 22 · Implant posé il y a 3 mois, pose des piliers chez Dr Omar dans 3 jours
        $p = $this->patient('ZERHOUNI', 'Mustapha', '0612000122', '1962-06-06');
        $s = $this->seance($p, $d['malika'], $j(-90, 9), 120, 'Pose implant 36', 'Ostéo-intégration 3 mois');
        $this->acte($p, 'Implant', '36', 'fait', $d['malika'], $s);
        $this->dent($p, 36, 'implant');
        $pil = $this->acte($p, 'Pose de piliers', '36', 'a_faire', $d['malika']);
        $this->rdv($p, $d['omar'], 'Pose de piliers · 36', $j(3, 11), 60, 'planifie', ['acte_id' => $pil->id]);
        $this->payer($p, 5000, 'virement', 0);

        // 23 · All on 4 : grand plan de traitement (chirurgie + prothèse)
        $p = $this->patient('BENJELLOUN', 'Aïcha', '0612000123', '1955-02-14', ['antecedents' => ['hypertension']]);
        $this->consultationPassee($p, $d['nassim'], 7, 'Édentation maxillaire complète : All on 4');
        $this->radio($p, 'cone_beam', 'Volume osseux suffisant');
        $a4 = $this->acte($p, 'All on 4 (chirurgie)', 'maxillaire', 'a_faire', $d['nassim']);
        $this->acte($p, 'All on 4 (prothèse)', 'maxillaire', 'a_faire', $d['nassim']);
        $this->rdv($p, $d['malika'], 'All on 4 · chirurgie', $j(10, 9), 180, 'planifie', ['acte_id' => $a4->id]);

        // 24 · Prochain RDV en fin de journée chez Dr Yasmine (reprise endo)
        $p = $this->patient('CHERKAOUI', 'Ilyas', '0612000124', '1986-10-31');
        $this->consultationPassee($p, $d['nassim'], 5, 'Ancienne dévitalisation 46 infectée');
        $re = $this->acte($p, 'Reprise de traitement de canal', '46', 'a_faire', $d['nassim']);
        $this->rdv($p, $d['yasmine'], 'Reprise endo · 46', $now->copy()->addHours(2), 60, 'planifie', ['acte_id' => $re->id]);

        $this->info(Patient::count().' patients au total. Ouvrez la salle d’attente, l’agenda, « RDV à fixer », le labo, la radiologie et les impayés.');

        return self::SUCCESS;
    }

    /* ---------------- outils ---------------- */

    private function patient(string $nom, string $prenom, string $tel, ?string $naissance, array $info = [], array $extra = []): Patient
    {
        $p = Patient::create(['nom' => $nom, 'prenom' => $prenom, 'telephone' => $tel, 'date_naissance' => $naissance, 'ville' => $info['ville'] ?? 'Marrakech',
            'allergies' => $info['allergies'] ?? null, 'antecedents' => $info['antecedents'] ?? [], 'traitements' => $info['traitements'] ?? null,
            'statut_fiche' => 'validee', 'valide_par' => $this->rec?->id, 'valide_le' => now()] + $extra);
        Dossier::create(['patient_id' => $p->id, 'cree_par' => $this->rec?->id, 'docteur_consultation_id' => $this->doc['nassim']->id]);

        return $p->fresh('dossier');
    }

    private function rdv(Patient $p, ?User $doc, string $mission, Carbon $debut, int $min, string $statut = 'planifie', array $extra = []): RendezVous
    {
        $r = RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc?->id, 'mission' => $mission, 'debut' => $debut, 'fin' => $debut->copy()->addMinutes($min),
            'statut' => $statut, 'cree_par' => $this->rec?->id] + $extra);
        // Comme dans la vraie prise de RDV : la tâche du docteur dans « À faire »
        if (in_array($statut, ['planifie', 'arrive'], true) && ! $r->acte_id) {
            $p->dossier->acteDeVisite($r, $this->rec?->id);
        }

        return $r;
    }

    private function seance(Patient $p, User $doc, Carbon $debut, int $min, string $cr, ?string $suite): Seance
    {
        return Seance::create(['dossier_id' => $p->dossier->id, 'docteur_id' => $doc->id, 'debut' => $debut, 'fin' => $debut->copy()->addMinutes($min),
            'duree_minutes' => $min, 'compte_rendu' => $cr, 'suite' => $suite]);
    }

    private function consultationPassee(Patient $p, User $doc, int $ilYaJours, string $diagnostic): void
    {
        $s = $this->seance($p, $doc, now()->subDays($ilYaJours)->setTime(10, 0), 15, 'Examen clinique', $diagnostic);
        $this->acte($p, 'Consultation + plan de traitement', null, 'fait', $doc, $s);
        $p->dossier->update(['diagnostic' => $diagnostic, 'statut' => 'en_traitement']);
    }

    private function acte(Patient $p, string $libelle, ?string $dents, string $statut, User $par, ?Seance $s = null)
    {
        $cat = CatalogueActe::where('libelle', $libelle)->first() ?? CatalogueActe::where('libelle', 'like', explode(' (', $libelle)[0].'%')->first();
        $prix = $cat?->prix_defaut ?? match (true) {
            str_contains($libelle, 'All on 4') => 35000, str_contains($libelle, 'Orthodontie') => 15000, str_contains($libelle, 'Bridge') => 6000,
            str_contains($libelle, 'Prothèse') => 4000, str_contains($libelle, 'piliers') => 1500, str_contains($libelle, 'Reprise') => 1800,
            str_contains($libelle, 'Extraction') => 800, default => 500,
        };

        return $p->dossier->actes()->create(['catalogue_acte_id' => $cat?->id, 'libelle' => $libelle, 'dents' => $dents, 'statut' => $statut, 'prix_convenu' => $prix,
            'prevu_par' => $par->id, 'realise_par' => $statut === 'fait' ? $par->id : null, 'seance_id' => $s?->id, 'fait_le' => $statut === 'fait' ? ($s?->fin ?? now()) : null]);
    }

    private function payer(Patient $p, float $montant, string $methode, int $ilYaJours): void
    {
        $p->dossier->paiements()->create(['montant' => $montant, 'methode' => $methode, 'date' => now()->subDays($ilYaJours)->toDateString(),
            'reste_apres' => max(0, $p->dossier->reste() - $montant), 'encaisse_par' => $this->fin?->id]);
    }

    private function radio(Patient $p, string $type, string $mesures): void
    {
        Radio::create(['dossier_id' => $p->dossier->id, 'type' => $type, 'mesures' => $mesures, 'faite_par' => $this->ikram?->id]);
    }

    private function dent(Patient $p, int $dent, string $etat): void
    {
        $p->dossier->etatsDents()->updateOrCreate(['dent' => $dent], ['etat' => $etat]);
    }
}

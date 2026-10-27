<?php

namespace Database\Seeders;

use App\Enums\EtatDocteur;
use App\Enums\Role;
use App\Enums\StatutActe;
use App\Enums\StatutLabo;
use App\Enums\StatutRdv;
use App\Models\CatalogueActe;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Données de démonstration (noms fictifs). Mot de passe de tous les comptes : password
     * À supprimer avant la mise en service réelle.
     */
    public function run(): void
    {
        $u = fn ($name, $email, Role $role, $extra = []) => User::updateOrCreate(['email' => $email], [
            'name' => $name, 'password' => 'password', 'role' => $role, 'actif' => true, 'email_verified_at' => now(),
        ] + $extra);

        foreach ([['Salle 1', 'Fauteuil, radio rétro-alvéolaire'], ['Salle 2', 'Fauteuil, caméra intra-orale'], ['Salle 3', 'Fauteuil, bloc chirurgie implants']] as $i => [$nom, $eq]) {
            \App\Models\Salle::create(['nom' => $nom, 'equipement' => $eq, 'ordre' => $i + 1]);
        }

        $u('Chef du centre', 'chef@wesmile.ma', Role::Admin);
        $u('Réception', 'reception@wesmile.ma', Role::Reception);
        $nassim = $u('Dr Nassim', 'nassim@wesmile.ma', Role::Docteur, ['specialite' => 'Consultation, soins', 'couleur' => '#138c8f', 'salle' => 'Salle 1', 'etat' => EtatDocteur::Pret,
            'fait_consultation' => true, 'taches_principales' => 'Consultations, bilans, plan de traitement', 'taches_secondaires' => 'Détartrage, soins de carie']);
        $amrani = $u('Dr Amrani', 'amrani@wesmile.ma', Role::Docteur, ['specialite' => 'Prothèse, esthétique', 'couleur' => '#6db33f', 'salle' => 'Salle 2', 'etat' => EtatDocteur::Occupe,
            'taches_principales' => 'Couronnes, facettes, Hollywood Smile']);
        $berrada = $u('Dr Berrada', 'berrada@wesmile.ma', Role::Docteur, ['specialite' => 'Implants, chirurgie', 'couleur' => '#2f6fb3', 'salle' => 'Salle 3', 'etat' => EtatDocteur::Pause]);
        $u('Finance', 'finance@wesmile.ma', Role::Finance);
        $u('Laboratoire', 'labo@wesmile.ma', Role::Labo);
        $u('Photographe', 'photo@wesmile.ma', Role::Photographe);
        $ikram = $u('Ikram', 'ikram@wesmile.ma', Role::Radio);

        foreach ([$nassim, $amrani, $berrada] as $d) {
            $d->disponibilites()->delete();
            foreach (range(1, 6) as $jour) {
                $d->disponibilites()->create(['jour_semaine' => $jour, 'heure_debut' => '09:00', 'heure_fin' => '13:00']);
                if ($jour <= 5) {
                    $d->disponibilites()->create(['jour_semaine' => $jour, 'heure_debut' => '15:00', 'heure_fin' => '19:00']);
                }
            }
        }

        $catalogue = [
            ['Consultation + plan de traitement', 'Consultation, soins', 300, 30, false],
            ['Détartrage', 'Consultation, soins', 400, 30, false],
            ['Soin conservateur (composite)', 'Consultation, soins', 600, 45, false],
            ['Traitement de canal', 'Consultation, soins', 1500, 60, false],
            ['Extraction', 'Implants, chirurgie', 500, 30, false],
            ['Couronne céramo-métallique', 'Prothèse, esthétique', 2500, 45, true],
            ['Couronne E.max', 'Prothèse, esthétique', 3000, 45, true],
            ['Couronne zircone', 'Prothèse, esthétique', 3500, 45, true],
            ['Facette céramique (Hollywood Smile)', 'Prothèse, esthétique', 4000, 60, true],
            ['Blanchiment', 'Prothèse, esthétique', 2500, 60, false],
            ['Implant', 'Implants, chirurgie', 8000, 90, true],
            ['Radio panoramique', 'Consultation, soins', 200, 15, false],
        ];
        foreach ($catalogue as [$libelle, $spec, $prix, $duree, $labo]) {
            CatalogueActe::updateOrCreate(['libelle' => $libelle], ['specialite' => $spec, 'prix_defaut' => $prix, 'duree_minutes' => $duree, 'necessite_labo' => $labo]);
        }

        if (Patient::exists()) {
            return;
        }

        // Patients fictifs
        $mk = function ($nom, $prenom, $ville, $tel, $allergies = null) {
            $p = Patient::create(['nom' => $nom, 'prenom' => $prenom, 'ville' => $ville, 'telephone' => $tel,
                'allergies' => $allergies, 'statut_fiche' => 'validee', 'valide_le' => now(), 'date_naissance' => now()->subYears(rand(20, 60))]);
            $p->setRelation('dossier', Dossier::create(['patient_id' => $p->id]));

            return $p;
        };
        $sara = $mk('Benali', 'Sara', 'Marrakech', '0600000001', 'Pénicilline');
        $karim = $mk('Ziani', 'Karim', 'Oujda', '0600000002');
        $omar = $mk('Alaoui', 'Omar', 'Marrakech', '0600000003');
        $salma = $mk('Kadiri', 'Salma', 'Safi', '0600000004');
        Patient::create(['nom' => 'Tazi', 'prenom' => 'Youssef', 'ville' => 'Marrakech', 'telephone' => '0600000005', 'statut_fiche' => 'a_valider']);

        $acte = fn (Patient $p, $libelle, $dents, $prix, StatutActe $st, $doc) => $p->dossier->actes()->create([
            'libelle' => $libelle, 'dents' => $dents, 'prix_convenu' => $prix, 'statut' => $st, 'prevu_par' => $nassim->id,
            'realise_par' => $st === StatutActe::Fait ? $doc->id : null, 'fait_le' => $st === StatutActe::Fait ? now()->subDays(5) : null,
        ]);

        $acte($sara, 'Consultation + plan de traitement', null, 300, StatutActe::Fait, $nassim);
        $couronnes = $acte($sara, 'Couronne E.max', '16, 26', 6000, StatutActe::EnCours, $amrani);
        $acte($sara, 'Détartrage', null, 400, StatutActe::AFaire, $amrani);
        $sara->dossier->update(['docteur_consultation_id' => $nassim->id, 'diagnostic' => 'Délabrement 16 et 26, tartre généralisé.']);
        $paiement = $sara->dossier->paiements()->create(['montant' => 3000, 'methode' => 'especes', 'date' => now()->subDays(5), 'encaisse_par' => User::firstWhere('role', Role::Finance->value)->id]);
        $paiement->update(['reste_apres' => $sara->dossier->reste()]);

        // Radio faite par Ikram, reçue par le labo avec les mesures
        $karim->dossier->radios()->create([
            'type' => 'panoramique', 'dents' => '36', 'faite_par' => $ikram->id,
            'mesures' => 'Couronne 36 : largeur mésio-distale 10,8 mm · hauteur 7,5 mm',
            'notes' => 'Patient de Oujda : pose prévue demain.',
        ]);

        $kc = $acte($karim, 'Couronne zircone', '36', 3500, StatutActe::EnCours, $amrani);
        $omarFac = $acte($omar, 'Facette céramique (Hollywood Smile)', '13, 12, 11, 21, 22, 23', 24000, StatutActe::AFaire, $amrani);
        $acte($salma, 'Implant', '46', 8000, StatutActe::AFaire, $berrada);

        $today = now()->startOfDay();
        $rdv = fn (Patient $p, User $d, $h, $mission, StatutRdv $st = StatutRdv::Planifie, $jour = 0, $acteId = null, $extra = []) => RendezVous::create([
            'patient_id' => $p->id, 'docteur_id' => $d->id, 'mission' => $mission, 'acte_id' => $acteId,
            'debut' => $today->addDays($jour)->setTimeFromTimeString($h), 'fin' => $today->addDays($jour)->setTimeFromTimeString($h)->addMinutes(45),
            'statut' => $st,
        ] + $extra);

        $rdv($sara, $amrani, '09:30', 'Pose couronnes 16, 26', StatutRdv::Arrive, 0, $couronnes->id, ['arrive_le' => now()->subMinutes(8), 'numero_appel' => 1]);
        $rdv($omar, $nassim, '10:30', 'Hollywood Smile · séance 2', StatutRdv::Planifie, 0, $omarFac->id);
        $rdv($salma, $berrada, '11:30', 'Implant 46 · pose', StatutRdv::Planifie);
        $rdv($karim, $amrani, '14:30', 'Pose couronne 36', StatutRdv::Planifie, 1, $kc->id);

        $sara->dossier->commandesLabo()->create(['acte_id' => $couronnes->id, 'travail' => 'Couronnes E.max', 'dents' => '16, 26', 'teinte' => 'A2', 'materiau' => 'E.max',
            'date_pose' => now()->toDateString(), 'statut' => StatutLabo::Pret, 'pret_le' => now()->subDay(), 'envoye_par' => $amrani->id]);
        $karim->dossier->commandesLabo()->create(['acte_id' => $kc->id, 'travail' => 'Couronne zircone', 'dents' => '36', 'teinte' => 'A3', 'materiau' => 'Zircone',
            'date_pose' => now()->addDay()->toDateString(), 'statut' => StatutLabo::EnFabrication, 'envoye_par' => $amrani->id]);
        $omar->dossier->commandesLabo()->create(['acte_id' => $omarFac->id, 'travail' => 'Facettes céramique', 'dents' => '13 à 23', 'teinte' => 'BL2',
            'statut' => StatutLabo::AFaire, 'envoye_par' => $amrani->id]);
    }
}

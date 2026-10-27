<?php

namespace Tests\Feature;

use App\Enums\EtatDocteur;
use App\Enums\Role;
use App\Enums\StatutActe;
use App\Enums\StatutLabo;
use App\Enums\StatutRdv;
use App\Http\Controllers\SeanceController;
use App\Models\Acte;
use App\Models\CommandeLabo;
use App\Models\Dossier;
use App\Models\Patient;
use App\Models\RendezVous;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CabinetTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $role, array $extra = []): User
    {
        return User::factory()->role($role)->create($extra);
    }

    private function docteurDisponible(): User
    {
        $d = $this->user(Role::Docteur, ['name' => 'Dr Test', 'salle' => 'Salle 2']);
        foreach (range(1, 7) as $j) {
            $d->disponibilites()->create(['jour_semaine' => $j, 'heure_debut' => '08:00', 'heure_fin' => '20:00']);
        }

        return $d;
    }

    private function patientAvecDossier(array $attrs = []): Patient
    {
        $p = Patient::create($attrs + ['nom' => 'Ziani', 'prenom' => 'Karim', 'telephone' => '0611', 'ville' => 'Oujda', 'statut_fiche' => 'validee']);
        Dossier::create(['patient_id' => $p->id]);

        return $p->fresh('dossier');
    }

    public function test_chaque_role_arrive_sur_sa_page(): void
    {
        $pages = [
            [Role::Reception, 'salle-attente.index'], [Role::Docteur, 'docteur.patients'], [Role::Finance, 'patients.index'], [Role::Radio, 'radio.index'],
            [Role::Labo, 'labo.index'], [Role::Photographe, 'patients.index'], [Role::Admin, 'admin.tableau-de-bord'],
        ];
        foreach ($pages as [$role, $route]) {
            $u = $this->user($role);
            $this->actingAs($u)->get('/dashboard')->assertRedirect(route($route));
            $this->actingAs($u)->get(route($route))->assertOk();
        }
    }

    public function test_les_droits_par_role(): void
    {
        $this->actingAs($this->user(Role::Labo, ['chef_labo' => true]))->get('/patients')->assertOk(); // la cheffe du labo voit les dossiers
        $this->actingAs($this->user(Role::Labo))->get('/patients')->assertForbidden(); // les autres : seulement leurs travaux
        $this->actingAs($this->user(Role::Labo))->get('/fiches')->assertForbidden();
        $this->actingAs($this->user(Role::Docteur))->get('/caisse')->assertForbidden();
        $this->actingAs($this->user(Role::Docteur))->get('/agenda')->assertForbidden();
        $this->actingAs($this->user(Role::Finance))->get('/labo')->assertForbidden();
        $this->actingAs($this->user(Role::Reception))->get('/admin/utilisateurs')->assertForbidden();
        $this->actingAs($this->user(Role::Admin))->get('/labo')->assertOk();
    }

    public function test_compte_desactive_ne_peut_rien_faire(): void
    {
        $u = $this->user(Role::Reception, ['actif' => false]);
        $this->actingAs($u)->get('/salle-attente')->assertRedirect(route('login'));
    }

    public function test_parcours_nouveau_patient_complet(): void
    {
        Storage::fake('local');
        $reception = $this->user(Role::Reception);
        $docteur = $this->docteurDisponible();
        $finance = $this->user(Role::Finance);

        // 1. Le patient remplit sa fiche sur la tablette (sans connexion)
        $this->post('/accueil', ['nom' => 'Ziani', 'prenom' => 'Karim', 'telephone' => '0611223344', 'ville' => 'Oujda', 'antecedents' => ['diabete'], 'allergies' => 'Pénicilline'])
            ->assertRedirect('/accueil/merci?lang=fr');
        $patient = Patient::firstWhere('nom', 'Ziani');
        $this->assertSame('a_valider', $patient->statut_fiche);

        // 2. La réception valide : dossier créé
        $this->actingAs($reception)->get('/fiches')->assertOk();
        $this->actingAs($reception)->post("/fiches/{$patient->id}/valider", ['nom' => 'Ziani', 'prenom' => 'Karim', 'telephone' => '0611223344', 'ville' => 'Oujda', 'cin' => 'AB123'])
            ->assertRedirect();
        $dossier = $patient->fresh()->dossier;
        $this->assertNotNull($dossier);
        $this->actingAs($reception)->post("/patients/{$patient->id}/documents", ['type' => 'cin', 'fichiers' => [UploadedFile::fake()->image('cin.jpg')]])->assertRedirect();
        $this->assertCount(1, $patient->documents);

        // 3. Sans RDV → salle d'attente → le docteur est prêt → envoi
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['patient_id' => $patient->id, 'mission' => 'Consultation', 'urgence' => true])->assertRedirect();
        $rdv = RendezVous::first();
        $this->assertSame(StatutRdv::Arrive, $rdv->statut);
        $this->assertSame(1, $rdv->numero_appel);

        $this->actingAs($docteur)->post('/mon-etat', ['etat' => 'pret'])->assertRedirect();
        $this->assertSame(EtatDocteur::Pret, $docteur->fresh()->etat);

        $this->actingAs($reception)->post("/rendez-vous/{$rdv->id}/envoyer", ['docteur_id' => $docteur->id])->assertRedirect();
        $this->assertSame(StatutRdv::EnCours, $rdv->fresh()->statut);
        $this->assertSame(EtatDocteur::Occupe, $docteur->fresh()->etat);
        $seance = Seance::first();
        $this->assertNotNull($seance->debut);
        $this->actingAs($reception)->get('/tv')->assertOk();

        // 4. Le docteur ouvre le dossier, ajoute le plan, termine la séance
        $this->actingAs($docteur)->get("/dossiers/{$dossier->id}")->assertOk();
        $this->actingAs($docteur)->post("/dossiers/{$dossier->id}/actes", ['libelle' => 'Consultation', 'prix_convenu' => 300])->assertRedirect();
        $this->actingAs($docteur)->post("/dossiers/{$dossier->id}/actes", ['libelle' => 'Couronne zircone', 'dents' => '36', 'prix_convenu' => 3500])->assertRedirect();
        [$consult, $couronne] = Acte::orderBy('id')->get()->all();
        // le docteur ne fixe pas les prix : c'est la finance qui le fait
        $this->assertEquals(0, $couronne->prix_convenu);
        $this->actingAs($finance)->put("/actes/{$consult->id}", ['prix_convenu' => 300])->assertRedirect();
        $this->actingAs($finance)->put("/actes/{$couronne->id}", ['prix_convenu' => 3500])->assertRedirect();
        $this->actingAs($docteur)->put("/actes/{$couronne->id}", ['prix_convenu' => 10])->assertForbidden();

        $this->actingAs($docteur)->post("/dossiers/{$dossier->id}/dents", ['dent' => 36, 'etat' => 'couronne'])->assertRedirect();
        $this->actingAs($docteur)->post("/seances/{$seance->id}/terminer", [
            'compte_rendu' => 'Examen', 'suite' => 'Couronne 36 → labo', 'actes_faits' => [$consult->id], 'actes_en_cours' => [$couronne->id],
        ])->assertRedirect();

        $this->assertNotNull($seance->fresh()->fin);
        $this->assertSame(StatutActe::Fait, $consult->fresh()->statut);
        $this->assertSame($docteur->id, $consult->fresh()->realise_par);
        $this->assertSame(StatutActe::EnCours, $couronne->fresh()->statut);
        $this->assertSame(EtatDocteur::Pret, $docteur->fresh()->etat);

        // 5. RDV de pose (réception) puis labo : date de pose et priorité
        $this->actingAs($docteur)->post("/dossiers/{$dossier->id}/labo", ['demande' => "Couronne zircone 36\nTeinte A3"])->assertSessionHasNoErrors();
        $couronne = CommandeLabo::first()->acte; // la demande écrite est reliée à l'acte « Couronne zircone »
        $this->assertSame('Couronne zircone', $couronne->libelle);
        $this->actingAs($reception)->post('/agenda', ['patient_id' => $patient->id, 'docteur_id' => $docteur->id, 'acte_id' => $couronne->id, 'date' => now()->addDay()->toDateString(), 'heure' => '10:00', 'duree' => 45])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, RendezVous::where('acte_id', $couronne->id)->count(), 'rdv pose créé');
        $commande = CommandeLabo::first();
        $this->assertSame(now()->addDay()->toDateString(), $commande->date_pose->toDateString());
        $this->assertSame('urgent', $commande->priorite());

        $labo = $this->user(Role::Labo, ['chef_labo' => true]);
        $this->actingAs($labo)->get('/labo')->assertOk();
        $this->actingAs($labo)->put("/labo/{$commande->id}", ['statut' => 'pret'])->assertRedirect();
        $this->assertSame(StatutLabo::Pret, $commande->fresh()->statut);

        // 6. Finance : paiement, reçu numéroté, reste calculé
        $this->actingAs($finance)->post("/dossiers/{$dossier->id}/paiements", ['montant' => 1000, 'methode' => 'especes', 'date' => now()->toDateString()])->assertRedirect();
        $paiement = $dossier->paiements()->first();
        $this->assertSame('002201', $paiement->numero_recu);
        $this->assertEquals(2800, $paiement->reste_apres);
        $this->actingAs($finance)->get("/paiements/{$paiement->id}/recu")->assertOk()->assertSee('002201');
        $this->actingAs($finance)->get('/impayes')->assertForbidden(); // seuls les totaux du chef
        $this->actingAs($this->user(Role::Admin))->get('/impayes')->assertOk();

        // Journal : tout est tracé
        $this->assertDatabaseHas('journal', ['sujet_type' => 'Paiement', 'user_id' => $finance->id]);
    }

    public function test_agenda_refuse_hors_horaires_et_chevauchement(): void
    {
        $reception = $this->user(Role::Reception);
        $docteur = $this->user(Role::Docteur);
        $docteur->disponibilites()->create(['jour_semaine' => now()->addDay()->dayOfWeekIso, 'heure_debut' => '09:00', 'heure_fin' => '12:00']);
        $patient = $this->patientAvecDossier();
        $base = ['patient_id' => $patient->id, 'docteur_id' => $docteur->id, 'mission' => 'Contrôle', 'date' => now()->addDay()->toDateString(), 'duree' => 30];

        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '15:00'])->assertSessionHasErrors('heure');
        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '10:00'])->assertSessionHasNoErrors();
        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '10:15'])->assertSessionHasErrors('heure');
        $this->assertSame(1, RendezVous::count());
    }

    public function test_patient_qui_revient_alertes_et_recherche(): void
    {
        $reception = $this->user(Role::Reception);
        $docteur = $this->user(Role::Docteur);
        $patient = $this->patientAvecDossier(['allergies' => 'Latex', 'ville' => 'Marrakech']);
        $patient->dossier->update(['ancien_numero_papier' => '2415']);
        $patient->dossier->actes()->create(['libelle' => 'Détartrage', 'prix_convenu' => 800, 'statut' => 'a_faire']);

        $this->actingAs($reception)->get('/recherche-patients?q=2415')->assertOk()->assertJsonFragment(['nom' => 'Ziani']);

        $alertes = collect($patient->dossier->alertes())->pluck('texte')->implode(' | ');
        $this->assertStringContainsString('Latex', $alertes);
        $this->assertStringContainsString('800', $alertes);
        $this->assertStringContainsString('sans rendez-vous', $alertes);

        // Changement de téléphone : l'ancien est gardé
        $this->actingAs($reception)->put("/patients/{$patient->id}", ['nom' => 'Ziani', 'prenom' => 'Karim', 'telephone' => '0799'])->assertRedirect();
        $this->assertSame(['0611'], $patient->fresh()->anciens_telephones);
    }

    public function test_admin_cree_un_docteur_qui_peut_se_connecter(): void
    {
        $admin = $this->user(Role::Developpeur);
        $this->actingAs($admin)->post('/admin/utilisateurs', [
            'name' => 'Dr Nouveau', 'email' => 'nouveau@wesmile.ma', 'role' => 'docteur', 'password' => 'motdepasse1', 'actif' => true, 'specialite' => 'Orthodontie',
        ])->assertRedirect();
        auth()->logout();
        $this->post('/login', ['email' => 'nouveau@wesmile.ma', 'password' => 'motdepasse1']);
        $this->assertAuthenticated();
    }

    public function test_le_seeder_de_demo_fonctionne(): void
    {
        $this->seed();
        $this->assertSame(9, User::count());
        $reception = User::firstWhere('email', 'reception@wesmile.ma');
        $this->actingAs($reception)->get('/salle-attente')->assertOk();
        $this->actingAs($reception)->get('/agenda')->assertOk();
        $this->actingAs(User::firstWhere('email', 'amrani@wesmile.ma'))->get('/mes-patients')->assertOk();
        $this->actingAs(User::firstWhere('email', 'chef@wesmile.ma'))->get('/admin/tableau-de-bord')->assertOk();
        foreach (Dossier::all() as $d) {
            $this->actingAs($reception)->get("/dossiers/{$d->id}")->assertOk();
        }
    }

    public function test_fiche_tablette_visible_chez_reception_et_chef_avec_cin(): void
    {
        Storage::fake('local');
        $reception = $this->user(Role::Reception);
        $chef = $this->user(Role::Admin);

        $this->post('/accueil', ['nom' => 'Idrissi', 'prenom' => 'Nora', 'telephone' => '0600000000'])->assertRedirect('/accueil/merci?lang=fr');
        $patient = Patient::firstWhere('nom', 'Idrissi');

        // Badge du menu + page des fiches, pour la réception et le chef
        $this->actingAs($reception)->get('/salle-attente')
            ->assertInertia(fn ($p) => $p->where('fichesEnAttente', 1));
        $this->actingAs($chef)->get('/fiches')
            ->assertInertia(fn ($p) => $p->component('reception/fiches')->has('fiches', 1));

        // Validation avec la CIN (recto/verso) et un papier
        $this->actingAs($reception)->post("/fiches/{$patient->id}/valider", [
            'nom' => 'Idrissi', 'prenom' => 'Nora', 'telephone' => '0600000000', 'cin' => 'EE1234',
            'cin_fichiers' => [UploadedFile::fake()->image('recto.jpg'), UploadedFile::fake()->image('verso.jpg')],
            'papiers' => [UploadedFile::fake()->create('ordonnance.pdf', 50, 'application/pdf')],
        ])->assertRedirect();

        $this->assertSame(2, $patient->documents()->where('type', 'cin')->count());
        $this->assertSame(1, $patient->documents()->where('type', 'autre')->count());
        $this->actingAs($reception)->get('/salle-attente')->assertInertia(fn ($p) => $p->where('fichesEnAttente', 0));
    }

    public function test_reception_modifie_antecedents_mais_pas_le_diagnostic(): void
    {
        $reception = $this->user(Role::Reception);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;

        $this->actingAs($reception)->put("/dossiers/{$dossier->id}/antecedents", [
            'antecedents' => ['diabete'], 'allergies' => 'Latex', 'traitements' => 'Metformine',
        ])->assertRedirect();
        $this->assertSame('Latex', $patient->fresh()->allergies);
        $this->assertSame(['diabete'], $patient->fresh()->antecedents);

        $this->actingAs($reception)->put("/dossiers/{$dossier->id}/medical", ['statut' => 'en_suivi'])->assertForbidden();
    }

    public function test_alerte_cin_manquante(): void
    {
        $patient = $this->patientAvecDossier();
        $textes = array_column($patient->dossier->alertes(), 'texte');
        $this->assertContains('Carte nationale non scannée', $textes);
    }

    public function test_nouveau_patient_rapide_et_doublon(): void
    {
        $reception = $this->user(Role::Reception);
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', [
            'nouveau' => ['nom' => 'Amrani', 'prenom' => 'Iliass', 'telephone' => '0612345678'], 'mission' => 'Détartrage',
        ])->assertSessionHasNoErrors();
        $iliass = Patient::firstWhere('prenom', 'Iliass');
        $this->assertNotNull($iliass->dossier);
        $this->assertSame(StatutRdv::Arrive, $iliass->rendezVous()->first()->statut);

        // même téléphone : la plateforme prévient
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', [
            'nouveau' => ['nom' => 'Amrani', 'prenom' => 'Ilias', 'telephone' => '06 12 34 56 78'], 'mission' => 'Consultation',
        ])->assertSessionHasErrors('nouveau');
        // … sauf si la réception confirme que c'est une autre personne
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', [
            'nouveau' => ['nom' => 'Amrani', 'prenom' => 'Ilias', 'telephone' => '0612345678'], 'creer_quand_meme' => true, 'mission' => 'Consultation',
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, Patient::where('nom', 'Amrani')->count());
    }

    public function test_fusion_de_deux_dossiers(): void
    {
        $reception = $this->user(Role::Reception);
        $a = $this->patientAvecDossier(['nom' => 'Tazi', 'prenom' => 'Nadia', 'telephone' => '0600000001']);
        $b = $this->patientAvecDossier(['nom' => 'Tazi', 'prenom' => 'Nadia', 'telephone' => '0700000002', 'allergies' => 'Latex']);
        $b->dossier->actes()->create(['libelle' => 'Détartrage', 'statut' => 'fait', 'prix_convenu' => 300]);
        $b->dossier->paiements()->create(['montant' => 300, 'methode' => 'especes', 'date' => today()]);

        $this->actingAs($reception)->post("/dossiers/{$a->dossier->id}/fusionner", ['autre_dossier_id' => $b->dossier->id])->assertRedirect();

        $a->refresh();
        $this->assertSame(1, $a->dossier->actes()->count());
        $this->assertSame(1, $a->dossier->paiements()->count());
        $this->assertStringContainsString('Latex', $a->allergies);
        $this->assertContains('0700000002', $a->anciens_telephones);
        $this->assertSoftDeleted('patients', ['id' => $b->id]);
        // l'ancien lien ouvre le dossier gardé
        $this->actingAs($reception)->get("/dossiers/{$b->dossier->id}")->assertRedirect("/dossiers/{$a->dossier->id}");
    }

    public function test_docteur_envoie_le_patient_et_ne_voit_pas_la_finance(): void
    {
        $nassim = $this->docteurDisponible();
        $yasmine = $this->user(Role::Docteur, ['name' => 'Dr Yasmine']);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;
        $rdv = RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $nassim->id, 'mission' => 'Consultation', 'debut' => now(), 'fin' => now()->addMinutes(30), 'statut' => StatutRdv::EnCours, 'numero_appel' => 4]);
        $seance = SeanceController::ouvrir($dossier, $nassim->id, $rdv);

        $this->actingAs($nassim)->get("/dossiers/{$dossier->id}")
            ->assertInertia(fn ($p) => $p->where('finance', null)->where('paiements', [])->where('peut.prix', false));

        $this->actingAs($nassim)->post("/seances/{$seance->id}/terminer", [
            'suite' => 'Détartrage complet', 'envoyer_docteur_id' => $yasmine->id, 'envoyer_mission' => 'Détartrage',
        ])->assertSessionHasNoErrors();

        // le patient quitte la liste de Nassim et arrive chez Yasmine
        $this->actingAs($nassim)->get('/mes-patients')->assertInertia(fn ($p) => $p->has('rdvs', 0)->has('termines', 1));
        $this->actingAs($yasmine)->get('/mes-patients')->assertInertia(fn ($p) => $p->has('rdvs', 1)
            ->where('rdvs.0.mission', 'Détartrage')->where('rdvs.0.numero_appel', 4)->where('rdvs.0.historique.suite', 'Détartrage complet'));
    }

    public function test_cloture_de_caisse_et_planning(): void
    {
        $finance = $this->user(Role::Admin); // la caisse et les totaux : le chef
        $patient = $this->patientAvecDossier();
        $patient->dossier->paiements()->create(['montant' => 500, 'methode' => 'especes', 'date' => today()]);
        $patient->dossier->paiements()->create(['montant' => 1200, 'methode' => 'carte', 'date' => today()]);

        $this->actingAs($finance)->get('/caisse')->assertInertia(fn ($p) => $p->where('totaux.especes', 500)->where('totaux.carte', 1200));
        $this->actingAs($finance)->post('/caisse/cloturer', ['date' => today()->toDateString(), 'especes_comptees' => 450])->assertRedirect();
        $cloture = \App\Models\ClotureCaisse::first();
        $this->assertEquals(1700, $cloture->total);
        $this->assertEquals(-50, $cloture->ecart);

        $reception = $this->user(Role::Reception);
        RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $this->docteurDisponible()->id, 'mission' => 'Détartrage', 'debut' => now()->addDays(3)->setTime(10, 0), 'fin' => now()->addDays(3)->setTime(10, 30), 'statut' => StatutRdv::Planifie]);
        $this->actingAs($reception)->get('/agenda?q='.$patient->nom)->assertInertia(fn ($p) => $p->has('rdvs', 1));
        $this->actingAs($reception)->get('/agenda/imprimer?periode=semaine&date='.now()->addDays(3)->toDateString())->assertOk()->assertSee('Détartrage');
    }

    public function test_chef_modifie_le_cabinet_et_les_salles(): void
    {
        $this->actingAs($this->user(Role::Admin))->get('/admin/cabinet')->assertForbidden();
        $chef = $this->user(Role::Developpeur);
        $this->actingAs($chef)->put('/admin/cabinet', ['nom' => 'Centre We Smile Guéliz', 'telephone' => '0524000000'])->assertRedirect();
        $this->actingAs($chef)->post('/admin/salles', ['nom' => 'Salle 4', 'equipement' => 'Radio panoramique'])->assertRedirect();
        $this->assertDatabaseHas('salles', ['nom' => 'Salle 4']);
        \App\Models\Parametre::charger();
        $this->assertSame('Centre We Smile Guéliz', config('cabinet.nom'));
    }

    public function test_radio_envoyee_au_labo_et_droits_finance(): void
    {
        Storage::fake('local');
        $ikram = $this->user(Role::Radio);
        $labo = $this->user(Role::Labo, ['chef_labo' => true]);
        $reception = $this->user(Role::Reception);
        $finance = $this->user(Role::Finance);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;

        $this->actingAs($ikram)->get('/radiologie')->assertOk();
        $this->actingAs($ikram)->post("/dossiers/{$dossier->id}/radios", [
            'type' => 'panoramique', 'dents' => '36', 'mesures' => 'largeur 10,8 mm',
            'fichiers' => [UploadedFile::fake()->image('pano.jpg')],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $patient->documents()->where('type', 'radio')->count());

        // le labo la reçoit automatiquement, avec les mesures
        $this->actingAs($labo)->get('/labo')->assertInertia(fn ($p) => $p->has('radios', 1)->where('radios.0.mesures', 'largeur 10,8 mm'));
        $radio = \App\Models\Radio::first();
        $this->actingAs($labo)->post("/labo/radios/{$radio->id}/vue")->assertRedirect();
        $this->assertNotNull($radio->fresh()->vue_labo_le);

        // finance : la réception ne voit plus l'argent, la finance voit les paiements du patient
        $this->actingAs($reception)->get("/dossiers/{$dossier->id}")->assertInertia(fn ($p) => $p->where('finance', null));
        $this->actingAs($reception)->post("/dossiers/{$dossier->id}/paiements", ['montant' => 100, 'methode' => 'especes', 'date' => today()->toDateString()])->assertForbidden();
        $this->actingAs($reception)->get('/caisse')->assertForbidden();
        $this->actingAs($finance)->get("/dossiers/{$dossier->id}")->assertInertia(fn ($p) => $p->whereNot('finance', null));
        $this->actingAs($finance)->get('/caisse')->assertForbidden();
    }

    public function test_travail_livre_archive_commande_et_radios(): void
    {
        $labo = $this->user(Role::Labo, ['chef_labo' => true]);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;
        $radio = $dossier->radios()->create(['type' => 'panoramique', 'mesures' => '10 mm']);
        $commande = $dossier->commandesLabo()->create(['travail' => 'Couronne', 'statut' => 'en_fabrication', 'acceptee_le' => now()]);

        $this->actingAs($labo)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 1)->has('radios', 1));
        $this->actingAs($labo)->put("/labo/{$commande->id}", ['statut' => 'livre'])->assertRedirect();

        $this->assertNotNull($radio->fresh()->archivee_le);
        $this->assertModelExists($radio); // archivée, jamais supprimée
        // Livrée : reste en cours jusqu'à ce que le labo clique « Archiver »
        $this->actingAs($labo)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 1)->has('radios', 0));
        $this->actingAs($labo)->post("/labo/{$commande->id}/archiver")->assertRedirect();
        $this->actingAs($labo)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 0));
        $this->actingAs($labo)->get('/labo?historique=1')->assertInertia(fn ($p) => $p->has('commandes', 1)->has('radios', 1));
    }

    public function test_consultation_demande_panoramique_et_docteur_envoie_a_ikram(): void
    {
        Storage::fake('local');
        $reception = $this->user(Role::Reception);
        $nassim = $this->docteurDisponible();
        $ikram = $this->user(Role::Radio);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;

        // 1re consultation : la panoramique est demandée automatiquement à la radiologie
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['patient_id' => $patient->id, 'mission' => 'Consultation'])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\DemandeRadio::where('type', 'panoramique')->count());
        $this->actingAs($ikram)->get('/radiologie')->assertInertia(fn ($p) => $p->has('demandes', 1));

        $demande = \App\Models\DemandeRadio::first();
        $this->actingAs($ikram)->post("/dossiers/{$dossier->id}/radios", ['type' => 'panoramique', 'demande_id' => $demande->id, 'fichiers' => [UploadedFile::fake()->image('pano.jpg')]])->assertSessionHasNoErrors();
        $this->assertSame('faite', $demande->fresh()->statut);

        // le docteur peut aussi envoyer le dossier à Ikram
        $this->actingAs($nassim)->post("/dossiers/{$dossier->id}/demandes-radio", ['type' => 'retro_alveolaire', 'notes' => '16, 26'])->assertRedirect();
        $this->actingAs($ikram)->get('/radiologie')->assertInertia(fn ($p) => $p->has('demandes', 1)->where('demandes.0.type', 'retro_alveolaire'));
        $this->actingAs($nassim)->get("/dossiers/{$dossier->id}")->assertInertia(fn ($p) => $p->has('demandesRadio', 1)->has('radios', 1));
    }

    public function test_rdv_par_telephone_et_prochain_rdv_chez_un_autre_docteur(): void
    {
        $reception = $this->user(Role::Reception);
        $consult = $this->docteurDisponible();
        $ortho = $this->docteurDisponible();
        $demain = now()->addDay()->toDateString();

        // 1. Nouveau patient au téléphone : nom, prénom, téléphone → dossier créé + RDV
        $this->actingAs($reception)->getJson("/agenda/creneaux?docteur_id={$consult->id}&date={$demain}&duree=30")->assertOk()->assertJsonFragment(['09:00']);
        $this->actingAs($reception)->post('/agenda', [
            'nouveau' => ['nom' => 'Alaoui', 'prenom' => 'Sara', 'telephone' => '0612345678'],
            'docteur_id' => $consult->id, 'mission' => 'Première consultation', 'date' => $demain, 'heure' => '09:00', 'duree' => 30,
        ])->assertSessionHasNoErrors();
        $patient = Patient::where('nom', 'ALAOUI')->firstOrFail();
        $this->assertNotNull($patient->dossier);
        $this->assertDatabaseHas('rendez_vous', ['patient_id' => $patient->id, 'docteur_id' => $consult->id]);
        // Le créneau pris n'est plus proposé ; un doublon est signalé
        $this->actingAs($reception)->getJson("/agenda/creneaux?docteur_id={$consult->id}&date={$demain}&duree=30")->assertJsonMissing(['09:00']);
        $this->actingAs($reception)->post('/agenda', [
            'nouveau' => ['nom' => 'alaoui', 'prenom' => 'sara', 'telephone' => '0612345678'],
            'docteur_id' => $consult->id, 'mission' => 'Consultation', 'date' => $demain, 'heure' => '10:00', 'duree' => 30,
        ])->assertSessionHasErrors('nouveau.nom');

        // 2. Après la consultation : le docteur laisse la réception fixer la date chez l'orthodontiste
        $dossier = $patient->dossier;
        $this->actingAs($consult)->post("/dossiers/{$dossier->id}/prochain-rdv", ['mode' => 'reception', 'docteur_id' => $ortho->id, 'mission' => 'Bilan orthodontie', 'notes' => 'dans 2 semaines'])->assertRedirect();
        $af = \App\Models\RdvAFixer::firstOrFail();
        $this->actingAs($reception)->get('/agenda')->assertInertia(fn ($p) => $p->has('aFixer', 1)->where('rdvAFixer', 1));
        $this->actingAs($reception)->post('/agenda', ['patient_id' => $patient->id, 'a_fixer_id' => $af->id, 'docteur_id' => $ortho->id, 'mission' => 'Bilan orthodontie', 'date' => now()->addDays(14)->toDateString(), 'heure' => '11:00', 'duree' => 30])->assertSessionHasNoErrors();
        $this->assertSame('fixe', $af->fresh()->statut);

        // 3. Ou le docteur fixe lui-même la date (en fin de séance)
        $seance = \App\Models\Seance::create(['dossier_id' => $dossier->id, 'docteur_id' => $consult->id, 'debut' => now()->subMinutes(20)]);
        $this->actingAs($consult)->post("/seances/{$seance->id}/terminer", [
            'compte_rendu' => 'Consultation faite',
            'prochain' => ['mode' => 'fixer', 'docteur_id' => $ortho->id, 'mission' => 'Détartrage', 'date' => now()->addDays(3)->toDateString(), 'heure' => '15:00', 'duree' => 30],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('rendez_vous', ['patient_id' => $patient->id, 'docteur_id' => $ortho->id, 'mission' => 'Détartrage']);
        $this->assertNotNull($seance->fresh()->fin);

        // Créneau déjà pris → erreur, et la séance n'est pas clôturée
        $seance2 = \App\Models\Seance::create(['dossier_id' => $dossier->id, 'docteur_id' => $consult->id, 'debut' => now()->subMinutes(5)]);
        $this->actingAs($consult)->post("/seances/{$seance2->id}/terminer", [
            'prochain' => ['mode' => 'fixer', 'docteur_id' => $ortho->id, 'mission' => 'X', 'date' => now()->addDays(3)->toDateString(), 'heure' => '15:00', 'duree' => 30],
        ])->assertSessionHasErrors('prochain.heure');
        $this->assertNull($seance2->fresh()->fin);
    }

    public function test_dossier_pris_au_telephone_a_confirmer_puis_fiche_tablette(): void
    {
        $reception = $this->user(Role::Reception);
        $doc = $this->docteurDisponible();
        $this->actingAs($reception)->post('/agenda', [
            'nouveau' => ['nom' => 'Tazi', 'prenom' => 'Karim', 'telephone' => '0661223344'],
            'docteur_id' => $doc->id, 'mission' => 'Première consultation', 'date' => now()->toDateString(), 'heure' => now()->addHour()->format('H:00'), 'duree' => 30, 'forcer' => true,
        ])->assertSessionHasNoErrors();
        $karim = Patient::where('nom', 'TAZI')->firstOrFail();
        $this->assertTrue($karim->fiche_a_completer);

        // Arrivée : message « fiche non remplie »
        $rdv = RendezVous::where('patient_id', $karim->id)->firstOrFail();
        $this->actingAs($reception)->post("/rendez-vous/{$rdv->id}/arrive")->assertInertiaFlash('toast.type', 'error');

        // La réception l'envoie à la tablette : la fiche s'ouvre à son nom
        $this->actingAs($reception)->post("/patients/{$karim->id}/tablette")->assertRedirect();
        auth()->logout();
        $this->get('/accueil')->assertInertia(fn ($p) => $p->where('attendu.id', $karim->id));
        $this->post('/accueil', ['patient_attendu_id' => $karim->id, 'nom' => 'Tazi', 'prenom' => 'Karim', 'telephone' => '0661223344', 'date_naissance' => '1990-05-12', 'allergies' => 'Pénicilline'])->assertRedirect();

        $karim->refresh();
        $this->assertFalse($karim->fiche_a_completer);
        $this->assertSame('Pénicilline', $karim->allergies);
        $this->assertSame(1, Patient::where('telephone', '0661223344')->count()); // pas de doublon
        $this->get('/accueil')->assertInertia(fn ($p) => $p->where('attendu', null));
    }

    public function test_pas_de_doublon_salle_attente_archive_labo_et_corbeille(): void
    {
        $reception = $this->user(Role::Reception);
        $doc = $this->docteurDisponible();
        $patient = $this->patientAvecDossier();
        $rdv = RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $doc->id, 'mission' => 'Contrôle', 'debut' => now()->addHour(), 'fin' => now()->addHours(2), 'statut' => StatutRdv::Planifie]);

        // Ajouté « sans RDV » alors qu'il a un RDV aujourd'hui : pas de 2e ligne
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['patient_id' => $patient->id, 'mission' => 'Contrôle'])->assertRedirect();
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['patient_id' => $patient->id, 'mission' => 'Contrôle'])->assertRedirect();
        $this->assertSame(1, RendezVous::where('patient_id', $patient->id)->count());
        $this->assertSame(StatutRdv::Arrive, $rdv->fresh()->statut);

        // Labo : archiver une commande prête, puis la désarchiver
        $labo = $this->user(Role::Labo, ['chef_labo' => true]);
        $c = \App\Models\CommandeLabo::create(['dossier_id' => $patient->dossier->id, 'travail' => 'Couronne', 'statut' => 'a_faire', 'acceptee_le' => now()]);
        $this->actingAs($labo)->post("/labo/{$c->id}/archiver")->assertStatus(422);
        $this->actingAs($labo)->put("/labo/{$c->id}", ['statut' => 'pret']);
        $this->actingAs($labo)->post("/labo/{$c->id}/archiver")->assertRedirect();
        $this->actingAs($labo)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 0));
        $this->actingAs($labo)->get('/labo?historique=1')->assertInertia(fn ($p) => $p->has('commandes', 1));
        $this->actingAs($labo)->post("/labo/{$c->id}/desarchiver")->assertRedirect();
        $this->assertNull($c->fresh()->archivee_le);

        // Supprimer un dossier : chef seulement, puis restaurer depuis la corbeille
        $dossier = $patient->dossier;
        $this->actingAs($reception)->delete("/dossiers/{$dossier->id}")->assertForbidden();
        $chef = $this->user(Role::Admin);
        $this->actingAs($chef)->delete("/dossiers/{$dossier->id}")->assertRedirect('/patients');
        $this->assertSoftDeleted($dossier);
        $this->assertSame(StatutRdv::Annule, $rdv->fresh()->statut);
        $this->actingAs($chef)->get('/patients?corbeille=1')->assertInertia(fn ($p) => $p->where('patients.total', 1));
        $this->actingAs($chef)->post("/patients/{$patient->id}/restaurer")->assertRedirect();
        $this->assertNotSoftDeleted($dossier);
    }

    public function test_reception_supprime_un_rendez_vous_termine(): void
    {
        $doc = $this->docteurDisponible();
        $autre = $this->user(Role::Docteur);
        $patient = $this->patientAvecDossier();
        $rdv = RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $doc->id, 'mission' => 'Consultation', 'debut' => now()->subHour(), 'fin' => now(), 'statut' => StatutRdv::Termine]);
        $this->actingAs($autre)->delete("/rendez-vous/{$rdv->id}")->assertForbidden();
        $this->actingAs($this->user(Role::Reception))->delete("/rendez-vous/{$rdv->id}")->assertRedirect();
        $this->assertModelMissing($rdv);
    }

    public function test_radio_deja_envoyee_pas_renvoyee(): void
    {
        $doc = $this->user(Role::Docteur);
        $dossier = $this->patientAvecDossier()->dossier;
        $this->actingAs($doc)->post("/dossiers/{$dossier->id}/demandes-radio", ['type' => 'cone_beam'])->assertSessionHasNoErrors();
        $this->actingAs($doc)->post("/dossiers/{$dossier->id}/demandes-radio", ['type' => 'cone_beam'])->assertSessionHasErrors('type');
        $this->assertSame(1, \App\Models\DemandeRadio::count());
    }

    public function test_demande_ecrite_acceptee_par_la_cheffe_du_labo(): void
    {
        $this->artisan('cabinet:equipe-labo')->assertSuccessful();
        $sofia = User::where('email', 'sofia.mostafi@wesmile.ma')->firstOrFail();
        $brahim = User::where('email', 'brahim.ait.lahsen@wesmile.ma')->firstOrFail();
        $walid = User::where('email', 'walid.el.maataoui@wesmile.ma')->firstOrFail();
        $this->assertTrue($sofia->chef_labo);
        $this->assertSame('Céramiste', $brahim->taches_principales);
        $dossier = $this->patientAvecDossier()->dossier;

        // Le docteur et la finance écrivent la demande librement
        $this->actingAs($this->user(Role::Docteur))->post("/dossiers/{$dossier->id}/labo", ['demande' => "Facette E.max 11 21\nTeinte A1, forme naturelle"])->assertSessionHasNoErrors();
        $this->actingAs($this->user(Role::Finance))->post("/dossiers/{$dossier->id}/labo", ['demande' => 'Couronne zircone 36'])->assertSessionHasNoErrors();
        $facette = \App\Models\CommandeLabo::where('travail', 'Facette E.max 11 21')->firstOrFail();
        $this->assertStringContainsString('Teinte A1', $facette->instructions);
        $this->assertNull($facette->acceptee_le);

        // Sofia voit les 2 demandes à accepter ; Brahim ne voit rien
        $this->actingAs($sofia)->get('/labo')->assertInertia(fn ($p) => $p->where('chef', true)->has('aAccepter', 2)->has('commandes', 0)->has('equipe', 7));
        $this->actingAs($brahim)->get('/labo')->assertInertia(fn ($p) => $p->where('chef', false)->has('aAccepter', 0)->has('commandes', 0)->has('equipe', 0));
        $this->actingAs($brahim)->post("/labo/{$facette->id}/accepter", ['travail' => 'X', 'technicien_id' => $brahim->id, 'date_prete' => now()->addDays(3)->toDateString()])->assertForbidden();

        // Sofia accepte et confie avec la date « prête le »
        $date = now()->addDays(6)->toDateString();
        $this->actingAs($sofia)->post("/labo/{$facette->id}/accepter", ['travail' => 'Facettes E.max 11-21', 'technicien_id' => $brahim->id, 'date_prete' => $date])->assertSessionHasNoErrors();
        $this->assertSame($date, $facette->fresh()->date_prete->toDateString());
        $this->assertSame('bientot', $facette->fresh()->priorite());

        // Brahim ne voit que son travail ; Walid rien ; il ne peut pas toucher au travail d'un autre
        $this->actingAs($brahim)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 1)->where('commandes.0.travail', 'Facettes E.max 11-21'));
        $this->actingAs($walid)->get('/labo')->assertInertia(fn ($p) => $p->has('commandes', 0));
        $this->actingAs($walid)->put("/labo/{$facette->id}", ['statut' => 'pret'])->assertForbidden();
        $this->actingAs($brahim)->put("/labo/{$facette->id}", ['technicien_id' => $brahim->id])->assertForbidden();
        $this->actingAs($brahim)->put("/labo/{$facette->id}", ['statut' => 'en_fabrication'])->assertRedirect();
        $this->assertSame(StatutLabo::EnFabrication, $facette->fresh()->statut);
        $this->actingAs($sofia)->get('/labo')->assertInertia(fn ($p) => $p->has('aAccepter', 1)->has('commandes', 1));
    }

    public function test_supprimer_utilisateurs_labo_et_colonne_patients(): void
    {
        $this->artisan('cabinet:equipe-labo')->assertSuccessful();
        $sofia = User::where('email', 'sofia.mostafi@wesmile.ma')->firstOrFail();
        $dossier = $this->patientAvecDossier()->dossier;
        $c = $dossier->commandesLabo()->create(['travail' => 'Couronne', 'statut' => 'a_faire', 'technicien_id' => $sofia->id]);
        $dev = $this->user(Role::Developpeur);
        $this->actingAs($dev)->get('/admin/utilisateurs')->assertInertia(fn ($p) => $p->where('utilisateurs', fn ($u) => collect($u)->firstWhere('email', 'sofia.mostafi@wesmile.ma')['nb_patients'] === 1));
        $this->actingAs($dev)->delete("/admin/utilisateurs/{$sofia->id}")->assertRedirect();
        $this->assertSoftDeleted($sofia);
        $this->assertNull($c->fresh()->technicien_id);
        $this->artisan('cabinet:supprimer-utilisateur', ['--labo' => true])->expectsConfirmation('Supprimer ces comptes ?', 'yes')->assertSuccessful();
        $this->assertSame(0, User::where('role', 'docteur_labo')->count());
        $this->artisan('cabinet:equipe-labo')->assertSuccessful(); // recréés
        $this->assertSame(7, User::where('role', 'docteur_labo')->count());
    }

    public function test_patient_cree_sans_date_naissance_est_a_confirmer(): void
    {
        $this->actingAs($this->user(Role::Reception))->post('/patients', ['nom' => 'Test', 'prenom' => 'Ali', 'telephone' => '0600112233']);
        $this->assertTrue(Patient::where('nom', 'TEST')->orWhere('nom', 'Test')->firstOrFail()->fiche_a_completer);
    }

    public function test_trois_types_de_patients_et_ordre_tablette(): void
    {
        $reception = $this->user(Role::Reception);
        $doc = $this->docteurDisponible();
        // 1. Sans RDV, 1re fois : fiche à remplir → pas envoyable au docteur avant la tablette
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['nouveau' => ['nom' => 'Sans', 'prenom' => 'Rdv', 'telephone' => '0611000001'], 'docteur_id' => $doc->id, 'mission' => 'Consultation']);
        $r1 = RendezVous::latest('id')->firstOrFail();
        $this->actingAs($reception)->post("/rendez-vous/{$r1->id}/envoyer", ['docteur_id' => $doc->id]);
        $this->assertSame(StatutRdv::Arrive, $r1->fresh()->statut);
        // 3. Suivi : patient déjà venu (1 séance terminée) → séance 2
        $p = $this->patientAvecDossier(['date_naissance' => '1990-01-01']);
        \App\Models\Seance::create(['dossier_id' => $p->dossier->id, 'docteur_id' => $doc->id, 'debut' => now()->subWeek(), 'fin' => now()->subWeek()->addHour()]);
        RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc->id, 'mission' => 'Couronne', 'debut' => now()->addHour(), 'fin' => now()->addHours(2), 'statut' => StatutRdv::Planifie]);
        $this->actingAs($reception)->get('/salle-attente')->assertInertia(fn ($pg) => $pg
            ->where('rdvs', fn ($rdvs) => collect($rdvs)->firstWhere('patient_id', $p->id)['visite'] === 'suivi' && collect($rdvs)->firstWhere('patient_id', $p->id)['seance_num'] === 2
                && collect($rdvs)->firstWhere('id', $r1->id)['visite'] === 'premiere_sans_rdv'));
    }

    public function test_urgence_signale_le_rdv_futur_a_supprimer(): void
    {
        $reception = $this->user(Role::Reception);
        $doc = $this->docteurDisponible();
        $p = $this->patientAvecDossier(['date_naissance' => '1990-01-01']);
        $futur = RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc->id, 'mission' => 'Consultation', 'debut' => now()->addDays(3), 'fin' => now()->addDays(3)->addMinutes(30), 'statut' => StatutRdv::Planifie]);
        $this->actingAs($reception)->post('/salle-attente/sans-rdv', ['patient_id' => $p->id, 'docteur_id' => $doc->id, 'mission' => 'Douleur', 'urgence' => true]);
        $this->actingAs($reception)->get('/salle-attente')->assertInertia(fn ($pg) => $pg->where('rdvs.0.autres_rdv.0.id', $futur->id));
        $this->actingAs($reception)->delete("/rendez-vous/{$futur->id}")->assertRedirect();
        $this->assertModelMissing($futur);
    }

    public function test_carte_patient_qr_et_page_mon_dossier(): void
    {
        $photo = $this->user(Role::Photographe);
        $p = $this->patientAvecDossier(['telephone' => '0661 22 33 44', 'date_naissance' => '1990-01-01']);
        $this->actingAs($photo)->get("/dossiers/{$p->dossier->id}")->assertInertia(fn ($pg) => $pg->where('carte.photo', null)->has('carte.lien'));
        $this->actingAs($photo)->get("/patients/{$p->id}/carte")->assertOk()->assertSee('<svg', false)->assertSee($p->dossier->numero);
        $token = $p->fresh()->carte_token;
        $this->assertNotEmpty($token);
        $p->dossier->paiements()->create(['montant' => 500, 'methode' => 'especes', 'date' => now()]);
        $p->dossier->radios()->create(['type' => 'panoramique', 'notes' => 'SECRET-RADIO']);
        auth()->logout();
        // Code obligatoire : 4 derniers chiffres du téléphone
        $this->get("/c/{$token}")->assertOk()->assertSee('4 derniers chiffres')->assertDontSee('500 DH');
        $this->post("/c/{$token}", ['code' => '0000'])->assertSee('Code incorrect');
        $this->post("/c/{$token}", ['code' => '3344'])->assertRedirect("/c/{$token}");
        $this->get("/c/{$token}")->assertOk()->assertSee($p->nom_complet)->assertSee('500 DH')->assertDontSee('SECRET-RADIO');
        $this->get('/c/mauvais-lien')->assertNotFound();
    }

    public function test_premiere_visite_met_consultation_puis_controle_dans_a_faire(): void
    {
        $reception = $this->user(Role::Reception);
        $docteur = $this->docteurDisponible();
        $p = $this->patientAvecDossier();
        $base = ['patient_id' => $p->id, 'docteur_id' => $docteur->id, 'mission' => 'Douleur', 'date' => now()->addDay()->toDateString(), 'duree' => 30];
        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '10:00'])->assertSessionHasNoErrors();
        $acte = $p->dossier->actes()->sole();
        $this->assertStringStartsWith('Consultation', $acte->libelle);
        $this->assertSame('a_faire', $acte->statut->value);
        $this->assertSame($acte->id, $p->rendezVous()->first()->acte_id);
        // 2e RDV alors que la consultation est encore à faire : pas de doublon
        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '11:00'])->assertSessionHasNoErrors();
        $this->assertSame(1, $p->dossier->actes()->count());
        // Consultation faite, le patient revient : « Contrôle »
        $acte->update(['statut' => 'fait']);
        $this->actingAs($reception)->post('/agenda', $base + ['heure' => '12:00'])->assertSessionHasNoErrors();
        $this->assertSame('Contrôle', $p->dossier->actes()->latest('id')->first()->libelle);
    }

    public function test_labo_pret_demande_le_rdv_de_pose_a_la_reception(): void
    {
        $labo = $this->user(Role::Labo, ['chef_labo' => true]);
        $docteur = $this->user(Role::Docteur);
        $patient = $this->patientAvecDossier();
        $commande = $patient->dossier->commandesLabo()->create(['travail' => 'Couronne zircone', 'dents' => '36', 'statut' => 'en_fabrication', 'envoye_par' => $docteur->id, 'acceptee_le' => now()]);
        $this->actingAs($labo)->put("/labo/{$commande->id}", ['statut' => 'pret'])->assertRedirect();
        $f = \App\Models\RdvAFixer::sole();
        $this->assertSame('Pose Couronne zircone · 36', $f->mission);
        $this->assertSame($docteur->id, $f->docteur_id);
        // Pas de 2e demande si on repasse « prêt »
        $this->actingAs($labo)->put("/labo/{$commande->id}", ['statut' => 'pret'])->assertRedirect();
        $this->assertSame(1, \App\Models\RdvAFixer::count());
    }

    public function test_travail_demande_pour_un_autre_docteur_va_dans_a_faire(): void
    {
        $docteur = $this->docteurDisponible();
        $autre = $this->docteurDisponible();
        $p = $this->patientAvecDossier();
        $d = $p->dossier;
        $this->actingAs($docteur)->post("/dossiers/{$d->id}/prochain-rdv", [
            'mode' => 'reception', 'docteur_id' => $autre->id, 'mission' => 'Implant · 36',
        ])->assertSessionHasNoErrors();
        $acte = $d->actes()->sole();
        $this->assertSame(['Implant', '36', 'a_faire'], [$acte->libelle, $acte->dents, $acte->statut->value]);
        $this->assertSame($acte->id, \App\Models\RdvAFixer::sole()->acte_id);
        // Même travail redemandé : pas de doublon
        $this->actingAs($docteur)->post("/dossiers/{$d->id}/prochain-rdv", [
            'mode' => 'fixer', 'docteur_id' => $autre->id, 'mission' => 'Implant · 36', 'date' => now()->addDay()->toDateString(), 'heure' => '10:00', 'duree' => 60,
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $d->actes()->count());
        $this->assertSame($acte->id, $p->rendezVous()->first()->acte_id);
    }

    public function test_espace_patient_reserve_seulement_les_creneaux_libres(): void
    {
        $docteur = $this->docteurDisponible();
        $docteur->update(['fait_consultation' => true, 'duree_rdv' => 30]);
        $jour = now()->addDays(2)->toDateString();
        // Un RDV déjà pris par la réception à 10:00 : ce créneau ne doit pas être proposé
        $connu = $this->patientAvecDossier(['telephone' => '0611223344', 'date_naissance' => '1990-05-12']);
        RendezVous::create(['patient_id' => $connu->id, 'docteur_id' => $docteur->id, 'debut' => "$jour 10:00", 'fin' => "$jour 10:30", 'mission' => 'Consultation', 'statut' => 'planifie']);

        // Nouveau patient en ligne
        $this->post('/mon-espace/nouveau', ['nom' => 'Alaoui', 'prenom' => 'Nadia', 'telephone' => '0655443322'])->assertRedirect('/mon-espace/dossier');
        $nadia = Patient::where('telephone', '0655443322')->sole();
        $this->assertTrue($nadia->fiche_a_completer);
        $h = $this->getJson("/mon-espace/creneaux?motif=consultation&date=$jour")->assertOk()->json();
        $this->assertNotContains('10:00', $h);
        $this->assertContains('10:30', $h);
        // Contrôle réservé aux patients déjà soignés
        $this->getJson("/mon-espace/creneaux?motif=controle&date=$jour")->assertStatus(422);
        // Créneau déjà pris → refusé ; créneau libre → réservé (en ligne, consultation dans « À faire »)
        $this->post('/mon-espace/reserver', ['motif' => 'consultation', 'date' => $jour, 'heure' => '10:00'])->assertSessionHasErrors('heure');
        $this->post('/mon-espace/reserver', ['motif' => 'consultation', 'date' => $jour, 'heure' => '10:30'])->assertSessionHasNoErrors();
        $rdv = $nadia->rendezVous()->sole();
        $this->assertTrue($rdv->en_ligne);
        $this->assertSame($docteur->id, $rdv->docteur_id);
        $this->assertStringStartsWith('Consultation', $nadia->dossier->actes()->sole()->libelle);
        // Un seul RDV à venir à la fois
        $this->post('/mon-espace/reserver', ['motif' => 'consultation', 'date' => $jour, 'heure' => '11:00'])->assertSessionHasErrors('heure');
        // Le même numéro ne peut pas recréer un dossier
        $this->post('/mon-espace/deconnexion');
        $this->post('/mon-espace/nouveau', ['nom' => 'X', 'prenom' => 'Y', 'telephone' => '06 55 44 33 22'])->assertSessionHasErrors('nouveau');

        // Patient connu : téléphone + date de naissance ou n° de dossier
        $this->post('/mon-espace/connexion', ['telephone' => '0611223344', 'preuve' => '01/01/2000'])->assertSessionHasErrors('telephone');
        $this->post('/mon-espace/connexion', ['telephone' => '06 11 22 33 44', 'preuve' => '12/05/1990'])->assertRedirect('/mon-espace/dossier');
        $this->get('/mon-espace/dossier')->assertOk()->assertSee('Mon prochain rendez-vous');
        $this->post('/mon-espace/deconnexion');
        $this->post('/mon-espace/connexion', ['telephone' => '0611223344', 'preuve' => (string) $connu->dossier->numero])->assertRedirect('/mon-espace/dossier');
        // Sans connexion : rien
        $this->post('/mon-espace/deconnexion');
        $this->getJson("/mon-espace/creneaux?motif=consultation&date=$jour")->assertForbidden();
    }

    public function test_apres_consultation_le_devis_passe_par_la_finance(): void
    {
        $nassim = $this->docteurDisponible();
        $nassim->update(['fait_consultation' => true]);
        $malika = $this->docteurDisponible();
        $chef = $this->user(Role::Admin);
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;
        $dossier->acteDemande('All on 4 (chirurgie) · maxillaire', null, $nassim->id);

        // Nassim ne peut ni envoyer le patient, ni fixer un RDV lui-même
        $seance = \App\Models\Seance::create(['dossier_id' => $dossier->id, 'docteur_id' => $nassim->id, 'debut' => now()->subMinutes(15)]);
        $this->actingAs($nassim)->post("/seances/{$seance->id}/terminer", ['envoyer_docteur_id' => $malika->id, 'envoyer_mission' => 'All on 4'])->assertSessionHasErrors('plan');
        $this->assertNull($seance->fresh()->fin);
        $this->actingAs($nassim)->post("/dossiers/{$dossier->id}/prochain-rdv", ['mode' => 'reception', 'docteur_id' => $malika->id, 'mission' => 'X'])->assertForbidden();
        $this->actingAs($nassim)->get("/dossiers/{$dossier->id}")->assertInertia(fn ($p) => $p->where('peut.envoyer', false));

        // Il envoie le plan à la finance (Fadwa)
        $this->actingAs($nassim)->post("/seances/{$seance->id}/terminer", [
            'plan' => ['actes' => "All on 4 (chirurgie) · maxillaire\nAll on 4 (prothèse) · maxillaire\nBlanchiment", 'docteur_id' => $malika->id, 'notes' => 'Patient pressé'],
        ])->assertSessionHasNoErrors();
        $plan = \App\Models\PlanAValider::firstOrFail();
        $this->assertSame('en_attente', $plan->statut);
        $this->assertSame(0, RendezVous::where('docteur_id', $malika->id)->count());
        $fadwa = $this->user(Role::Finance);
        $this->actingAs($fadwa)->get('/plans')->assertInertia(fn ($p) => $p->component('finance/plans')->has('enAttente', 1)->has('enAttente.0.lignes', 3)->where('plansAValider', 1));
        // Le dossier avec un plan en attente s'ouvre sans écraser le compteur du menu (bug : page blanche chez la finance)
        $this->actingAs($fadwa)->get("/dossiers/{$dossier->id}")->assertInertia(fn ($p) => $p->has('plansDossier', 1)->where('plansAValider', 1));
        $this->actingAs($this->user(Role::Docteur))->get('/plans')->assertForbidden();
        $this->actingAs($fadwa)->getJson("/agenda/creneaux?docteur_id={$malika->id}&date=".now()->addDays(5)->toDateString().'&duree=60')->assertOk();

        // Le patient accepte 2 actes sur 3 (prix négocié) ; Fadwa fixe le docteur et la date
        $date = now()->addDays(5)->toDateString();
        $this->actingAs($fadwa)->post("/plans/{$plan->id}/valider", [
            'lignes' => [
                ['texte' => 'All on 4 (chirurgie) · maxillaire', 'prix' => 40000, 'accepte' => true],
                ['texte' => 'All on 4 (prothèse) · maxillaire', 'prix' => 30000, 'accepte' => true],
                ['texte' => 'Blanchiment', 'prix' => 2500, 'accepte' => false],
            ],
            'note' => 'trop cher pour le blanchiment',
            'prochain' => ['mode' => 'fixer', 'docteur_id' => $malika->id, 'mission' => 'All on 4 (chirurgie) · maxillaire', 'date' => $date, 'heure' => '10:00', 'duree' => 120],
        ])->assertSessionHasNoErrors();
        $this->assertSame('valide', $plan->fresh()->statut);
        $this->assertSame(2, $dossier->actes()->where('statut', 'a_faire')->count());
        $this->assertEquals(40000, $dossier->actes()->where('libelle', 'All on 4 (chirurgie)')->value('prix_convenu'));
        $this->assertDatabaseHas('actes', ['dossier_id' => $dossier->id, 'libelle' => 'Blanchiment', 'statut' => 'annule']);
        $rdv = RendezVous::where('docteur_id', $malika->id)->firstOrFail();
        $this->assertSame($date.' 10:00', $rdv->debut->format('Y-m-d H:i'));
        $this->assertSame('All on 4 (chirurgie)', $rdv->acte?->libelle);
        $this->actingAs($fadwa)->post("/plans/{$plan->id}/valider", ['lignes' => [['texte' => 'X', 'accepte' => true]]])->assertStatus(422);

        // Un autre patient refuse tout : c'est noté, pas de RDV
        $p2 = $this->patientAvecDossier(['nom' => 'Refus', 'telephone' => '0699']);
        $plan2 = \App\Models\PlanAValider::create(['dossier_id' => $p2->dossier->id, 'propose_par' => $nassim->id, 'actes' => 'Facette céramique · 11']);
        $this->actingAs($fadwa)->post("/plans/{$plan2->id}/valider", ['lignes' => [['texte' => 'Facette céramique · 11', 'accepte' => false]], 'note' => 'réfléchit'])->assertSessionHasNoErrors();
        $this->assertSame('refuse', $plan2->fresh()->statut);
        $this->assertSame(0, RendezVous::where('patient_id', $p2->id)->count());

        // Un autre docteur (pas de consultation) envoie toujours directement
        $seance2 = \App\Models\Seance::create(['dossier_id' => $dossier->id, 'docteur_id' => $malika->id, 'debut' => now()->subMinutes(5)]);
        $this->actingAs($malika)->post("/seances/{$seance2->id}/terminer", ['envoyer_docteur_id' => $nassim->id, 'envoyer_mission' => 'Contrôle'])->assertSessionHasNoErrors();
    }

    public function test_actes_ecrits_a_la_main_fait_et_a_faire(): void
    {
        $doc = $this->docteurDisponible();
        $patient = $this->patientAvecDossier();
        $dossier = $patient->dossier;
        $dossier->acteDemande('Détartrage', null, $doc->id);

        // Plan de traitement : écrire un acte « Fait » ou « À faire » (texte libre)
        $this->actingAs($doc)->post("/dossiers/{$dossier->id}/actes", ['statut' => 'fait', 'libelle' => 'Détartrage'])->assertSessionHasNoErrors();
        $this->actingAs($doc)->post("/dossiers/{$dossier->id}/actes", ['statut' => 'a_faire', 'libelle' => 'Gouttière de blanchiment', 'dents' => 'maxillaire'])->assertSessionHasNoErrors();
        $this->assertSame(1, $dossier->actes()->where('libelle', 'Détartrage')->count());
        $this->assertSame('fait', $dossier->actes()->where('libelle', 'Détartrage')->first()->statut->value);
        $this->assertDatabaseHas('actes', ['dossier_id' => $dossier->id, 'libelle' => 'Gouttière de blanchiment', 'dents' => 'maxillaire', 'statut' => 'a_faire']);
        $this->actingAs($this->user(Role::Reception))->post("/dossiers/{$dossier->id}/actes", ['statut' => 'fait', 'libelle' => 'X'])->assertForbidden();

        // Fin de séance : « Fait aujourd'hui » et « À faire ensuite » écrits
        $seance = \App\Models\Seance::create(['dossier_id' => $dossier->id, 'docteur_id' => $doc->id, 'debut' => now()->subMinutes(20)]);
        $this->actingAs($doc)->post("/seances/{$seance->id}/terminer", [
            'faits_texte' => "Composite · 36\nGouttière de blanchiment · maxillaire", 'a_faire_texte' => "Couronne zircone · 36\n",
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('actes', ['dossier_id' => $dossier->id, 'libelle' => 'Composite', 'dents' => '36', 'statut' => 'fait', 'seance_id' => $seance->id]);
        $this->assertSame('fait', $dossier->actes()->where('libelle', 'Gouttière de blanchiment')->first()->statut->value);
        $this->assertDatabaseHas('actes', ['dossier_id' => $dossier->id, 'libelle' => 'Couronne zircone', 'dents' => '36', 'statut' => 'a_faire']);

        // Un acte « Fait » se modifie ou se supprime (par le docteur qui l'a fait ou le chef)
        $composite = $dossier->actes()->where('libelle', 'Composite')->first();
        $this->actingAs($doc)->put("/actes/{$composite->id}", ['libelle' => 'Composite 2 faces', 'dents' => '36, 37'])->assertSessionHasNoErrors();
        $this->assertSame('Composite 2 faces', $composite->fresh()->libelle);
        $this->actingAs($doc)->put("/actes/{$composite->id}", ['statut' => 'a_faire']);
        $this->assertNull($composite->fresh()->fait_le);
        $this->actingAs($doc)->put("/actes/{$composite->id}", ['statut' => 'fait']);
        $autre = $this->docteurDisponible();
        $this->actingAs($autre)->delete("/actes/{$composite->id}")->assertForbidden();
        $this->actingAs($doc)->delete("/actes/{$composite->id}")->assertRedirect();
        $this->assertSoftDeleted('actes', ['id' => $composite->id]);
    }

    public function test_rappels_sms_la_veille(): void
    {
        $doc = $this->docteurDisponible();
        $demain = now()->addDay()->setTime(10, 30);
        $p1 = $this->patientAvecDossier(['prenom' => 'Salma', 'telephone' => '06 12 34 56 78']);
        $p2 = $this->patientAvecDossier(['nom' => 'Sans', 'telephone' => '0522']);
        foreach ([$p1, $p2] as $p) {
            RendezVous::create(['patient_id' => $p->id, 'docteur_id' => $doc->id, 'mission' => 'Contrôle', 'debut' => $demain, 'fin' => $demain->copy()->addMinutes(30), 'statut' => StatutRdv::Planifie]);
        }
        config(['sms.driver' => 'http', 'sms.http.url' => 'https://sms.example/api', 'cabinet.telephone' => '05 37 73 73 77', 'cabinet.nom' => 'Wesmile Center']);
        \Illuminate\Support\Facades\Http::fake(['sms.example/*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);

        $this->artisan('cabinet:rappels')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => $r['to'] === '+212612345678' && str_contains($r['message'], 'Salma') && str_contains($r['message'], '10:30') && str_contains($r['message'], '0537737377'));
        $this->assertNotNull(RendezVous::where('patient_id', $p1->id)->first()->rappel_envoye_le);
        $this->assertDatabaseHas('sms', ['patient_id' => $p1->id, 'statut' => 'envoye']);

        // Pas de 2e SMS pour le même RDV
        $this->artisan('cabinet:rappels')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_commentaire_patient_visible_par_le_chef_seulement(): void
    {
        $patient = $this->patientAvecDossier();
        $this->post('/mon-espace/avis', ['commentaire' => 'x'])->assertForbidden();
        $this->withSession(['espace.patient_id' => $patient->id]);
        $this->post('/mon-espace/avis', [])->assertSessionHasErrors('commentaire');
        $this->post('/mon-espace/avis', ['commentaire' => 'Attente trop longue'])->assertSessionHas('avis_ok');
        $this->get('/mon-espace/dossier')->assertOk()->assertSee('Un commentaire pour nous ?');

        $this->actingAs($this->user(Role::Docteur))->get('/admin/avis')->assertForbidden();
        $chef = $this->user(Role::Admin);
        $this->actingAs($chef)->get('/admin/avis')->assertOk()
            ->assertInertia(fn ($p) => $p->component('admin/avis')->where('avis.0.commentaire', 'Attente trop longue')->where('avis.0.lu_le', null)->where('total', 1));
        $this->assertNotNull(\App\Models\Avis::first()->lu_le);
        $this->actingAs($chef)->get('/dossiers/'.$patient->dossier->id)->assertInertia(fn ($p) => $p->where('avis.0.commentaire', 'Attente trop longue'));
        $this->actingAs($this->user(Role::Docteur))->get('/dossiers/'.$patient->dossier->id)->assertInertia(fn ($p) => $p->where('avis', []));
    }

    public function test_docteur_modifie_et_annule_ses_rendez_vous(): void
    {
        $nassim = $this->docteurDisponible();
        $autre = $this->user(Role::Docteur);
        $patient = $this->patientAvecDossier();
        $rdv = RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $nassim->id, 'mission' => 'Contrôle', 'debut' => now()->addDay()->setTime(10, 0), 'fin' => now()->addDay()->setTime(10, 30), 'statut' => StatutRdv::Planifie]);

        $this->actingAs($autre)->put("/rendez-vous/{$rdv->id}", ['date' => now()->addDays(2)->toDateString(), 'heure' => '11:00', 'duree' => 30, 'mission' => 'Contrôle'])->assertForbidden();
        $this->actingAs($nassim)->put("/rendez-vous/{$rdv->id}", ['date' => now()->addDays(2)->toDateString(), 'heure' => '11:00', 'duree' => 45, 'mission' => 'Contrôle + photo'])->assertSessionHasNoErrors();
        $this->assertSame('11:00', $rdv->fresh()->debut->format('H:i'));
        $this->actingAs($nassim)->get('/mes-patients?jour=semaine')->assertInertia(fn ($p) => $p->has('semaine', 1)->has('semaine.0.rdvs', 1));
        $this->actingAs($nassim)->post("/rendez-vous/{$rdv->id}/annuler")->assertRedirect();
        $this->assertSame(StatutRdv::Annule, $rdv->fresh()->statut);
    }

    public function test_compte_developpeur_secret(): void
    {
        $dev = $this->user(Role::Developpeur, ['email' => 'dev@secret.ma']);
        $chef = $this->user(Role::Admin);

        $this->actingAs($dev)->get('/admin/utilisateurs')->assertOk();
        $this->actingAs($dev)->get('/labo')->assertOk();
        $this->actingAs($dev)->get('/admin/utilisateurs')->assertInertia(fn ($p) => $p->where('utilisateurs', fn ($u) => collect($u)->doesntContain('email', 'dev@secret.ma')));
        $this->actingAs($dev)->post('/admin/utilisateurs', ['name' => 'Pirate', 'email' => 'p@p.ma', 'role' => 'developpeur', 'password' => 'password123'])->assertSessionHasErrors('role');
        // La chef ne gère plus ni les utilisateurs, ni les salles, ni les paramètres
        $this->actingAs($chef)->get('/admin/utilisateurs')->assertForbidden();
        $this->actingAs($chef)->post('/admin/utilisateurs', ['name' => 'X', 'email' => 'x@x.ma', 'role' => 'docteur', 'password' => 'password123'])->assertForbidden();
        $this->actingAs($chef)->put('/admin/cabinet', ['nom' => 'X'])->assertForbidden();
        $this->actingAs($chef)->post('/admin/salles', ['nom' => 'Salle 9'])->assertForbidden();
        $this->actingAs($chef)->get('/admin/tableau-de-bord')->assertOk();
    }

    public function test_ordonnance_et_ecran_tv(): void
    {
        $nassim = $this->docteurDisponible();
        $patient = $this->patientAvecDossier();
        $this->actingAs($nassim)->post("/dossiers/{$patient->dossier->id}/ordonnances", [
            'lignes' => [['medicament' => 'Paracétamol 1 g', 'posologie' => '3 fois par jour', 'duree' => '3 jours']],
        ])->assertSessionHasNoErrors();
        $o = \App\Models\Ordonnance::first();
        $this->actingAs($nassim)->get("/ordonnances/{$o->id}/imprimer")->assertOk()->assertSee('Paracétamol 1 g');

        $rdv = RendezVous::create(['patient_id' => $patient->id, 'docteur_id' => $nassim->id, 'mission' => 'Soin', 'debut' => now(), 'fin' => now()->addMinutes(30), 'statut' => StatutRdv::EnCours, 'numero_appel' => 7, 'appele_le' => now()]);
        $this->actingAs($this->user(Role::Reception))->get('/tv')
            ->assertInertia(fn ($p) => $p->where('salles.0.nom', 'Salle 2')->where('salles.0.patient', 'Karim Z.')->where('salles.0.numero', 7));
    }
}

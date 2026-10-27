<?php

use App\Http\Controllers\AccueilController;
use App\Http\Controllers\ActeController;
use App\Http\Controllers\Admin\CatalogueController;
use App\Http\Controllers\Admin\DisponibiliteController;
use App\Http\Controllers\Admin\TableauDeBordController;
use App\Http\Controllers\Admin\UtilisateurController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\DocteurController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DossierController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LaboController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\SalleAttenteController;
use App\Http\Controllers\SeanceController;
use Illuminate\Support\Facades\Route;

// Fiche d'accueil sur la tablette (sans connexion)
Route::get('accueil', [AccueilController::class, 'create'])->name('accueil');
Route::post('accueil', [AccueilController::class, 'store'])->middleware('throttle:20,1')->name('accueil.store');
// Carte patient : le QR code ouvre « Mon dossier » (code = 4 derniers chiffres du téléphone)
Route::middleware('espace.patient')->group(function () {
Route::get('c/{token}', [\App\Http\Controllers\CarteController::class, 'public'])->name('carte.public');
Route::post('c/{token}', [\App\Http\Controllers\CarteController::class, 'verifier'])->middleware('throttle:20,1')->name('carte.verifier');
Route::get('c/{token}/photo', [\App\Http\Controllers\CarteController::class, 'photo'])->name('carte.photo');
});

// « Mon espace patient » (sans compte) : voir son dossier et prendre un RDV sur les créneaux libres
// Langue des pages patient (français / arabe)
Route::get('langue/{langue}', function (string $langue) {
    session(['langue' => $langue === 'ar' ? 'ar' : 'fr']);

    return back();
})->whereIn('langue', ['fr', 'ar'])->name('langue');

Route::middleware('espace.patient')->prefix('mon-espace')->name('espace.')->controller(\App\Http\Controllers\EspacePatientController::class)->group(function () {
    Route::get('/', 'accueil')->name('accueil');
    Route::post('connexion', 'connexion')->middleware('throttle:20,1')->name('connexion');
    Route::post('nouveau', 'nouveau')->middleware('throttle:10,1')->name('nouveau');
    Route::get('dossier', 'index')->name('index');
    Route::get('creneaux', 'creneaux')->name('creneaux');
    Route::post('reserver', 'reserver')->middleware('throttle:10,1')->name('reserver');
    Route::post('avis', 'avis')->middleware('throttle:10,1')->name('avis');
    Route::get('photo', 'photo')->name('photo');
    Route::post('deconnexion', 'deconnexion')->name('deconnexion');
});
Route::get('accueil/merci', [AccueilController::class, 'merci'])->name('accueil.merci');

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified', 'role'])->group(function () {
    Route::get('dashboard', HomeController::class)->name('dashboard');

    // Tous les membres de l'équipe (sauf labo) : patients et dossiers
    Route::middleware('role:secretaire,docteur,admin_finance,photographe,docteur_labo,radiologue')->group(function () {
        Route::get('patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('recherche-patients', [SalleAttenteController::class, 'recherchePatients'])->name('patients.recherche');
        Route::get('dossiers/{dossier}', [DossierController::class, 'show'])->withTrashed()->name('dossiers.show');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::get('ordonnances/{ordonnance}/imprimer', [\App\Http\Controllers\OrdonnanceController::class, 'imprimer'])->name('ordonnances.imprimer');
    });

    // Réception
    Route::middleware('role:secretaire')->group(function () {
        Route::get('fiches', [PatientController::class, 'fiches'])->name('fiches.index');
        Route::post('fiches/{patient}/valider', [PatientController::class, 'valider'])->name('fiches.valider');
        Route::delete('fiches/{patient}', [PatientController::class, 'destroy'])->name('fiches.destroy');
        Route::post('fiches/{patient}/rattacher/{existant}', [PatientController::class, 'rattacher'])->name('fiches.rattacher');
        Route::post('dossiers/{dossier}/fusionner', [DossierController::class, 'fusionner'])->name('dossiers.fusionner');
        Route::post('patients', [PatientController::class, 'store'])->name('patients.store');
        Route::put('patients/{patient}', [PatientController::class, 'update'])->name('patients.update');

        Route::get('salle-attente', [SalleAttenteController::class, 'index'])->name('salle-attente.index');
        Route::post('salle-attente/sans-rdv', [SalleAttenteController::class, 'sansRdv'])->name('salle-attente.sans-rdv');
        Route::post('rendez-vous/{rdv}/arrive', [SalleAttenteController::class, 'arrive'])->name('rdv.arrive');
        Route::post('rendez-vous/{rdv}/absent', [SalleAttenteController::class, 'absent'])->name('rdv.absent');
        Route::post('rendez-vous/{rdv}/envoyer', [SalleAttenteController::class, 'envoyer'])->name('rdv.envoyer');
        Route::get('tv', [SalleAttenteController::class, 'tv'])->name('tv');

        Route::get('agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::get('agenda/imprimer', [AgendaController::class, 'imprimer'])->name('agenda.imprimer');
        Route::post('agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::get('patients/{patient}/actes', [AgendaController::class, 'actesPatient'])->name('patients.actes');
        Route::post('patients/{patient}/tablette', [\App\Http\Controllers\PatientController::class, 'versTablette'])->name('patients.tablette');
        Route::post('rdv-a-fixer/{aFixer}/retirer', [AgendaController::class, 'retirerAFixer'])->name('rdv-a-fixer.retirer');
        Route::get('a-rappeler', [DossierController::class, 'aRappeler'])->name('a-rappeler');
    });

    // Rendez-vous : la réception, ou chaque docteur pour ses propres rendez-vous
    Route::middleware('role:secretaire,docteur')->group(function () {
        Route::put('rendez-vous/{rdv}', [AgendaController::class, 'modifier'])->name('rdv.modifier');
        Route::post('rendez-vous/{rdv}/annuler', [AgendaController::class, 'annuler'])->name('rdv.annuler');
        Route::delete('rendez-vous/{rdv}', [AgendaController::class, 'supprimer'])->name('rdv.supprimer');
        Route::post('dossiers/{dossier}/prochain-rdv', [AgendaController::class, 'prochain'])->name('rdv.prochain');
    });
    Route::get('agenda/creneaux', [AgendaController::class, 'creneaux'])->middleware('role:secretaire,docteur,admin_finance')->name('agenda.creneaux');

    // Plans proposés après la consultation : la responsable finance (Fadwa) présente le devis,
    // le patient accepte tout, une partie ou rien ; elle fixe le docteur et la date
    Route::middleware('role:admin_finance')->group(function () {
        Route::get('plans', [\App\Http\Controllers\PlanController::class, 'index'])->name('plans.index');
        Route::post('plans/{plan}/valider', [\App\Http\Controllers\PlanController::class, 'valider'])->name('plans.valider');
    });
    Route::post('dossiers/{dossier}/demandes-radio', [\App\Http\Controllers\RadioController::class, 'demander'])
        ->middleware('role:secretaire,docteur')->name('radios.demander');
    Route::put('dossiers/{dossier}/antecedents', [DossierController::class, 'updateAntecedents'])
        ->middleware('role:secretaire,docteur')->name('dossiers.antecedents');

    // Documents (CIN, photos, radios…)
    Route::middleware('role:secretaire,docteur,photographe,radiologue')->group(function () {
        Route::post('patients/{patient}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::middleware('espace.patient')->get('patients/{patient}/carte', [\App\Http\Controllers\CarteController::class, 'imprimer'])->name('carte.imprimer');
        Route::middleware('espace.patient')->post('patients/{patient}/carte/nouveau-lien', [\App\Http\Controllers\CarteController::class, 'nouveauLien'])->name('carte.nouveau-lien');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Docteurs
    Route::middleware('role:docteur')->group(function () {
        Route::get('mes-patients', [DocteurController::class, 'patients'])->name('docteur.patients');
        Route::post('mon-etat', [DocteurController::class, 'etat'])->name('docteur.etat');
        Route::put('dossiers/{dossier}/medical', [DossierController::class, 'updateMedical'])->name('dossiers.medical');
        Route::post('dossiers/{dossier}/dents', [DossierController::class, 'updateDent'])->name('dossiers.dents');
        Route::post('dossiers/{dossier}/seances', [SeanceController::class, 'demarrer'])->name('seances.demarrer');
        Route::post('dossiers/{dossier}/ordonnances', [\App\Http\Controllers\OrdonnanceController::class, 'store'])->name('ordonnances.store');
        Route::post('seances/{seance}/terminer', [SeanceController::class, 'terminer'])->name('seances.terminer');
        Route::get('mes-horaires', [DisponibiliteController::class, 'index'])->name('docteur.horaires');
        Route::put('horaires/{docteur}', [DisponibiliteController::class, 'update'])->name('horaires.update');
    });
    // Actes : le docteur, et la réception pour reprendre les anciens dossiers papier
    Route::post('dossiers/{dossier}/actes', [ActeController::class, 'store'])->middleware('role:docteur,secretaire')->name('actes.store');
    Route::delete('actes/{acte}', [ActeController::class, 'destroy'])->middleware('role:docteur,secretaire')->name('actes.destroy');
    Route::put('actes/{acte}', [ActeController::class, 'update'])->middleware('role:docteur,admin_finance')->name('actes.update');
    Route::post('dossiers/{dossier}/labo', [LaboController::class, 'store'])->middleware('role:docteur,secretaire,admin_finance')->name('labo.store');

    // Laboratoire
    Route::middleware('role:docteur_labo')->group(function () {
        Route::get('labo', [LaboController::class, 'index'])->name('labo.index');
        Route::put('labo/{commande}', [LaboController::class, 'update'])->name('labo.update');
        Route::post('labo/{commande}/accepter', [LaboController::class, 'accepter'])->name('labo.accepter');
        Route::post('labo/radios/{radio}/vue', [LaboController::class, 'radioVue'])->name('labo.radio-vue');
        Route::post('labo/radios/{radio}/archiver', [LaboController::class, 'radioArchiver'])->name('labo.radio-archiver');
        Route::post('labo/radios/{radio}/desarchiver', [LaboController::class, 'radioDesarchiver'])->name('labo.radio-desarchiver');
        Route::post('labo/{commande}/archiver', [LaboController::class, 'archiver'])->name('labo.archiver');
        Route::post('labo/{commande}/desarchiver', [LaboController::class, 'desarchiver'])->name('labo.desarchiver');
    });

    // Finance : la responsable finance fait les reçus ; seul le chef voit les totaux
    Route::middleware('role:admin_finance')->group(function () {
        Route::post('dossiers/{dossier}/paiements', [FinanceController::class, 'store'])->name('paiements.store');
        Route::get('paiements/{paiement}/recu', [FinanceController::class, 'recu'])->name('paiements.recu');
    });
    Route::middleware('role:admin')->group(function () {
        Route::get('impayes', [FinanceController::class, 'impayes'])->name('finance.impayes');
        Route::get('caisse', [\App\Http\Controllers\CaisseController::class, 'index'])->name('caisse.index');
        Route::post('caisse/cloturer', [\App\Http\Controllers\CaisseController::class, 'cloturer'])->name('caisse.cloturer');
    });

    // Radiologie (Ikram)
    Route::middleware('role:radiologue')->group(function () {
        Route::get('radiologie', [\App\Http\Controllers\RadioController::class, 'index'])->name('radio.index');
        Route::post('dossiers/{dossier}/radios', [\App\Http\Controllers\RadioController::class, 'store'])->name('radios.store');
    });

    // Chef (et développeur) : supprimer / restaurer un dossier
    Route::middleware('role:admin')->group(function () {
        Route::delete('dossiers/{dossier}', [DossierController::class, 'supprimer'])->name('dossiers.supprimer');
        Route::post('patients/{patient}/restaurer', [PatientController::class, 'restaurer'])->name('patients.restaurer');
    });

    // Chef / Admin
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');
        Route::get('catalogue', [CatalogueController::class, 'index'])->name('catalogue.index');
        Route::post('catalogue', [CatalogueController::class, 'store'])->name('catalogue.store');
        Route::put('catalogue/{acte}', [CatalogueController::class, 'update'])->name('catalogue.update');
        Route::get('horaires', [DisponibiliteController::class, 'index'])->name('horaires.index');
        Route::get('avis', [\App\Http\Controllers\AvisController::class, 'index'])->name('avis.index');
    });
    // Réservé au compte développeur : utilisateurs, salles, paramètres du cabinet
    Route::middleware('role:developpeur')->prefix('admin')->name('admin.')->group(function () {
        Route::get('utilisateurs', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::post('utilisateurs', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
        Route::put('utilisateurs/{user}', [UtilisateurController::class, 'update'])->name('utilisateurs.update');
        Route::delete('utilisateurs/{user}', [UtilisateurController::class, 'destroy'])->name('utilisateurs.destroy');
        Route::get('cabinet', [\App\Http\Controllers\Admin\CabinetController::class, 'index'])->name('cabinet.index');
        Route::put('cabinet', [\App\Http\Controllers\Admin\CabinetController::class, 'update'])->name('cabinet.update');
        Route::post('salles', [\App\Http\Controllers\Admin\CabinetController::class, 'storeSalle'])->name('salles.store');
        Route::put('salles/{salle}', [\App\Http\Controllers\Admin\CabinetController::class, 'updateSalle'])->name('salles.update');
    });
});

require __DIR__.'/settings.php';

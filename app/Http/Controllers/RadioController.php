<?php

namespace App\Http\Controllers;

use App\Enums\StatutRdv;
use App\Models\Dossier;
use App\Models\Radio;
use App\Models\RendezVous;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Espace radiologie (Ikram) : la radio est ajoutée au dossier et part automatiquement au labo. */
class RadioController extends Controller
{
    public function index(): Response
    {
        $patients = RendezVous::duJour()
            ->whereIn('statut', [StatutRdv::Planifie->value, StatutRdv::Arrive->value, StatutRdv::EnCours->value])
            ->with(['patient:id,nom,prenom,telephone,ville', 'patient.dossier:id,patient_id,numero', 'docteur:id,name'])
            ->orderBy('debut')->get()
            ->filter(fn ($r) => $r->patient?->dossier)->values();

        return Inertia::render('radio/index', [
            'demandes' => \App\Models\DemandeRadio::where('statut', 'en_attente')
                ->with(['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom,telephone', 'demandePar:id,name'])
                ->oldest()->get(),
            'patients' => $patients,
            'radios' => Radio::whereDate('created_at', today())
                ->with(['dossier:id,numero,patient_id', 'dossier.patient:id,nom,prenom', 'documents:id,radio_id,nom_original', 'fairePar:id,name'])
                ->latest()->get(),
            'types' => Radio::TYPES,
        ]);
    }

    /** Un docteur (ou la réception) envoie le patient à la radiologie. */
    public function demander(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Radio::TYPES))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        // Déjà envoyée (en attente) ou déjà faite aujourd'hui : on ne renvoie pas
        $libelle = Radio::TYPES[$data['type']];
        if ($dossier->demandesRadio()->where('type', $data['type'])->where('statut', 'en_attente')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type' => "{$libelle} déjà envoyée à la radio (en attente)."]);
        }
        if ($dossier->radios()->where('type', $data['type'])->whereDate('created_at', today())->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['type' => "{$libelle} déjà faite aujourd'hui : voir l'onglet Documents."]);
        }
        $dossier->demandesRadio()->create($data + ['demande_par' => $request->user()->id]);
        $this->ok('Demande de radio envoyée à la radiologie.');

        return back();
    }

    public function store(Request $request, Dossier $dossier): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(Radio::TYPES))],
            'dents' => ['nullable', 'string', 'max:100'],
            'mesures' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'demande_id' => ['nullable', 'exists:demandes_radio,id'],
            'fichiers' => ['required', 'array', 'max:10'],
            'fichiers.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,heic,pdf,dcm,zip,stl'],
        ]);

        $radio = $dossier->radios()->create(collect($data)->except(['fichiers', 'demande_id'])->all() + ['faite_par' => $request->user()->id]);
        // la demande du docteur (ou de la consultation) est faite
        \App\Models\DemandeRadio::where('dossier_id', $dossier->id)->where('statut', 'en_attente')
            ->where(fn ($q) => $q->where('type', $data['type'])->when($data['demande_id'] ?? null, fn ($x, $id) => $x->orWhere('id', $id)))
            ->update(['statut' => 'faite', 'radio_id' => $radio->id]);
        foreach ($data['fichiers'] as $f) {
            $dossier->patient->documents()->create([
                'type' => 'radio',
                'radio_id' => $radio->id,
                'chemin' => $f->store("patients/{$dossier->patient_id}", 'local'),
                'nom_original' => $f->getClientOriginalName(),
                'ajoute_par' => $request->user()->id,
            ]);
        }
        $this->ok("Radio de {$dossier->patient->nom_complet} ajoutée au dossier et envoyée au labo.");

        return back();
    }
}

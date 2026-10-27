<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\Journal;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/** Regroupe deux dossiers d'un même patient (doublon) sans rien perdre. */
class FusionPatients
{
    /** Complète la fiche gardée avec les infos de l'autre (sans écraser ce qui est déjà rempli). */
    public static function completerPatient(Patient $garder, Patient $autre): void
    {
        $champs = ['date_naissance', 'sexe', 'cin', 'email', 'ville', 'adresse', 'profession', 'antecedents_autres', 'traitements'];
        $maj = [];
        foreach ($champs as $c) {
            if (blank($garder->{$c}) && filled($autre->{$c})) {
                $maj[$c] = $autre->{$c};
            }
        }
        // allergies : on additionne, c'est une info de sécurité
        $allergies = collect([$garder->allergies, $autre->allergies])->filter()->map(fn ($a) => trim($a))->unique(fn ($a) => mb_strtolower($a));
        if ($allergies->count() > 1 || (blank($garder->allergies) && $allergies->isNotEmpty())) {
            $maj['allergies'] = $allergies->implode(', ');
        }
        $maj['antecedents'] = array_values(array_unique([...($garder->antecedents ?? []), ...($autre->antecedents ?? [])]));

        // téléphone : on garde le plus récent comme principal, l'autre part dans l'historique
        $tels = collect([...($garder->anciens_telephones ?? []), ...($autre->anciens_telephones ?? [])]);
        if (filled($autre->telephone) && $autre->telephone !== $garder->telephone) {
            if (blank($garder->telephone)) {
                $maj['telephone'] = $autre->telephone;
            } else {
                $tels->push($autre->telephone);
            }
        }
        $maj['anciens_telephones'] = $tels->filter()->unique()->values()->all();

        $garder->update($maj);
    }

    /** Déplace tout le contenu du dossier doublon vers le dossier gardé. */
    public static function fusionner(Dossier $garder, Dossier $doublon): void
    {
        abort_if($garder->id === $doublon->id, 422, 'Choisissez deux dossiers différents.');

        DB::transaction(function () use ($garder, $doublon) {
            $pG = $garder->patient;
            $pD = $doublon->patient;

            DB::table('rendez_vous')->where('patient_id', $pD->id)->update(['patient_id' => $pG->id]);
            DB::table('documents')->where('patient_id', $pD->id)->update(['patient_id' => $pG->id]);
            foreach (['seances', 'actes', 'commandes_labo', 'paiements'] as $table) {
                DB::table($table)->where('dossier_id', $doublon->id)->update(['dossier_id' => $garder->id]);
            }
            $dejaNotees = DB::table('etat_dents')->where('dossier_id', $garder->id)->pluck('dent');
            DB::table('etat_dents')->where('dossier_id', $doublon->id)->whereNotIn('dent', $dejaNotees)->update(['dossier_id' => $garder->id]);

            self::completerPatient($pG, $pD);
            $garder->update(array_filter([
                'ancien_numero_papier' => $garder->ancien_numero_papier ?: $doublon->ancien_numero_papier,
                'docteur_consultation_id' => $garder->docteur_consultation_id ?: $doublon->docteur_consultation_id,
                'diagnostic' => collect([$garder->diagnostic, $doublon->diagnostic])->filter()->unique()->implode("\n"),
                'notes' => collect([$garder->notes, $doublon->notes, "Fusionné avec le dossier n° {$doublon->numero}."])->filter()->implode("\n"),
            ], fn ($v) => $v !== null && $v !== ''));

            $doublon->update(['fusionne_dans_id' => $garder->id]);
            $doublon->delete();
            $pD->delete();

            Journal::create([
                'user_id' => auth()->id(), 'action' => 'fusion', 'sujet_type' => 'Dossier', 'sujet_id' => $garder->id,
                'avant' => ['dossier_fusionne' => $doublon->numero, 'patient' => $pD->nom_complet], 'apres' => ['dossier_garde' => $garder->numero],
            ]);
        });
    }
}

<?php

namespace App\Services;

use App\Models\MessagePatient;
use App\Models\Patient;

/** Message au patient : toujours dans « Mon espace », et par SMS seulement s'il a accepté les SMS (consentement CNDP). */
class Messagerie
{
    public static function envoyer(Patient $patient, string $type, string $titre, string $texte, ?int $par = null, ?string $cle = null): ?MessagePatient
    {
        if ($cle && MessagePatient::where('patient_id', $patient->id)->where('cle', $cle)->exists()) {
            return null; // rappel automatique déjà envoyé
        }
        $m = MessagePatient::create([
            'patient_id' => $patient->id, 'type' => $type, 'titre' => $titre, 'texte' => $texte,
            'cle' => $cle, 'envoye_par' => $par,
        ]);
        if ($patient->consentement_sms && ($num = Sms::numero($patient->telephone))) {
            [$ok] = Sms::envoyer($num, config('cabinet.nom', 'Cabinet dentaire').' : '.$texte);
            $m->update(['sms_envoye' => $ok]);
        }

        return $m;
    }
}

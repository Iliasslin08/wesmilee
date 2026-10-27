<?php

namespace App\Models;

use App\Models\Concerns\Journalise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ordonnance extends Model
{
    use Journalise;

    /** Suggestions rapides pour le docteur (modifiables avant l'impression). */
    public const SUGGESTIONS = [
        ['medicament' => 'Amoxicilline 1 g', 'posologie' => '1 comprimé matin et soir', 'duree' => '6 jours'],
        ['medicament' => 'Paracétamol 1 g', 'posologie' => '1 comprimé jusqu\'à 3 fois par jour si douleur', 'duree' => '3 jours'],
        ['medicament' => 'Ibuprofène 400 mg', 'posologie' => '1 comprimé 3 fois par jour au milieu du repas', 'duree' => '3 jours'],
        ['medicament' => 'Bain de bouche chlorhexidine 0,12 %', 'posologie' => '2 fois par jour après le brossage', 'duree' => '7 jours'],
        ['medicament' => 'Métronidazole 500 mg', 'posologie' => '1 comprimé 3 fois par jour', 'duree' => '7 jours'],
        ['medicament' => 'Gel gingival', 'posologie' => 'Appliquer 3 fois par jour', 'duree' => '5 jours'],
    ];

    protected $guarded = ['id'];

    protected $casts = ['lignes' => 'array', 'date' => 'date'];

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function docteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docteur_id')->withTrashed();
    }
}

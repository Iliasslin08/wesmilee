<?php

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => 'Le champ :attribute n’est pas une date valide.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => 'La valeur du champ :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'image' => 'Le champ :attribute doit être une image.',
    'in' => 'La valeur du champ :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas dépasser :max Ko.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'mimes' => 'Le fichier doit être de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit faire au moins :min Ko.',
        'numeric' => 'Le champ :attribute doit être au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'Ce :attribute est déjà utilisé.',
    'uploaded' => 'Le fichier :attribute n’a pas pu être envoyé.',

    'attributes' => [
        'nom' => 'nom', 'prenom' => 'prénom', 'telephone' => 'téléphone', 'cin' => 'n° CIN',
        'date_naissance' => 'date de naissance', 'email' => 'e-mail', 'password' => 'mot de passe',
        'fichiers' => 'fichiers', 'cin_fichiers' => 'carte nationale', 'papiers' => 'papiers',
        'montant' => 'montant', 'debut' => 'début', 'mission' => 'mission', 'docteur_id' => 'docteur',
    ],
];

<?php

return [
    // Valeurs par défaut : le chef peut les changer dans « Cabinet & salles »
    'nom' => env('CABINET_NOM', 'Wesmile Center'),
    'adresse' => env('CABINET_ADRESSE', 'Résidence Narjiss, 68 av. Fal Ould Oumeir, Agdal'),
    'ville' => env('CABINET_VILLE', 'Rabat'),
    'telephone' => env('CABINET_TELEPHONE', '05 37 73 73 77'),
    'site' => env('CABINET_SITE', 'www.wesmilegrp.com'),
    'slogan' => env('CABINET_SLOGAN', 'Urgence dentaire 24h/24'),
    'whatsapp' => env('CABINET_WHATSAPP', ''),
    'email' => env('CABINET_EMAIL', ''),
    'chef_plateau' => env('CABINET_CHEF_PLATEAU', ''),
    // Continue la numérotation papier
    'premier_numero_dossier' => (int) env('PREMIER_NUMERO_DOSSIER', 3100),
    'premier_numero_recu' => (int) env('PREMIER_NUMERO_RECU', 2201),
    'duree_rdv_defaut' => 30,
    // Déconnexion automatique après X minutes sans activité (0 = jamais)
    'inactivite_minutes' => (int) env('CABINET_INACTIVITE_MINUTES', 20),
    // Alerte si une séance reste ouverte plus de X minutes
    'seance_max_minutes' => (int) env('CABINET_SEANCE_MAX_MINUTES', 120),
    // Adresse utilisée dans le QR code de la carte patient (doit être joignable depuis le téléphone du patient).
    // Au cabinet : http://ADRESSE-IP-DU-PC:8000  ·  En ligne : https://app.wesmile.ma
    'carte_url' => env('CARTE_URL', env('APP_URL')),
    // false = mode local en attendant la CNDP : pas de QR code, pas de « Mon espace » patient
    'espace_patient' => (bool) env('ESPACE_PATIENT', true),
];

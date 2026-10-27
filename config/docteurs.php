<?php

/*
 | Docteurs du cabinet (fiches envoyées par les docteurs).
 | Pour ajouter / modifier un docteur : compléter cette liste puis lancer « php artisan cabinet:docteurs ».
 | - soins : ce qu'il fait habituellement (★ dans l'agenda). Les autres soins restent possibles (exception).
 | - consultation : true s'il fait les consultations / contrôles.
 | - duree : durée habituelle d'un RDV en minutes.
 | - salle : vide (pas de salle fixe).
 */
return [
    [
        'nom' => 'Dr ELBAKKOURI Malika',
        'email' => 'malikaelbakkouri.ma@gmail.com',
        'telephone' => '0664407657',
        'specialite' => 'Chirurgie / Implantologie',
        'soins' => 'All on 4/6, extraction chirurgicale, greffe, implant',
        'autres' => null,
        'consultation' => false,
        'duree' => 120,
        'assistante' => 'Basma',
        'couleur' => '#2f6fb3',
    ],
    [
        'nom' => 'Dr NASMA Nassime',
        'email' => 'nassimenasma92@gmail.com',
        'telephone' => '0666927730',
        'specialite' => 'Dentisterie esthétique et chirurgie',
        'soins' => 'Consultation, contrôle, extraction, soin dentaire, traitement canalaire, orthodontie',
        'autres' => null,
        'consultation' => true,
        'duree' => 10,
        'assistante' => 'Malak Cherkaoui',
        'couleur' => '#138c8f',
    ],
    [
        'nom' => 'Dr OUIZZOU Omar',
        'email' => 'ouizzou2001@gmail.com',
        'telephone' => '0706767095',
        'specialite' => 'Prothèse',
        'soins' => 'All on 4/6 (prothèse), bridge implanto-porté, pose de piliers, prothèse',
        'autres' => null,
        'consultation' => false,
        'duree' => 60,
        'assistante' => 'Amina',
        'couleur' => '#6db33f',
    ],
    [
        'nom' => 'Dr EL KABD Ilham',
        'email' => 'ilhamsoulaimi07@gmail.com',
        'telephone' => '0697719109',
        'specialite' => 'Endodontie',
        'soins' => 'Détartrage, soin de carie, endodontie, reprise endo, extraction, couronne, bridge, prothèse, éclaircissement',
        'autres' => null,
        'consultation' => false,
        'duree' => 60,
        'assistante' => 'Ikram',
        'couleur' => '#c2410c',
    ],
    [
        'nom' => 'Dr EL MAACHI Oualid',
        'email' => 'dr.elmaachioualid@outlook.com',
        'telephone' => '0624726453',
        'specialite' => 'ODF / Chirurgie',
        'soins' => 'Orthodontie, orthopédie, prothèse supra-implantaire, chirurgie',
        'autres' => null,
        'consultation' => false,
        'duree' => 30,
        'assistante' => null,
        'couleur' => '#8a5cc2',
    ],
    [
        'nom' => 'Dr ENNACIRI Yasmine',
        'email' => 'yasmine.ennaciri98@gmail.com',
        'telephone' => '0613025198',
        'specialite' => 'Endodontie',
        'soins' => 'Détartrage, soin de carie, endodontie, reprise endo, extraction, couronne, bridge, prothèse, éclaircissement',
        'autres' => null,
        'consultation' => false,
        'duree' => 60,
        'assistante' => 'Malak Ziani',
        'couleur' => '#d0566c',
    ],
    [
        'nom' => 'Dr DYUKOV Dmitry',
        'email' => 'dmitry.dyukov@wesmile.ma', // pas d'e-mail donné : à remplacer par son vrai e-mail
        'telephone' => null,
        'specialite' => 'Chirurgie maxillo-faciale',
        'soins' => 'Ouverture d’implants, curetage paro, extraction chirurgicale, exérèse de kyste',
        'autres' => null,
        'consultation' => false,
        'duree' => 60, // entre 30 min et 2 h selon le soin
        'assistante' => 'Sabah Alabouche',
        'couleur' => '#0e7490',
    ],
    [
        'nom' => 'Dr BASKALI Oussama',
        'email' => 'baskali_oussama@hotmail.com',
        'telephone' => '0670595993',
        'specialite' => 'Chirurgie / Implantologie / Endodontie / Prothèse fixée',
        'soins' => 'Traitement canalaire, endodontie, prothèse fixée, couronne, implant, prothèse sur implants, extraction chirurgicale',
        'autres' => null,
        'consultation' => false,
        'duree' => 60, // entre 45 min et 1 h
        'assistante' => 'Malak Ziani',
        'couleur' => '#7c3aed',
    ],
];

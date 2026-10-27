<?php

/*
 * Rappels SMS des rendez-vous (envoyés automatiquement la veille).
 * SMS_DRIVER :
 *   - log    : rien n'est envoyé, le SMS est seulement écrit dans storage/logs (pour tester)
 *   - twilio : compte Twilio (SMS_TWILIO_SID, SMS_TWILIO_TOKEN, SMS_EXPEDITEUR = numéro ou nom)
 *   - http   : fournisseur marocain avec une API HTTP (SMS_HTTP_URL, SMS_HTTP_TOKEN, SMS_EXPEDITEUR)
 */
return [
    'driver' => env('SMS_DRIVER', 'log'),
    'actif' => (bool) env('SMS_RAPPELS_ACTIFS', true),
    'expediteur' => env('SMS_EXPEDITEUR', 'WESMILE'),
    // Heure d'envoi des rappels pour les rendez-vous du lendemain
    'heure' => env('SMS_HEURE', '18:00'),
    'twilio' => [
        'sid' => env('SMS_TWILIO_SID'),
        'token' => env('SMS_TWILIO_TOKEN'),
    ],
    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'token' => env('SMS_HTTP_TOKEN'),
    ],
    // Sans accents : un SMS = 160 caractères (avec accents : 70 seulement, donc plus cher)
    'message' => env('SMS_MESSAGE', 'Bonjour :prenom, rappel de votre RDV au :cabinet le :date a :heure avec :docteur. Empechement ? Appelez le :telephone.'),
];

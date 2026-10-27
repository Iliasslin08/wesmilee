<?php

namespace App\Support;

/**
 * Pages patient (espace, carte QR) en français ou en arabe.
 * Choix gardé en session ; par défaut, la langue du téléphone si c'est l'arabe.
 */
class Langue
{
    public static function ar(): bool
    {
        $choix = session('langue');
        if ($choix) {
            return $choix === 'ar';
        }

        return str_starts_with(strtolower((string) request()->header('Accept-Language')), 'ar');
    }

    public static function t(string $fr, string $ar): string
    {
        return self::ar() ? $ar : $fr;
    }

    /** Statuts (actes, labo) et motifs traduits. */
    public static function mot(string $fr): string
    {
        if (! self::ar()) {
            return $fr;
        }

        return [
            'À faire' => 'مبرمج', 'En cours' => 'جارٍ', 'Fait' => 'تم', 'Annulé' => 'ملغى',
            'À commencer' => 'لم يبدأ', 'En fabrication' => 'قيد الصنع', 'Prêt' => 'جاهز', 'Livré au centre' => 'وصل إلى المركز', 'Posé' => 'تم التركيب',
            'Consultation' => 'استشارة', 'Contrôle' => 'مراقبة',
            'Espèces' => 'نقدًا', 'Carte' => 'بطاقة', 'Chèque' => 'شيك', 'Virement' => 'تحويل',
        ][$fr] ?? $fr;
    }

    /** Date lisible dans la langue choisie : « lundi 20 octobre » / « الاثنين 20 أكتوبر ». */
    public static function date(\DateTimeInterface $d, string $format = 'l j F'): string
    {
        return \Illuminate\Support\Carbon::instance($d)->locale(self::ar() ? 'ar' : 'fr')->translatedFormat($format);
    }
}

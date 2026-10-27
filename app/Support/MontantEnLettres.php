<?php

namespace App\Support;

/** Écrit un montant en toutes lettres (français), ex. 1250,50 → « mille deux cent cinquante dirhams et cinquante centimes ». */
class MontantEnLettres
{
    private const UNITES = ['zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix',
        'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];

    private const DIZAINES = [2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante'];

    public static function dirhams(float $montant): string
    {
        $montant = round($montant, 2);
        $dh = (int) floor($montant);
        $cts = (int) round(($montant - $dh) * 100);
        $texte = self::nombre($dh).' dirham'.($dh > 1 ? 's' : '');
        if ($cts > 0) {
            $texte .= ' et '.self::nombre($cts).' centime'.($cts > 1 ? 's' : '');
        }

        return $texte;
    }

    public static function nombre(int $n): string
    {
        if ($n < 0) {
            return 'moins '.self::nombre(-$n);
        }
        if ($n < 1000) {
            return self::centaines($n, true);
        }
        $parties = [];
        foreach ([1_000_000_000 => 'milliard', 1_000_000 => 'million'] as $valeur => $mot) {
            if ($n >= $valeur) {
                $q = intdiv($n, $valeur);
                $parties[] = self::centaines($q, false).' '.$mot.($q > 1 ? 's' : '');
                $n %= $valeur;
            }
        }
        if ($n >= 1000) {
            $q = intdiv($n, 1000);
            $parties[] = $q === 1 ? 'mille' : self::centaines($q, false).' mille';
            $n %= 1000;
        }
        if ($n > 0) {
            $parties[] = self::centaines($n, true);
        }

        return implode(' ', $parties);
    }

    /** 0–999. $final : « cents » et « quatre-vingts » prennent un s seulement en fin de nombre. */
    private static function centaines(int $n, bool $final): string
    {
        if ($n < 100) {
            return self::dizaines($n, $final);
        }
        $c = intdiv($n, 100);
        $reste = $n % 100;
        $txt = $c === 1 ? 'cent' : self::UNITES[$c].' cent';
        if ($reste === 0) {
            return $txt.($c > 1 && $final ? 's' : '');
        }

        return $txt.' '.self::dizaines($reste, $final);
    }

    private static function dizaines(int $n, bool $final): string
    {
        if ($n < 20) {
            return self::UNITES[$n];
        }
        $d = intdiv($n, 10);
        $u = $n % 10;
        if ($d === 7 || $d === 9) { // soixante-dix, quatre-vingt-dix
            $base = $d === 7 ? 'soixante' : 'quatre-vingt';
            $suite = 10 + $u;

            return $base.($d === 7 && $u === 1 ? ' et ' : '-').self::UNITES[$suite];
        }
        if ($d === 8) {
            return $u === 0 ? 'quatre-vingt'.($final ? 's' : '') : 'quatre-vingt-'.self::UNITES[$u];
        }
        $base = self::DIZAINES[$d];
        if ($u === 0) {
            return $base;
        }

        return $base.($u === 1 ? ' et un' : '-'.self::UNITES[$u]);
    }
}

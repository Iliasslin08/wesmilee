<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Envoi d'un SMS via le fournisseur choisi dans config/sms.php. Retourne [ok, statut, erreur]. */
class Sms
{
    /** 06 12 34 56 78 → +212612345678 (null si le numéro n'est pas un mobile marocain valide). */
    public static function numero(?string $tel): ?string
    {
        $c = preg_replace('/\D/', '', (string) $tel);
        if (str_starts_with($c, '00')) {
            $c = substr($c, 2);
        }
        if (str_starts_with($c, '212')) {
            $c = substr($c, 3);
        }
        $c = ltrim($c, '0');

        return preg_match('/^[67]\d{8}$/', $c) ? '+212'.$c : null;
    }

    /** @return array{0: bool, 1: string, 2: ?string} */
    public static function envoyer(string $numero, string $message): array
    {
        $driver = config('sms.driver');
        try {
            switch ($driver) {
                case 'twilio':
                    $sid = config('sms.twilio.sid');
                    $r = Http::asForm()->withBasicAuth($sid, (string) config('sms.twilio.token'))->timeout(20)
                        ->post("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json", [
                            'To' => $numero, 'From' => config('sms.expediteur'), 'Body' => $message,
                        ]);
                    break;
                case 'http':
                    $r = Http::withToken((string) config('sms.http.token'))->acceptJson()->timeout(20)
                        ->post((string) config('sms.http.url'), ['to' => $numero, 'from' => config('sms.expediteur'), 'message' => $message]);
                    break;
                default:
                    Log::info("SMS (test, non envoyé) à $numero : $message");

                    return [true, 'test', null];
            }

            return $r->successful() ? [true, 'envoye', null] : [false, 'echec', 'HTTP '.$r->status().' : '.mb_substr($r->body(), 0, 300)];
        } catch (\Throwable $e) {
            return [false, 'echec', mb_substr($e->getMessage(), 0, 300)];
        }
    }
}

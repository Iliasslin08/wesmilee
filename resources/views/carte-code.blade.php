@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); $slogan = !empty($cabinet['slogan'] ?? null) ? ($AR && $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan']) : null; @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Mon dossier', 'ملفي') }} · We Smile</title>
<style>
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #f3f9f9; color: #12303a; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
  .box { background: #fff; border-radius: 16px; padding: 28px 24px; width: min(360px, 92vw); text-align: center; box-shadow: 0 8px 30px rgba(11,95,107,.12); }
  img { height: 90px; } h1 { font-size: 20px; color: #0b5f6b; margin: 10px 0 4px; } p { color: #5b737a; font-size: 14px; }
  input { width: 100%; box-sizing: border-box; font-size: 28px; letter-spacing: 10px; text-align: center; padding: 10px; border: 2px solid #cfe2e5; border-radius: 10px; margin: 10px 0; }
  button { width: 100%; padding: 12px; font-size: 16px; font-weight: bold; color: #fff; background: #04a3b5; border: 0; border-radius: 10px; }
  .err { color: #b3261e; font-weight: 600; }
</style>
</head>
<body>
@include('partials.langue')
<form class="box" method="post" action="{{ route('carte.verifier', $token) }}">
  @csrf
  <img src="/images/logo-wesmile.png" alt="We Smile">
  <h1>{{ $t('Mon dossier dentaire', 'ملفي لطب الأسنان') }}</h1>
  <p>{!! $t('Pour votre sécurité, tapez les <b>4 derniers chiffres</b> de votre numéro de téléphone.', 'لحمايتكم، أدخلوا <b>آخر 4 أرقام</b> من رقم هاتفكم.') !!}</p>
  @if ($erreur)<p class="err">{{ $erreur }}</p>@endif
  <input dir="ltr" name="code" inputmode="numeric" maxlength="4" autocomplete="off" autofocus required>
  <button type="submit">{{ $t('Voir mon dossier', 'عرض ملفي') }}</button>
</form>
</body>
</html>

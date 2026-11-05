@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); $cabinet = $cabinet ?? config('cabinet'); @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Mon espace', 'فضائي') }} · {{ $cabinet['nom'] ?? 'We Smile' }}</title>
@include('partials.site-style')
<style>
  .zone { padding: 50px 0 80px; }
  .boite { max-width: 400px; margin: 0 auto; background: #fff; color: var(--t); border-radius: 22px; padding: 28px 24px; text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,.25); }
  .boite h1 { font-size: 22px; color: var(--f); margin: 6px 0; }
  .code { font-size: 32px !important; letter-spacing: 14px; text-align: center; padding: 12px !important; margin: 14px 0 4px; }
  .cadenas { width: 64px; height: 64px; border-radius: 18px; background: var(--clair); display: inline-flex; align-items: center; justify-content: center; font-size: 30px; }
</style>
</head>
<body>
@include('partials.site-nav', ['liens' => []])
<section class="bandeau zone">
  <div class="conteneur">
    <form class="boite" method="post" action="{{ route('carte.verifier', $token) }}">
      @csrf
      <div class="cadenas">🔒</div>
      <h1>{{ $t('Mon espace patient', 'فضاء المريض') }}</h1>
      <p class="muted">{!! $t('Pour votre sécurité, tapez les <b>4 derniers chiffres</b> de votre numéro de téléphone.', 'لحمايتكم، أدخلوا <b>آخر 4 أرقام</b> من رقم هاتفكم.') !!}</p>
      @if ($erreur)<div class="err">{{ $erreur }}</div>@endif
      <input class="code" dir="ltr" name="code" inputmode="numeric" maxlength="4" autocomplete="off" autofocus required>
      <button class="btn" type="submit">{{ $t('Ouvrir mon espace', 'فتح فضائي') }}</button>
    </form>
  </div>
</section>
@include('partials.site-pied')
</body>
</html>

@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); $slogan = !empty($cabinet['slogan'] ?? null) ? ($AR && $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan']) : null; @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Mon espace patient', 'فضائي') }} · We Smile</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #eef7f8; color: #12303a; }
  .haut { background: linear-gradient(160deg, #048ea7, #0b5f6b); color: #fff; padding: 28px 18px 70px; text-align: center; }
  .haut img { height: 64px; background: #fff; border-radius: 16px; padding: 6px 10px; }
  .haut h1 { margin: 12px 0 4px; font-size: 22px; } .haut p { margin: 0; opacity: .9; font-size: 14px; }
  .wrap { max-width: 460px; margin: -50px auto 0; padding: 0 16px 30px; }
  .carte { background: #fff; border-radius: 18px; padding: 18px; box-shadow: 0 10px 30px rgba(11,95,107,.12); margin-bottom: 14px; }
  .onglets { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: #eef7f8; border-radius: 12px; padding: 4px; margin-bottom: 14px; }
  .onglets button { border: 0; background: none; padding: 10px 6px; border-radius: 9px; font: 600 14px 'Poppins', Arial; color: #5b737a; cursor: pointer; }
  .onglets button.actif { background: #fff; color: #048ea7; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  label { display: block; font-size: 13px; font-weight: 600; color: #48616a; margin: 10px 0 4px; }
  input { width: 100%; font: 16px 'Poppins', Arial; padding: 11px 12px; border: 1.5px solid #cfe2e5; border-radius: 10px; }
  input:focus { outline: none; border-color: #048ea7; }
  .btn { width: 100%; margin-top: 16px; padding: 13px; font: 700 16px 'Poppins', Arial; color: #fff; background: #048ea7; border: 0; border-radius: 12px; cursor: pointer; }
  .aide { font-size: 12px; color: #6b8189; margin-top: 6px; }
  .err { background: #fdecea; color: #b3261e; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 10px; }
  .ar { direction: rtl; font-size: 13px; color: #5b737a; text-align: center; margin-top: 10px; }
  .pied { text-align: center; font-size: 12px; color: #6b8189; }
</style>
</head>
<body>
@include('partials.langue')
<div class="haut">
  <img src="/images/logo-wesmile.png" alt="We Smile">
  <h1>{{ $t('Mon espace patient', 'فضاء المريض') }}</h1>
  <p>{{ $t('Prenez rendez-vous en ligne', 'احجزوا موعدكم عبر الإنترنت') }}</p>
  @if ($slogan)<p style="margin-top:8px;display:inline-block;background:rgba(255,255,255,.18);border-radius:999px;padding:4px 12px;font-weight:600">🦷 {{ $slogan }}</p>@endif
</div>
<div class="wrap">
  <div class="carte">
    @php $nouveau = old('nom') !== null || $errors->has('nouveau') || $errors->has('nom') || $errors->has('prenom'); @endphp
    <div class="onglets">
      <button type="button" id="o1" class="{{ $nouveau ? '' : 'actif' }}" onclick="voir(1)">{{ $t('J’ai déjà un dossier', 'عندي ملف') }}</button>
      <button type="button" id="o2" class="{{ $nouveau ? 'actif' : '' }}" onclick="voir(2)">{{ $t('Première visite', 'أول زيارة') }}</button>
    </div>

    <form id="f1" method="post" action="{{ route('espace.connexion') }}" @if ($nouveau) hidden @endif>
      @csrf
      @if ($errors->has('telephone'))<div class="err">{{ $errors->first('telephone') }}</div>@endif
      <label>{{ $t('Téléphone', 'الهاتف') }}</label>
      <input name="telephone" type="tel" inputmode="tel" dir="ltr" value="{{ old('telephone') }}" placeholder="06 00 00 00 00" required>
      <label>{{ $t('Date de naissance ou n° de dossier', 'تاريخ الازدياد أو رقم الملف') }}</label>
      <input name="preuve" value="{{ old('preuve') }}" placeholder="{{ $t('jj/mm/aaaa ou n° de dossier', 'يوم/شهر/سنة أو رقم الملف') }}" required>
      <div class="aide">{{ $t('Le n° de dossier est écrit sur votre carte patient.', 'رقم الملف مكتوب على بطاقة المريض.') }}</div>
      <button class="btn">{{ $t('Entrer', 'دخول') }}</button>
    </form>

    <form id="f2" method="post" action="{{ route('espace.nouveau') }}" @if (! $nouveau) hidden @endif>
      @csrf
      @if ($errors->has('nouveau'))<div class="err">{{ $errors->first('nouveau') }}</div>@endif
      @foreach (['nom', 'prenom', 'telephone'] as $c)@if ($errors->has($c) && $c !== 'telephone')<div class="err">{{ $errors->first($c) }}</div>@endif @endforeach
      <label>{{ $t('Nom', 'الاسم العائلي') }}</label>
      <input name="nom" value="{{ old('nom') }}" required>
      <label>{{ $t('Prénom', 'الاسم الشخصي') }}</label>
      <input name="prenom" value="{{ old('prenom') }}" required>
      <label>{{ $t('Téléphone', 'الهاتف') }}</label>
      <input name="telephone" type="tel" inputmode="tel" dir="ltr" value="{{ old('nom') !== null ? old('telephone') : '' }}" placeholder="06 00 00 00 00" required>
      <div class="aide">{{ $t('Le jour du rendez-vous, vous compléterez votre fiche sur la tablette de l’accueil.', 'يوم الموعد، ستكملون بطاقتكم على اللوحة الإلكترونية في الاستقبال.') }}</div>
      <button class="btn">{{ $t('Continuer', 'متابعة') }}</button>
    </form>
  </div>
  <div class="pied">{{ $cabinet['nom'] ?? 'Wesmile Center' }} · {{ $cabinet['ville'] ?? 'Rabat' }}@if (!empty($cabinet['telephone'])) · <a href="tel:{{ preg_replace('/\s+/', '', $cabinet['telephone']) }}" style="color:inherit">{{ $cabinet['telephone'] }}</a>@endif
    @if (!empty($cabinet['adresse']))<br>{{ $cabinet['adresse'] }}@endif
    @if (!empty($cabinet['site']))<br><a href="https://{{ preg_replace('#^https?://#', '', $cabinet['site']) }}" style="color:#048ea7">{{ $cabinet['site'] }}</a>@endif</div>
</div>
<script>
  function voir(n) {
    document.getElementById('f1').hidden = n !== 1; document.getElementById('f2').hidden = n !== 2;
    document.getElementById('o1').className = n === 1 ? 'actif' : ''; document.getElementById('o2').className = n === 2 ? 'actif' : '';
  }
</script>
</body>
</html>

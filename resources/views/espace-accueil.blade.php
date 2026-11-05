@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Mon espace patient', 'فضاء المريض') }} · {{ $cabinet['nom'] ?? 'We Smile' }}</title>
@include('partials.site-style')
<style>
  .heros { padding: 56px 0 64px; }
  .heros .conteneur { display: grid; grid-template-columns: 1.1fr .9fr; gap: 40px; align-items: center; }
  .points { margin: 22px 0 0; display: grid; gap: 10px; }
  .points div { display: flex; gap: 10px; align-items: center; font-size: 15px; }
  .points span { width: 34px; height: 34px; border-radius: 10px; background: rgba(255,255,255,.15); display: inline-flex; align-items: center; justify-content: center; flex: none; }
  .connexion { background: #fff; color: var(--t); border-radius: 22px; padding: 24px; box-shadow: 0 24px 60px rgba(0,0,0,.25); }
  .connexion h3 { margin: 0 0 4px; font-size: 20px; color: var(--f); }
  .onglets { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: var(--fond); border-radius: 12px; padding: 4px; margin: 14px 0 6px; }
  .onglets button { border: 0; background: none; padding: 10px 6px; border-radius: 9px; font: 600 14px Poppins, Arial; color: #5b737a; cursor: pointer; }
  .onglets button.actif { background: #fff; color: var(--p); box-shadow: 0 2px 8px rgba(0,0,0,.08); }
  .aide { font-size: 12px; color: var(--m); margin-top: 6px; }
  .qr { margin-top: 14px; background: var(--clair); border-radius: 12px; padding: 10px 12px; font-size: 13px; color: var(--f); }
  .atouts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-top: -34px; position: relative; }
  .atouts .carte { margin: 0; } .atouts h2 { font-size: 15px; }
  @media (max-width: 900px) { .heros .conteneur { grid-template-columns: 1fr; } .atouts { grid-template-columns: 1fr 1fr; margin-top: 20px; } }
  @media (max-width: 520px) { .atouts { grid-template-columns: 1fr; } .heros { padding: 30px 0 40px; } }
</style>
</head>
<body>
@include('partials.site-nav', ['liens' => [['#connexion', $t('Se connecter', 'الدخول')], ['#services', $t('Services', 'الخدمات')]]])

<section class="bandeau heros">
  <div class="conteneur">
    <div>
      @if (!empty($cabinet['slogan']))<span class="badge">🦷 {{ $AR && $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan'] }}</span>@endif
      <h1 style="margin-top:14px">{{ $t('Votre sourire, suivi en ligne', 'ابتسامتكم، تتبعونها عبر الإنترنت') }}</h1>
      <p>{{ $t('Votre espace patient', 'فضاؤكم كمريض') }} {{ $cabinet['nom'] ?? 'Wesmile Center' }} : {{ $t('rendez-vous, soins, paiements et messages du centre, au même endroit.', 'المواعيد، العلاجات، المدفوعات ورسائل المركز في مكان واحد.') }}</p>
      <div class="points">
        <div><span>📅</span>{{ $t('Prenez rendez-vous sur les créneaux libres', 'احجزوا موعدا في الأوقات المتاحة') }}</div>
        <div><span>🦷</span>{{ $t('Suivez votre traitement et votre schéma dentaire', 'تابعوا علاجكم وخريطة أسنانكم') }}</div>
        <div><span>💳</span>{{ $t('Consultez vos paiements et votre mutuelle', 'اطلعوا على مدفوعاتكم وتأمينكم') }}</div>
        <div><span>💬</span>{{ $t('Recevez les rappels de contrôle et les messages du centre', 'توصلوا بتذكيرات المراقبة ورسائل المركز') }}</div>
      </div>
    </div>

    <div class="connexion" id="connexion">
      @php $nouveau = old('nom') !== null || $errors->has('nouveau') || $errors->has('nom') || $errors->has('prenom'); @endphp
      <h3>{{ $t('Mon espace patient', 'فضاء المريض') }}</h3>
      <div class="muted">{{ $t('Connectez-vous pour voir votre dossier.', 'سجلوا الدخول لرؤية ملفكم.') }}</div>
      <div class="onglets">
        <button type="button" id="o1" class="{{ $nouveau ? '' : 'actif' }}" onclick="voir(1)">{{ $t('J’ai déjà un dossier', 'عندي ملف') }}</button>
        <button type="button" id="o2" class="{{ $nouveau ? 'actif' : '' }}" onclick="voir(2)">{{ $t('Première visite', 'أول زيارة') }}</button>
      </div>

      <form id="f1" method="post" action="{{ route('espace.connexion') }}" @if ($nouveau) hidden @endif>
        @csrf
        @if ($errors->has('telephone'))<div class="err">{{ $errors->first('telephone') }}</div>@endif
        <label>{{ $t('Téléphone', 'الهاتف') }}</label>
        <input name="telephone" type="tel" inputmode="tel" dir="ltr" value="{{ old('telephone') }}" placeholder="06 00 00 00 00" required>
        <label>{{ $t('Mot de passe (ou date de naissance)', 'كلمة السر (أو تاريخ الازدياد)') }}</label>
        <input name="preuve" type="password" value="" placeholder="{{ $t('mot de passe donné à l’accueil', 'كلمة السر المسلمة في الاستقبال') }}" required>
        <div class="aide">{{ $t('Mot de passe : tout votre dossier. Date de naissance ou n° de dossier : prise de rendez-vous seulement.', 'كلمة السر: ملفكم كاملا. تاريخ الازدياد أو رقم الملف: حجز موعد فقط.') }}</div>
        <button class="btn">{{ $t('Entrer', 'دخول') }}</button>
        <div class="qr">📱 {{ $t('Vous avez votre carte patient ? Scannez son QR code avec votre téléphone : c’est plus rapide.', 'لديكم بطاقة المريض؟ امسحوا رمز QR بهاتفكم: إنه أسرع.') }}</div>
      </form>

      <form id="f2" method="post" action="{{ route('espace.nouveau') }}" @if (! $nouveau) hidden @endif>
        @csrf
        @if ($errors->has('nouveau'))<div class="err">{{ $errors->first('nouveau') }}</div>@endif
        @foreach (['nom', 'prenom'] as $c)@if ($errors->has($c))<div class="err">{{ $errors->first($c) }}</div>@endif @endforeach
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
  </div>
</section>

<section class="conteneur" id="services">
  <div class="atouts">
    <div class="carte"><h2><span class="ic">📅</span>{{ $t('Rendez-vous en ligne', 'المواعيد عبر الإنترنت') }}</h2><div class="muted">{{ $t('Consultation ou contrôle, sur les créneaux encore libres.', 'استشارة أو مراقبة، في الأوقات المتاحة.') }}</div></div>
    <div class="carte"><h2><span class="ic">🦷</span>{{ $t('Mon traitement', 'علاجي') }}</h2><div class="muted">{{ $t('Soins faits et à venir, schéma dentaire, suivi du laboratoire.', 'العلاجات المنجزة والقادمة، خريطة الأسنان، تتبع المختبر.') }}</div></div>
    <div class="carte"><h2><span class="ic">💳</span>{{ $t('Paiements & mutuelle', 'المدفوعات والتأمين') }}</h2><div class="muted">{{ $t('Total, payé, reste, reçus et feuilles de soins.', 'المجموع، المدفوع، الباقي، الوصولات وأوراق العلاج.') }}</div></div>
    <div class="carte"><h2><span class="ic">💬</span>{{ $t('Messages', 'الرسائل') }}</h2><div class="muted">{{ $t('Rappels de contrôle, implants, prothèse prête…', 'تذكيرات المراقبة، الزرعات، التركيبة جاهزة…') }}</div></div>
  </div>
</section>

@include('partials.site-pied')
<script>
  function voir(n) {
    document.getElementById('f1').hidden = n !== 1; document.getElementById('f2').hidden = n !== 2;
    document.getElementById('o1').className = n === 1 ? 'actif' : ''; document.getElementById('o2').className = n === 2 ? 'actif' : '';
  }
</script>
</body>
</html>

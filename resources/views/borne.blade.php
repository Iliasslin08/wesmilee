@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title>{{ $t('Borne d’accueil', 'محطة الاستقبال') }}</title>
@include('partials.site-style')
<style>
  html, body { height: 100%; }
  body { background: linear-gradient(160deg, #04748a, #0b3b44); color: #fff; user-select: none; -webkit-user-select: none; overflow: hidden; }
  .ecran { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; text-align: center; gap: 18px; }
  .logo { background: #fff; border-radius: 18px; padding: 8px 16px; } .logo img { height: 56px; display: block; }
  h1 { font-size: clamp(26px, 4.5vw, 44px); margin: 0; }
  .sous { font-size: clamp(16px, 2.2vw, 22px); opacity: .9; margin: 0; }
  .ecran-saisie { width: min(520px, 92vw); background: #fff; color: var(--t); border-radius: 26px; padding: 22px; box-shadow: 0 30px 70px rgba(0,0,0,.35); }
  .affiche { font-size: 44px; font-weight: 800; letter-spacing: 6px; min-height: 72px; border: 2.5px solid #cfe2e5; border-radius: 16px; display: flex; align-items: center; justify-content: center; direction: ltr; color: var(--f); }
  .pave { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 16px; direction: ltr; }
  .pave button { font: 800 32px Poppins, Arial; padding: 18px 0; border-radius: 16px; border: 0; background: var(--fond); color: var(--f); cursor: pointer; }
  .pave button:active { background: var(--clair); transform: scale(.97); }
  .pave .eff { font-size: 20px; background: #fdecea; color: #b3261e; }
  .valider { margin-top: 16px; width: 100%; padding: 20px; font: 800 24px Poppins, Arial; color: #fff; background: #16a34a; border: 0; border-radius: 16px; cursor: pointer; }
  .valider:disabled { opacity: .4; }
  .message { width: min(620px, 92vw); background: #fff; color: var(--t); border-radius: 26px; padding: 30px; box-shadow: 0 30px 70px rgba(0,0,0,.35); }
  .message h2 { font-size: 28px; justify-content: center; margin-bottom: 8px; }
  .retour { display: inline-block; margin-top: 18px; padding: 16px 30px; font: 700 20px Poppins, Arial; background: var(--p); color: #fff; border-radius: 14px; text-decoration: none; }
  .choix { display: grid; gap: 12px; margin-top: 16px; }
  .choix button { padding: 18px; font: 700 22px Poppins, Arial; border-radius: 14px; border: 2px solid #cfe2e5; background: #fff; color: var(--f); cursor: pointer; }
  .ticket { width: min(420px, 92vw); background: #fff; color: #111; border-radius: 22px; padding: 26px 24px; box-shadow: 0 30px 70px rgba(0,0,0,.35); }
  .ticket .num { font-size: 96px; font-weight: 800; line-height: 1; color: var(--f); margin: 6px 0; }
  .ticket .lbl { font-size: 14px; text-transform: uppercase; letter-spacing: .1em; color: #555; font-weight: 700; }
  .ticket .ligne { border-top: 2px dashed #ccc; margin: 14px 0; }
  .ticket .info { font-size: 17px; line-height: 1.6; }
  .ticket .alerte { background: #fff3cd; border-radius: 10px; padding: 10px; font-weight: 700; margin-top: 10px; }
  .langue-b { position: fixed; top: 16px; inset-inline-end: 16px; background: rgba(255,255,255,.95); color: var(--f); border-radius: 999px; padding: 10px 20px; font: 700 18px Poppins, Arial; text-decoration: none; }
  .pas-imprimer { }
  .premiere-b { display: inline-block; margin-top: 4px; padding: 16px 28px; font: 700 20px Poppins, Arial; background: rgba(255,255,255,.14); color: #fff; border: 2px solid rgba(255,255,255,.6); border-radius: 16px; text-decoration: none; }
  .champs { display: grid; gap: 12px; text-align: start; margin-top: 14px; }
  .champs label { font-weight: 700; font-size: 17px; }
  .champs input { width: 100%; font: 600 24px Poppins, Arial; padding: 14px 16px; border: 2.5px solid #cfe2e5; border-radius: 14px; color: var(--f); }
  .champs input:focus { outline: none; border-color: var(--p); }
  .err-b { background: #fdecea; color: #b3261e; border-radius: 12px; padding: 10px 12px; font-weight: 700; }
  @media print {
    @page { size: 80mm auto; margin: 3mm; }
    body { background: #fff !important; color: #000; overflow: visible; }
    .pas-imprimer, .langue-b, .logo { display: none !important; }
    .ecran { height: auto; padding: 0; display: block; }
    .ticket { width: 100%; box-shadow: none; border-radius: 0; padding: 0; }
    .ticket .num { font-size: 64px; color: #000; }
  }
</style>
</head>
<body>
<a class="langue-b" href="{{ route('langue', $AR ? 'fr' : 'ar') }}">{{ $AR ? 'Français' : 'العربية' }}</a>
<div class="ecran">
  <div class="logo pas-imprimer"><img src="/images/logo-wesmile.png" alt="We Smile"></div>

  @if ($etape === 'saisie')
    <h1>{{ $t('Bienvenue', 'مرحبا بكم') }}</h1>
    <p class="sous">{{ $t('Tapez votre n° de dossier ou votre n° de téléphone', 'أدخلوا رقم ملفكم أو رقم هاتفكم') }}</p>
    <form class="ecran-saisie" method="post" action="{{ route('borne.arrivee') }}" id="f">
      @csrf
      <input type="hidden" name="numero" id="numero">
      <div class="affiche" id="affiche">&nbsp;</div>
      <div class="pave">
        @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)<button type="button" data-n="{{ $n }}">{{ $n }}</button>@endforeach
        <button type="button" class="eff" data-n="eff">⌫</button>
        <button type="button" data-n="0">0</button>
        <button type="button" class="eff" data-n="tout">C</button>
      </div>
      <button class="valider" id="ok" disabled>{{ $t('Valider', 'تأكيد') }} ✓</button>
    </form>
    <a class="premiere-b" href="{{ route('borne.premiere') }}">🆕 {{ $t('Première visite ? Appuyez ici', 'أول زيارة؟ اضغطوا هنا') }}</a>
    <script>
      let v = '';
      const aff = document.getElementById('affiche'), ok = document.getElementById('ok');
      const maj = () => { aff.innerHTML = v || '&nbsp;'; ok.disabled = v.length < 1; document.getElementById('numero').value = v; };
      document.querySelectorAll('.pave button').forEach((b) => b.onclick = () => {
        const n = b.dataset.n;
        if (n === 'eff') v = v.slice(0, -1); else if (n === 'tout') v = ''; else if (v.length < 12) v += n;
        maj();
      });
      document.getElementById('f').onsubmit = () => { ok.disabled = true; };
    </script>

  @elseif ($etape === 'premiere')
    <h1>{{ $t('Première visite', 'أول زيارة') }}</h1>
    <p class="sous">{{ $t('Écrivez votre nom, prénom et téléphone', 'اكتبوا اسمكم العائلي والشخصي ورقم هاتفكم') }}</p>
    <form class="ecran-saisie" method="post" action="{{ route('borne.premiere.enregistrer') }}" onsubmit="this.querySelector('.valider').disabled = true">
      @csrf
      @if ($errors->any())<div class="err-b">{{ $t('Vérifiez les informations (téléphone : 10 chiffres).', 'تحققوا من المعلومات (الهاتف: 10 أرقام).') }}</div>@endif
      <div class="champs">
        <label>{{ $t('Nom', 'الاسم العائلي') }}</label>
        <input name="nom" value="{{ old('nom') }}" autocomplete="off" required autofocus>
        <label>{{ $t('Prénom', 'الاسم الشخصي') }}</label>
        <input name="prenom" value="{{ old('prenom') }}" autocomplete="off" required>
        <label>{{ $t('Téléphone', 'الهاتف') }}</label>
        <input name="telephone" type="tel" inputmode="numeric" dir="ltr" value="{{ old('telephone') }}" placeholder="06 00 00 00 00" autocomplete="off" required>
      </div>
      <button class="valider">{{ $t('Prendre mon ticket', 'أخذ التذكرة') }} ✓</button>
    </form>
    <a class="retour" href="{{ route('borne') }}" style="background:#94a3b8">{{ $t('Annuler', 'إلغاء') }}</a>
    <script>setTimeout(() => { location.href = '{{ route('borne') }}'; }, 120000);</script>

  @elseif ($etape === 'choix')
    <div class="message">
      <h2>{{ $t('Qui êtes-vous ?', 'من أنتم؟') }}</h2>
      <div class="muted" style="font-size:16px">{{ $t('Plusieurs rendez-vous aujourd’hui pour ce numéro.', 'عدة مواعيد اليوم لهذا الرقم.') }}</div>
      <form class="choix" method="post" action="{{ route('borne.arrivee') }}">
        @csrf
        <input type="hidden" name="numero" value="{{ $numero }}">
        @foreach ($rdvs as $r)
          <button name="rdv_id" value="{{ $r->id }}">{{ $r->patient->prenom }} {{ mb_substr($r->patient->nom, 0, 1) }}. · {{ $r->debut->format('H:i') }}</button>
        @endforeach
      </form>
      <a class="retour" href="{{ route('borne') }}" style="background:#94a3b8">{{ $t('Annuler', 'إلغاء') }}</a>
    </div>

  @elseif ($etape === 'ticket')
    <div class="ticket">
      <div class="lbl">{{ $cabinet['nom'] ?? 'Wesmile Center' }}</div>
      <div style="font-size:18px;margin-top:4px">{{ $t('Bonjour', 'مرحبا') }} <b>{{ $rdv->patient->prenom }} {{ mb_substr($rdv->patient->nom, 0, 1) }}.</b></div>
      <div class="ligne"></div>
      <div class="lbl">{{ $t('Votre numéro', 'رقمكم') }}</div>
      <div class="num">{{ $rdv->numero_appel }}</div>
      <div class="ligne"></div>
      <div class="info">
        {{ $t('Rendez-vous', 'الموعد') }} : <b>{{ $rdv->debut->format('H:i') }}</b><br>
        @if ($rdv->docteur){{ $rdv->docteur->name }}@if ($rdv->docteur->salle) · {{ $rdv->docteur->salle }}@endif<br>@else{{ $t('Consultation : l’accueil vous oriente vers un docteur.', 'استشارة: الاستقبال سيوجهكم إلى طبيب.') }}<br>@endif
        @if ($avant > 0){{ $t('Patients avant vous', 'المرضى قبلكم') }} : <b>{{ $avant }}</b><br>@endif
        {{ $t('Installez-vous en salle d’attente. Votre numéro s’affichera sur l’écran.', 'تفضلوا إلى قاعة الانتظار. سيظهر رقمكم على الشاشة.') }}
      </div>
      @if ($rdv->patient->fiche_a_completer || ! $rdv->patient->consentement_le)
        <div class="alerte">⚠ {{ $t('Passez d’abord à l’accueil pour compléter votre fiche.', 'مروا أولا إلى الاستقبال لإكمال بطاقتكم.') }}</div>
      @endif
      <div class="ligne"></div>
      <div style="font-size:12px;color:#666">{{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <a class="retour pas-imprimer" href="{{ route('borne') }}">{{ $t('Terminé', 'تم') }}</a>
    <script>
      window.addEventListener('load', () => setTimeout(() => window.print(), 300));
      setTimeout(() => { location.href = '{{ route('borne') }}'; }, 15000);
    </script>

  @else
    <div class="message">
      @if ($etape === 'inconnu')
        <h2>🔍 {{ $t('Numéro introuvable', 'الرقم غير موجود') }}</h2>
        <div style="font-size:18px">{{ $t('Vérifiez votre n° de dossier ou de téléphone, ou présentez-vous à l’accueil.', 'تحققوا من رقم الملف أو الهاتف، أو توجهوا إلى الاستقبال.') }}</div>
        <a class="retour" href="{{ route('borne.premiere') }}" style="background:#16a34a">🆕 {{ $t('C’est ma première visite', 'هذه أول زيارة لي') }}</a>
      @else
        <h2>📅 {{ $t('Pas de rendez-vous aujourd’hui', 'لا يوجد موعد اليوم') }}</h2>
        @if (! empty($patients))
          <div style="font-size:18px">{{ $t('Vous venez pour une consultation ?', 'هل جئتم من أجل استشارة؟') }}</div>
          <form class="choix" method="post" action="{{ route('borne.consultation') }}" onsubmit="this.querySelectorAll('button').forEach(b => b.disabled = true)">
            @csrf
            <input type="hidden" name="numero" value="{{ $numero }}">
            @foreach ($patients as $p)
              <button name="patient_id" value="{{ $p->id }}" style="background:#16a34a;color:#fff;border-color:#16a34a">🦷 {{ $t('Consultation', 'استشارة') }} · {{ $p->prenom }} {{ mb_substr($p->nom, 0, 1) }}.</button>
            @endforeach
          </form>
        @else
          <div style="font-size:18px">{{ $t('Présentez-vous à l’accueil, nous allons vous aider.', 'توجهوا إلى الاستقبال، سنساعدكم.') }}</div>
        @endif
      @endif
      <a class="retour" href="{{ route('borne') }}">{{ $t('Recommencer', 'إعادة') }}</a>
    </div>
    <script>setTimeout(() => { location.href = '{{ route('borne') }}'; }, 20000);</script>
  @endif
</div>
</body>
</html>

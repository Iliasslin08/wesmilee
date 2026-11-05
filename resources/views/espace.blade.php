@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); @endphp
@php
    $dh = fn ($n) => number_format((float) $n, 0, ',', ' ').' DH';
    $methodes = ['especes' => 'Espèces', 'carte' => 'Carte', 'cheque' => 'Chèque', 'virement' => 'Virement'];
    $nouveauxMsg = $complet ? $messages->whereNull('lu_le')->count() : 0;
    $liens = [['#rdv', $t('Rendez-vous', 'المواعيد')]];
    if ($complet) {
        $liens = array_merge($liens, [['#messages', $t('Messages', 'الرسائل')], ['#traitement', $t('Traitement', 'العلاج')], ['#paiements', $t('Paiements', 'المدفوعات')], ['#mutuelle', $t('Mutuelle', 'التأمين')]]);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $t('Mon espace', 'فضائي') }} · {{ $patient->nom_complet }}</title>
@include('partials.site-style')
<style>
  .accueil { padding: 34px 0 70px; }
  .accueil .conteneur { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; }
  .ph { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; background: rgba(255,255,255,.2); border: 3px solid rgba(255,255,255,.5); flex: none; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 28px; }
  .accueil h1 { margin: 0; font-size: clamp(22px, 3vw, 32px); }
  .chiffres { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: -46px; position: relative; }
  .chiffres .carte { margin: 0; padding: 16px 18px; } .chiffres small { color: var(--m); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; }
  .chiffres b { display: block; font-size: 19px; color: var(--f); margin-top: 2px; } .chiffres .rouge { color: #b3261e; } .chiffres .vert { color: #3d7a12; }
  .colonnes { columns: 2 440px; column-gap: 20px; margin-top: 22px; }
  .rdv { background: linear-gradient(135deg, #048ea7, #0b5f6b); color: #fff; border: 0; }
  .rdv h2 { color: #fff; } .rdv h2 .ic { background: rgba(255,255,255,.18); } .rdv .date { font-size: 22px; font-weight: 700; } .rdv .date::first-letter { text-transform: uppercase; }
  .puces { display: flex; gap: 8px; flex-wrap: wrap; }
  .puce { border: 1.5px solid #cfe2e5; background: #fff; border-radius: 999px; padding: 8px 16px; font: 600 14px Poppins, Arial; color: var(--t); cursor: pointer; }
  .puce.actif { background: var(--p); border-color: var(--p); color: #fff; }
  .grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(72px, 1fr)); gap: 8px; }
  .grille .vide { grid-column: 1 / -1; }
  .grille .lien { background: none; border: 0; color: var(--p); font: 600 14px Poppins, Arial; text-decoration: underline; cursor: pointer; padding: 0; margin-top: 6px; }
  .h { border: 1.5px solid #cfe2e5; background: #fff; border-radius: 10px; padding: 9px 0; font: 600 14px Poppins, Arial; cursor: pointer; color: var(--t); }
  .h.actif { background: var(--p); border-color: var(--p); color: #fff; }
  .vide { color: var(--m); font-size: 14px; padding: 6px 0; }
  @media (max-width: 700px) { .chiffres { grid-template-columns: 1fr; } .accueil { padding: 24px 0 64px; } }
</style>
</head>
<body>
@include('partials.site-nav', ['liens' => $liens, 'quitter' => true])

<section class="bandeau accueil">
  <div class="conteneur">
    @if ($photo)<img class="ph" src="{{ route('espace.photo') }}" alt="">@else<div class="ph">{{ mb_substr($patient->prenom ?? $patient->nom, 0, 1) }}</div>@endif
    <div>
      <h1>{{ $t('Bonjour', 'مرحبا') }} {{ $patient->prenom }}</h1>
      <p>{{ $t('Dossier n°', 'ملف رقم') }} {{ $dossier?->numero }} · {{ $cabinet['nom'] ?? 'Wesmile Center' }}</p>
    </div>
  </div>
</section>

<main class="conteneur">
  <div class="chiffres">
    <div class="carte"><small>{{ $t('Prochain rendez-vous', 'الموعد القادم') }}</small><b>{{ $prochain ? $prochain->debut->format('d/m/Y H:i') : $t('Aucun', 'لا يوجد') }}</b></div>
    @if ($complet)
      <div class="carte"><small>{{ $t('Reste à payer', 'الباقي للأداء') }}</small><b class="{{ $reste > 0 ? 'rouge' : 'vert' }}">{{ $reste > 0 ? $dh($reste) : $t('Soldé', 'مؤدى بالكامل') }}</b></div>
      <div class="carte"><small>{{ $t('Messages', 'الرسائل') }}</small><b>@if ($nouveauxMsg) <a href="#messages" style="text-decoration:none">{{ $nouveauxMsg }} {{ $t('nouveau(x)', 'جديدة') }}</a> @else {{ $t('Rien de nouveau', 'لا جديد') }} @endif</b></div>
    @else
      <div class="carte"><small>{{ $t('Dossier complet', 'الملف الكامل') }}</small><b style="font-size:15px">{{ $t('Avec le mot de passe ou la carte QR', 'بكلمة السر أو بطاقة QR') }}</b></div>
      <div class="carte"><small>{{ $t('Besoin d’aide ?', 'تحتاجون مساعدة؟') }}</small><b style="font-size:15px">{{ $cabinet['telephone'] ?? '' }}</b></div>
    @endif
  </div>

  @if (session('ok'))<div class="ok" style="margin-top:20px">✓ {{ session('ok') }}</div>@endif

  <div class="colonnes">
  @if ($prochain)
    <div class="carte rdv" id="rdv">
      <h2><span class="ic">📅</span>{{ $t('Mon prochain rendez-vous', 'موعدي القادم') }}</h2>
      <div class="date">{{ \App\Support\Langue::date($prochain->debut) }} {{ $t('à', 'على الساعة') }} {{ $prochain->debut->format('H:i') }}</div>
      <div>{{ \App\Support\Langue::mot($prochain->mission) }} · {{ $prochain->docteur?->name }}</div>
      <div style="font-size:13px;opacity:.85;margin-top:8px">{{ $t('Pour changer ou annuler, appelez le centre', 'للتغيير أو الإلغاء، اتصلوا بالمركز') }}{{ !empty($cabinet['telephone']) ? ' '.$t('au', 'على الرقم').' '.$cabinet['telephone'] : '' }}.</div>
    </div>
  @else
    <div class="carte" id="rdv">
      <h2><span class="ic">📅</span>{{ $t('Prendre un rendez-vous', 'حجز موعد') }}</h2>
      @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
      <form method="post" action="{{ route('espace.reserver') }}" id="f">
        @csrf
        <input type="hidden" name="motif" id="motif" value="{{ array_key_first($motifs) }}">
        <input type="hidden" name="heure" id="heure">
        <div class="puces">
          @foreach ($motifs as $k => $m)
            <button type="button" class="puce {{ $loop->first ? 'actif' : '' }}" data-motif="{{ $k }}">{{ \App\Support\Langue::mot($m) }}</button>
          @endforeach
        </div>
        <label>{{ $t('Jour', 'اليوم') }}</label>
        <input type="date" name="date" id="date" min="{{ $jourMin }}" max="{{ $jourMax }}" value="{{ old('date', $jourMin) }}">
        <label>{{ $t('Heures disponibles', 'الساعات المتاحة') }}</label>
        <div class="grille" id="grille" dir="ltr"><div class="vide">{{ $t('Chargement…', 'جارٍ التحميل…') }}</div></div>
        <button class="btn" id="ok" disabled>{{ $t('Réserver ce rendez-vous', 'احجز هذا الموعد') }}</button>
      </form>
    </div>
  @endif


  @if ($complet)
    @include('partials.espace-dossier')
  @else
    <div class="carte">
      <h2><span class="ic">🔒</span>{{ $t('Mon dossier complet', 'ملفي الكامل') }}</h2>
      <div class="muted">{{ $t('Pour voir vos soins, votre schéma dentaire, vos paiements, votre mutuelle et vos messages : scannez le QR code de votre carte patient, ou demandez votre mot de passe à l’accueil.', 'لرؤية علاجاتكم وخريطة أسنانكم ومدفوعاتكم وتأمينكم ورسائلكم: امسحوا رمز QR لبطاقتكم، أو اطلبوا كلمة السر من الاستقبال.') }}</div>
    </div>
  @endif
  </div>
</main>

@include('partials.site-pied')
@if (! $prochain)
<script>
  const grille = document.getElementById('grille'), ok = document.getElementById('ok'), heure = document.getElementById('heure');
  const motif = document.getElementById('motif'), date = document.getElementById('date');
  let premier = true; // au chargement : si aujourd'hui est plein, on va directement au prochain jour libre
  async function charger() {
    heure.value = ''; ok.disabled = true; grille.innerHTML = '<div class="vide">{{ $t('Chargement…', 'جارٍ التحميل…') }}</div>';
    try {
      const r = await fetch(`{{ route('espace.creneaux') }}?motif=${motif.value}&date=${date.value}`, { headers: { Accept: 'application/json' } });
      const rep = r.ok ? await r.json() : { heures: [], prochain: null };
      const h = rep.heures ?? [];
      if (!h.length && rep.prochain && premier) { premier = false; date.value = rep.prochain; return charger(); }
      premier = false;
      grille.innerHTML = h.length ? '' : '<div class="vide">{{ $t('Pas de place ce jour-là (centre fermé ou complet).', 'لا توجد أماكن في هذا اليوم (المركز مغلق أو ممتلئ).') }}</div>';
      if (!h.length && rep.prochain) {
        const l = document.createElement('button'); l.type = 'button'; l.className = 'lien';
        l.textContent = '{{ $t('Voir le prochain jour libre :', 'أقرب يوم متاح:') }} ' + rep.prochain.split('-').reverse().join('/');
        l.onclick = () => { date.value = rep.prochain; charger(); };
        grille.firstChild.appendChild(document.createElement('br')); grille.firstChild.appendChild(l);
      }
      h.forEach((t) => {
        const b = document.createElement('button'); b.type = 'button'; b.className = 'h'; b.textContent = t;
        b.onclick = () => { grille.querySelectorAll('.h').forEach((x) => x.classList.remove('actif')); b.classList.add('actif'); heure.value = t; ok.disabled = false; };
        grille.appendChild(b);
      });
    } catch { grille.innerHTML = '<div class="vide">{{ $t('Connexion impossible. Réessayez.', 'تعذر الاتصال. أعيدوا المحاولة.') }}</div>'; }
  }
  document.querySelectorAll('.puce').forEach((p) => p.onclick = () => {
    document.querySelectorAll('.puce').forEach((x) => x.classList.remove('actif')); p.classList.add('actif'); motif.value = p.dataset.motif; charger();
  });
  date.onchange = charger; charger();
</script>
@endif
</body>
</html>

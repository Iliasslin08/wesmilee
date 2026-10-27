@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); $slogan = !empty($cabinet['slogan'] ?? null) ? ($AR && $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan']) : null; @endphp
@php
    $dh = fn ($n) => number_format((float) $n, 0, ',', ' ').' DH';
    $methodes = ['especes' => 'Espèces', 'carte' => 'Carte', 'cheque' => 'Chèque', 'virement' => 'Virement'];
@endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Mon espace · {{ $patient->nom_complet }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap">
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #eef7f8; color: #12303a; font-size: 15px; }
  .haut { background: linear-gradient(160deg, #048ea7, #0b5f6b); color: #fff; padding: 18px 16px 64px; }
  .haut .l { display: flex; align-items: center; gap: 12px; max-width: 560px; margin: 0 auto; }
  .ph { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; background: rgba(255,255,255,.25); flex: none; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 20px; }
  .haut h1 { margin: 0; font-size: 18px; } .haut p { margin: 0; font-size: 13px; opacity: .85; }
  .haut a { margin-inline-start: auto; color: #fff; font-size: 13px; opacity: .85; }
  .wrap { max-width: 560px; margin: -48px auto 0; padding: 0 14px 28px; }
  .carte { background: #fff; border-radius: 18px; padding: 16px; box-shadow: 0 10px 30px rgba(11,95,107,.10); margin-bottom: 12px; }
  h2 { font-size: 15px; margin: 0 0 10px; color: #0b5f6b; }
  .ok { background: #e3f4d6; color: #2c5f0e; border-radius: 12px; padding: 12px; font-weight: 600; margin-bottom: 12px; }
  .err { background: #fdecea; color: #b3261e; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 10px; }
  .rdv { background: linear-gradient(135deg, #048ea7, #0b6f8a); color: #fff; }
  .rdv h2 { color: #c8f1f5; } .rdv .date { font-size: 22px; font-weight: 700; } .rdv .date::first-letter { text-transform: uppercase; }
  .puces { display: flex; gap: 8px; flex-wrap: wrap; }
  .puce { border: 1.5px solid #cfe2e5; background: #fff; border-radius: 999px; padding: 8px 16px; font: 600 14px 'Poppins', Arial; color: #12303a; cursor: pointer; }
  .puce.actif { background: #048ea7; border-color: #048ea7; color: #fff; }
  label { display: block; font-size: 13px; font-weight: 600; color: #48616a; margin: 12px 0 6px; }
  input[type=date] { width: 100%; font: 16px 'Poppins', Arial; padding: 10px 12px; border: 1.5px solid #cfe2e5; border-radius: 10px; }
  .grille { display: grid; grid-template-columns: repeat(auto-fill, minmax(72px, 1fr)); gap: 8px; }
  .h { border: 1.5px solid #cfe2e5; background: #fff; border-radius: 10px; padding: 9px 0; font: 600 14px 'Poppins', Arial; cursor: pointer; color: #12303a; }
  .h.actif { background: #048ea7; border-color: #048ea7; color: #fff; }
  .vide { color: #6b8189; font-size: 14px; padding: 6px 0; }
  .btn { width: 100%; margin-top: 14px; padding: 13px; font: 700 16px 'Poppins', Arial; color: #fff; background: #048ea7; border: 0; border-radius: 12px; cursor: pointer; }
  .btn:disabled { opacity: .45; }
  ul { list-style: none; padding: 0; margin: 0; } li { padding: 9px 0; border-bottom: 1px solid #e8f1f2; } li:last-child { border: 0; }
  .muted { color: #6b8189; font-size: 13px; }
  .pill { float: inline-end; border-radius: 20px; padding: 1px 10px; font-size: 12px; font-weight: 600; }
  .p-ok { background: #e3f4d6; color: #3d7a12; } .p-cours { background: #dff4f6; color: #037786; } .p-att { background: #fff1c9; color: #7a5a00; }
  .totaux { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
  .totaux div { background: #f3f9f9; border-radius: 10px; padding: 8px 4px; font-size: 13px; } .totaux b { display: block; font-size: 16px; }
  .reste b { color: #b3261e; } .solde b { color: #3d7a12; }
  details summary { cursor: pointer; font-weight: 600; color: #0b5f6b; }
  .pied { text-align: center; color: #6b8189; font-size: 12px; margin-top: 8px; }
</style>
</head>
<body>
@include('partials.langue')
<div class="haut">
  <div class="l">
    @if ($photo)<img class="ph" src="{{ route('espace.photo') }}" alt="">@else<div class="ph">{{ mb_substr($patient->prenom ?? $patient->nom, 0, 1) }}</div>@endif
    <div>
      <h1>{{ $t('Bonjour', 'مرحبا') }} {{ $patient->prenom }}</h1>
      <p>{{ $t('Dossier n°', 'ملف رقم') }} {{ $dossier?->numero }}</p>
    </div>
    <form method="post" action="{{ route('espace.deconnexion') }}" style="margin-inline-start:auto;margin-top:34px">@csrf<button style="background:none;border:0;color:#fff;font:13px Poppins,Arial;opacity:.85;cursor:pointer">{{ $t('Quitter', 'خروج') }}</button></form>
  </div>
</div>
<div class="wrap">
  @if (session('ok'))<div class="ok">✓ {{ session('ok') }}</div>@endif

  @if ($prochain)
    <div class="carte rdv">
      <h2>{{ $t('Mon prochain rendez-vous', 'موعدي القادم') }}</h2>
      <div class="date">{{ \App\Support\Langue::date($prochain->debut) }} {{ $t('à', 'على الساعة') }} {{ $prochain->debut->format('H:i') }}</div>
      <div>{{ \App\Support\Langue::mot($prochain->mission) }} · {{ $prochain->docteur?->name }}</div>
      <div style="font-size:13px;opacity:.85;margin-top:8px">{{ $t('Pour changer ou annuler, appelez le centre', 'للتغيير أو الإلغاء، اتصلوا بالمركز') }}{{ !empty($cabinet['telephone']) ? ' '.$t('au', 'على الرقم').' '.$cabinet['telephone'] : '' }}.</div>
    </div>
  @else
    <div class="carte">
      <h2>{{ $t('Prendre un rendez-vous', 'حجز موعد') }}</h2>
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

  @if ($actes->isNotEmpty())
  <div class="carte">
    <h2>{{ $t('Mon traitement', 'علاجي') }}</h2>
    <ul>
      @foreach ($actes as $a)
        <li>{{ $a->libelle }}@if ($a->dents) <span class="muted">· {{ $t('dent', 'السن') }} {{ $a->dents }}</span>@endif
          <span class="pill {{ $a->statut->value === 'fait' ? 'p-ok' : ($a->statut->value === 'en_cours' ? 'p-cours' : 'p-att') }}">{{ \App\Support\Langue::mot($a->statut->label()) }}</span></li>
      @endforeach
    </ul>
  </div>
  @endif

  @if ($labo->isNotEmpty())
  <div class="carte">
    <h2>{{ $t('Mes travaux au laboratoire', 'أعمالي في المختبر') }}</h2>
    <ul>
      @foreach ($labo as $c)
        <li>{{ $c->travail }}@if ($c->dents) <span class="muted">· {{ $c->dents }}</span>@endif
          <span class="pill {{ in_array($c->statut->value, ['pret', 'livre', 'pose']) ? 'p-ok' : 'p-cours' }}">{{ \App\Support\Langue::mot($c->statut->label()) }}</span></li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="carte">
    <h2>{{ $t('Mes paiements', 'مدفوعاتي') }}</h2>
    <div class="totaux">
      <div>{{ $t('Total', 'المجموع') }}<b>{{ $dh($total) }}</b></div>
      <div>{{ $t('Payé', 'المدفوع') }}<b>{{ $dh($paye) }}</b></div>
      <div class="{{ $reste > 0 ? 'reste' : 'solde' }}">{{ $t('Reste', 'الباقي') }}<b>{{ $reste > 0 ? $dh($reste) : $t('Soldé', 'مؤدى بالكامل') }}</b></div>
    </div>
    @if ($paiements->isNotEmpty())
      <details style="margin-top:10px"><summary>{{ $t('Détail des paiements', 'تفاصيل المدفوعات') }}</summary>
        <ul>@foreach ($paiements as $p)<li>{{ $p->date->format('d/m/Y') }} · <b>{{ $dh($p->montant) }}</b> · {{ \App\Support\Langue::mot($methodes[$p->methode] ?? $p->methode) }}<span class="muted" style="float:inline-end">{{ $t('Reçu n°', 'وصل رقم') }} {{ $p->numero_recu }}</span></li>@endforeach</ul>
      </details>
    @endif
  </div>

  @if ($seances->isNotEmpty())
  <div class="carte">
    <details><summary>{{ $t('Mes séances', 'حصصي') }} ({{ $seances->count() }})</summary>
      <ul>@foreach ($seances as $s)<li><b>{{ $s->debut->format('d/m/Y') }}</b> · {{ $s->docteur?->name }}@if ($s->actes->isNotEmpty())<div class="muted">{{ $s->actes->map(fn ($a) => $a->libelle.($a->dents ? ' ('.$a->dents.')' : ''))->implode(', ') }}</div>@endif</li>@endforeach</ul>
    </details>
  </div>
  @endif

  @include('partials.avis', ['classe' => 'carte'])

  <div class="pied">{{ $cabinet['nom'] ?? 'Wesmile Center' }} · {{ $cabinet['ville'] ?? 'Rabat' }}@if (!empty($cabinet['telephone'])) · {{ $cabinet['telephone'] }}@endif
    @if ($slogan)<br><b>{{ $slogan }}</b>@endif
    @if (!empty($cabinet['site'])) · {{ $cabinet['site'] }}@endif<br>{{ $t('Informations confidentielles.', 'معلومات سرية.') }}</div>
</div>
@if (! $prochain)
<script>
  const grille = document.getElementById('grille'), ok = document.getElementById('ok'), heure = document.getElementById('heure');
  const motif = document.getElementById('motif'), date = document.getElementById('date');
  async function charger() {
    heure.value = ''; ok.disabled = true; grille.innerHTML = '<div class="vide">{{ $t('Chargement…', 'جارٍ التحميل…') }}</div>';
    try {
      const r = await fetch(`{{ route('espace.creneaux') }}?motif=${motif.value}&date=${date.value}`, { headers: { Accept: 'application/json' } });
      const h = r.ok ? await r.json() : [];
      grille.innerHTML = h.length ? '' : '<div class="vide">{{ $t('Plus de place ce jour-là. Choisissez un autre jour.', 'لا توجد أماكن في هذا اليوم. اختاروا يوما آخر.') }}</div>';
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

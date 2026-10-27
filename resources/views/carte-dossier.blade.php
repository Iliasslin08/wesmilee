@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); $slogan = !empty($cabinet['slogan'] ?? null) ? ($AR && $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan']) : null; @endphp
@php
    $dh = fn ($n) => number_format((float) $n, 0, ',', ' ').' DH';
    $methodes = ['especes' => 'Espèces', 'carte' => 'Carte', 'cheque' => 'Chèque', 'virement' => 'Virement'];
@endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Mon dossier', 'ملفي') }} · {{ $patient->nom_complet }}</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #f3f9f9; color: #12303a; font-size: 15px; }
  .wrap { max-width: 640px; margin: 0 auto; padding: 16px; }
  .tete { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 6px 20px rgba(11,95,107,.08); }
  .tete .ph { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; background: #e3eef0; flex: none; }
  .tete h1 { font-size: 20px; margin: 0; color: #0b5f6b; } .tete p { margin: 2px 0 0; color: #5b737a; font-size: 13px; }
  .logo { height: 46px; margin-inline-start: auto; }
  .bloc { background: #fff; border-radius: 16px; padding: 14px 16px; margin-top: 12px; box-shadow: 0 6px 20px rgba(11,95,107,.06); }
  h2 { font-size: 15px; color: #0b5f6b; margin: 0 0 8px; }
  .rdv { background: linear-gradient(135deg, #04a3b5, #0b5f6b); color: #fff; }
  .rdv h2 { color: #bff0f4; } .rdv b { font-size: 19px; }
  ul { list-style: none; padding: 0; margin: 0; } li { padding: 8px 0; border-bottom: 1px solid #e3eef0; } li:last-child { border: 0; }
  .muted { color: #6b8189; font-size: 13px; }
  .pill { display: inline-block; border-radius: 20px; padding: 1px 10px; font-size: 12px; font-weight: 600; }
  .ok { background: #e3f4d6; color: #3d7a12; } .cours { background: #dff4f6; color: #037786; } .att { background: #fff1c9; color: #7a5a00; }
  .totaux { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; text-align: center; }
  .totaux div { background: #f3f9f9; border-radius: 10px; padding: 8px 4px; } .totaux b { display: block; font-size: 17px; }
  .reste b { color: #b3261e; } .solde b { color: #3d7a12; }
  .actions { text-align: center; margin: 16px 0; } .actions button { padding: 12px 20px; font-size: 15px; font-weight: bold; color: #fff; background: #04a3b5; border: 0; border-radius: 10px; }
  .pied { text-align: center; color: #6b8189; font-size: 12px; margin: 12px 0 24px; }
  @media print { .actions { display: none; } body { background: #fff; } .bloc, .tete { box-shadow: none; border: 1px solid #dcebed; } }
</style>
</head>
<body>
@include('partials.langue')
<div class="wrap">
  <div class="tete">
    @if ($photo)<img class="ph" src="{{ route('carte.photo', $patient->carte_token) }}" alt="">@endif
    <div>
      <h1>{{ $patient->nom_complet }}</h1>
      <p>{{ $t('Dossier n°', 'ملف رقم') }} {{ $dossier?->numero }}@if ($dossier?->docteurConsultation) · {{ $dossier->docteurConsultation->name }}@endif</p>
      <p>{{ $t('Mis à jour le', 'آخر تحديث') }} {{ now()->format('d/m/Y H:i') }}</p>
    </div>
    <img class="logo" src="/images/logo-wesmile.png" alt="We Smile">
  </div>

  <div class="bloc rdv">
    <h2>{{ $t('Prochaine séance', 'الحصة القادمة') }}</h2>
    @forelse ($prochains as $i => $r)
      @if ($i === 0)
        <b>{{ ucfirst(\App\Support\Langue::date($r->debut)) }} {{ $t('à', 'على الساعة') }} {{ $r->debut->format('H:i') }}</b>
        <div>{{ \App\Support\Langue::mot($r->mission) }} · {{ $r->docteur?->name }}</div>
      @else
        <div style="margin-top:6px;font-size:13px;opacity:.9">{{ $t('Puis :', 'ثم :') }} {{ $r->debut->format('d/m H:i') }} · {{ \App\Support\Langue::mot($r->mission) }}</div>
      @endif
    @empty
      <div>{{ $t('Aucune séance prévue pour le moment.', 'لا توجد حصة مبرمجة حاليا.') }}@if (!empty($cabinet['telephone'])) {{ $t('Appelez le centre au', 'اتصلوا بالمركز على الرقم') }} {{ $cabinet['telephone'] }}.@endif</div>
    @endforelse
  </div>

  @if ($actes->isNotEmpty())
  <div class="bloc">
    <h2>{{ $t('Mon traitement', 'علاجي') }}</h2>
    <ul>
      @foreach ($actes as $a)
        <li>{{ $a->libelle }}@if ($a->dents) · {{ $t('dent(s)', 'الأسنان') }} {{ $a->dents }}@endif
          <span class="pill {{ $a->statut->value === 'fait' ? 'ok' : ($a->statut->value === 'en_cours' ? 'cours' : 'att') }}" style="float:inline-end">{{ \App\Support\Langue::mot($a->statut->label()) }}</span></li>
      @endforeach
    </ul>
  </div>
  @endif

  @if ($labo->isNotEmpty())
  <div class="bloc">
    <h2>{{ $t('Laboratoire', 'المختبر') }}</h2>
    <ul>
      @foreach ($labo as $c)
        <li>{{ $c->travail }}@if ($c->dents) · {{ $c->dents }}@endif
          <span class="pill {{ in_array($c->statut->value, ['pret', 'livre', 'pose']) ? 'ok' : 'cours' }}" style="float:inline-end">{{ \App\Support\Langue::mot($c->statut->label()) }}</span>
          @if ($c->date_pose && $c->statut->value !== 'pose')<div class="muted">{{ $t('Pose prévue le', 'التركيب مبرمج يوم') }} {{ $c->date_pose->format('d/m/Y') }}</div>@endif
        </li>
      @endforeach
    </ul>
  </div>
  @endif

  <div class="bloc">
    <h2>{{ $t('Mes séances', 'حصصي') }}</h2>
    @if ($seances->isEmpty())
      <div class="muted">{{ $t('Pas encore de séance.', 'لا توجد حصص بعد.') }}</div>
    @else
      <ul>
        @foreach ($seances as $s)
          <li><b>{{ $s->debut->format('d/m/Y') }}</b> · {{ $s->docteur?->name }}
            @if ($s->actes->isNotEmpty())<div class="muted">{{ $s->actes->map(fn ($a) => $a->libelle.($a->dents ? ' ('.$a->dents.')' : ''))->implode(', ') }}</div>@endif
            @if ($s->suite)<div class="muted">{{ $t('Suite :', 'التالي :') }} {{ $s->suite }}</div>@endif
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  <div class="bloc">
    <h2>{{ $t('Mes paiements', 'مدفوعاتي') }}</h2>
    <div class="totaux">
      <div>{{ $t('Total', 'المجموع') }}<b>{{ $dh($total) }}</b></div>
      <div>{{ $t('Payé', 'المدفوع') }}<b>{{ $dh($paye) }}</b></div>
      <div class="{{ $reste > 0 ? 'reste' : 'solde' }}">{{ $t('Reste', 'الباقي') }}<b>{{ $reste > 0 ? $dh($reste) : $t('Soldé', 'مؤدى بالكامل') }}</b></div>
    </div>
    @if ($paiements->isNotEmpty())
      <ul style="margin-top:8px">
        @foreach ($paiements as $p)
          <li>{{ $p->date->format('d/m/Y') }} · <b>{{ $dh($p->montant) }}</b> · {{ \App\Support\Langue::mot($methodes[$p->methode] ?? $p->methode) }}
            <span class="muted" style="float:inline-end">{{ $t('Reçu n°', 'وصل رقم') }} {{ $p->numero_recu }}</span></li>
        @endforeach
      </ul>
    @endif
  </div>

  @include('partials.avis', ['classe' => 'bloc'])

  <div class="actions">
    @if ($prochains->isEmpty())<a href="{{ route('espace.index') }}" style="display:inline-block;margin-bottom:10px;padding:12px 20px;font-weight:bold;color:#fff;background:#048ea7;border-radius:10px;text-decoration:none">{{ $t('Prendre un rendez-vous', 'حجز موعد') }}</a><br>@endif
    <button onclick="window.print()">{{ $t('Télécharger en PDF', 'تحميل PDF') }}</button>
  </div>
  <div class="pied">{{ $cabinet['nom'] ?? 'Wesmile Center' }} · {{ $cabinet['ville'] ?? 'Rabat' }} {{ $cabinet['telephone'] ?? '' }}@if ($slogan)<br><b>{{ $slogan }}</b>@endif<br>{{ $t('Ces informations sont confidentielles. Ne partagez pas votre carte.', 'هذه المعلومات سرية. لا تشاركوا بطاقتكم.') }}</div>
</div>
</body>
</html>

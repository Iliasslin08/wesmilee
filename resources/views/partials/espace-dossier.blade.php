{{-- « Mon espace » complet (connexion avec le mot de passe donné à l'accueil). Variables : voir EspacePatientController::index. --}}
@php
    $etatsDent = [
        'carie' => ['#ef4444', $t('Carie', 'تسوس')],
        'a_surveiller' => ['#f59e0b', $t('À surveiller', 'للمراقبة')],
        'traitee' => ['#3b82f6', $t('Soignée', 'معالجة')],
        'couronne' => ['#8b5cf6', $t('Couronne', 'تاج')],
        'implant' => ['#0d9488', $t('Implant', 'زرعة')],
        'absente' => ['#9ca3af', $t('Absente', 'مفقودة')],
    ];
    $typesMsg = ['controle' => '🦷', 'implant' => '🔩', 'labo' => '🧪', 'paiement' => '💳', 'info' => 'ℹ️'];
    $mutuelles = \App\Models\Patient::MUTUELLES;
    $statutsFeuille = ['remise' => $t('Remise', 'مسلمة'), 'remboursee' => $t('Remboursée', 'مستردة'), 'refusee' => $t('Refusée', 'مرفوضة')];
    $nouveaux = $messages->whereNull('lu_le')->count();
@endphp
<style>
  .msg { padding: 10px 0; border-bottom: 1px solid #e8f1f2; } .msg:last-child { border: 0; }
  .msg b { display: block; } .msg .muted { margin-top: 2px; }
  .nv { background: #e11d48; color: #fff; border-radius: 999px; font-size: 11px; padding: 1px 8px; margin-inline-start: 6px; vertical-align: middle; }
  .bouche { display: grid; grid-template-columns: repeat(16, 1fr); gap: 3px; direction: ltr; }
  .bouche div { text-align: center; font-size: 10px; border-radius: 6px; padding: 6px 0; background: #f3f9f9; border: 1px solid #dbe9eb; color: #48616a; }
  .bouche .sep { grid-column: 1 / -1; height: 6px; background: none; border: 0; padding: 0; }
  .legende { display: flex; flex-wrap: wrap; gap: 6px 12px; font-size: 12px; margin-top: 10px; }
  .legende span { display: inline-flex; align-items: center; gap: 5px; } .legende i { width: 12px; height: 12px; border-radius: 3px; display: inline-block; }
  .mdp input { width: 100%; font: 15px Poppins, Arial; padding: 10px 12px; border: 1.5px solid #cfe2e5; border-radius: 10px; margin-bottom: 8px; }
</style>

{{-- Messages --}}
<div class="carte" id="messages">
  <h2><span class="ic">💬</span>{{ $t('Mes messages', 'رسائلي') }}@if ($nouveaux)<span class="nv">{{ $nouveaux }} {{ $t('nouveau(x)', 'جديدة') }}</span>@endif</h2>
  @forelse ($messages as $m)
    <div class="msg">
      <b>{{ $typesMsg[$m->type] ?? 'ℹ️' }} {{ $m->titre }}@if (! $m->lu_le)<span class="nv">{{ $t('Nouveau', 'جديد') }}</span>@endif</b>
      <div>{{ $m->texte }}</div>
      <div class="muted">{{ $m->created_at->format('d/m/Y H:i') }}</div>
    </div>
  @empty
    <div class="muted">{{ $t('Pas de message pour le moment.', 'لا توجد رسائل حاليا.') }}</div>
  @endforelse
</div>

{{-- Traitement --}}
<div class="carte" id="traitement">
  <h2><span class="ic">🦷</span>{{ $t('Mon traitement', 'علاجي') }}</h2>
  @if ($actes->isEmpty())
    <div class="muted">{{ $t('Pas encore de plan de traitement.', 'لا يوجد برنامج علاج بعد.') }}</div>
  @else
    <ul>
      @foreach ($actes as $a)
        <li>{{ $a->libelle }}@if ($a->dents) · {{ $t('dent(s)', 'الأسنان') }} {{ $a->dents }}@endif
          <span class="pill {{ $a->statut->value === 'fait' ? 'p-ok' : ($a->statut->value === 'en_cours' ? 'p-cours' : 'p-att') }}">{{ \App\Support\Langue::mot($a->statut->label()) }}</span>
          @if ($a->fait_le)<div class="muted">{{ $t('Fait le', 'تم يوم') }} {{ $a->fait_le->format('d/m/Y') }}</div>@endif
        </li>
      @endforeach
    </ul>
  @endif
</div>

{{-- Schéma dentaire --}}
<div class="carte">
  <h2><span class="ic">🗺️</span>{{ $t('Mon schéma dentaire', 'خريطة أسناني') }}</h2>
  <div class="bouche">
    @foreach ([array_merge(range(18, 11), range(21, 28)), array_merge(range(48, 41), range(31, 38))] as $i => $rang)
      @if ($i === 1)<div class="sep"></div>@endif
      @foreach ($rang as $n)
        @php $e = $dents[$n] ?? null; $c = $etatsDent[$e][0] ?? null; @endphp
        <div @if ($c) style="background:{{ $c }};border-color:{{ $c }};color:#fff;font-weight:700" title="{{ $etatsDent[$e][1] }}" @endif>{{ $n }}</div>
      @endforeach
    @endforeach
  </div>
  <div class="legende">
    @foreach ($etatsDent as [$c, $l])<span><i style="background:{{ $c }}"></i>{{ $l }}</span>@endforeach
  </div>
  <div class="muted" style="margin-top:6px">{{ $t('En haut : mâchoire du haut. En bas : mâchoire du bas.', 'في الأعلى: الفك العلوي. في الأسفل: الفك السفلي.') }}</div>
</div>

{{-- Laboratoire --}}
<div class="carte">
  <h2><span class="ic">🧪</span>{{ $t('Suivi du laboratoire', 'تتبع المختبر') }}</h2>
  @if ($labo->isEmpty())
    <div class="muted">{{ $t('Aucun travail au laboratoire.', 'لا يوجد عمل في المختبر.') }}</div>
  @else
    <ul>
      @foreach ($labo as $c)
        <li>{{ $c->travail }}@if ($c->dents) · {{ $c->dents }}@endif
          <span class="pill {{ in_array($c->statut->value, ['pret', 'livre', 'pose']) ? 'p-ok' : 'p-cours' }}">{{ \App\Support\Langue::mot($c->statut->label()) }}</span>
          @if ($c->date_pose && $c->statut->value !== 'pose')<div class="muted">{{ $t('Pose prévue le', 'التركيب مبرمج يوم') }} {{ $c->date_pose->format('d/m/Y') }}</div>@endif
        </li>
      @endforeach
    </ul>
  @endif
</div>

{{-- Séances --}}
<div class="carte">
  <h2><span class="ic">🗓️</span>{{ $t('Mes séances', 'حصصي') }}</h2>
  @if ($seances->isEmpty())
    <div class="muted">{{ $t('Pas encore de séance.', 'لا توجد حصص بعد.') }}</div>
  @else
    <ul>
      @foreach ($seances as $s)
        <li><b>{{ $s->debut->format('d/m/Y') }}</b> · {{ $s->docteur?->name }}
          @if ($s->actes->isNotEmpty())<div class="muted">{{ $s->actes->map(fn ($a) => $a->libelle.($a->dents ? ' ('.$a->dents.')' : ''))->implode(', ') }}</div>@endif
        </li>
      @endforeach
    </ul>
  @endif
</div>

{{-- Paiements (toujours à jour : calculés depuis la caisse) --}}
<div class="carte" id="paiements">
  <h2><span class="ic">💳</span>{{ $t('Mes paiements', 'مدفوعاتي') }}</h2>
  <div class="totaux">
    <div>{{ $t('Total', 'المجموع') }}<b>{{ $dh($total) }}</b></div>
    <div>{{ $t('Payé', 'المدفوع') }}<b>{{ $dh($paye) }}</b></div>
    <div class="{{ $reste > 0 ? 'reste' : 'solde' }}">{{ $t('Reste', 'الباقي') }}<b>{{ $reste > 0 ? $dh($reste) : $t('Soldé', 'مؤدى بالكامل') }}</b></div>
  </div>
  @if ($actes->isNotEmpty())
    <details style="margin-top:10px"><summary>{{ $t('Détail des soins', 'تفاصيل العلاجات') }}</summary>
      <ul>
        @foreach ($actes as $a)
          <li>{{ $a->libelle }}@if ($a->dents) ({{ $a->dents }})@endif<span style="float:inline-end;font-weight:600">{{ $dh($a->prix_convenu) }}</span></li>
        @endforeach
      </ul>
    </details>
  @endif
  @if ($paiements->isNotEmpty())
    <ul style="margin-top:8px">
      @foreach ($paiements as $p)
        <li>{{ $p->date->format('d/m/Y') }} · <b>{{ $dh($p->montant) }}</b> · {{ \App\Support\Langue::mot($methodes[$p->methode] ?? $p->methode) }}
          <span class="muted" style="float:inline-end">{{ $t('Reçu n°', 'وصل رقم') }} {{ $p->numero_recu }}</span></li>
      @endforeach
    </ul>
  @endif
</div>

{{-- Mutuelle --}}
<div class="carte" id="mutuelle">
  <h2><span class="ic">🏥</span>{{ $t('Ma mutuelle', 'تأميني الصحي') }}</h2>
  <dl class="infos">
    <dt>{{ $t('Organisme', 'الهيئة') }}</dt><dd>{{ $mutuelles[$patient->mutuelle] ?? $t('Non renseigné', 'غير محدد') }}</dd>
    @if ($patient->mutuelle_numero)<dt>{{ $t('N° d’immatriculation', 'رقم التسجيل') }}</dt><dd dir="ltr">{{ $patient->mutuelle_numero }}</dd>@endif
  </dl>
  <h2 style="margin-top:14px">{{ $t('Mes feuilles de soins', 'أوراق العلاج') }}</h2>
  @if ($feuilles->isEmpty())
    <div class="muted">{{ $t('Aucune feuille de soins remise pour le moment.', 'لم تسلم أي ورقة علاج بعد.') }}</div>
  @else
    <ul>
      @foreach ($feuilles as $f)
        <li>{{ $f->remise_le->format('d/m/Y') }} · {{ $mutuelles[$f->organisme] ?? $f->organisme }} · <b>{{ $dh($f->montant) }}</b>
          <span class="pill {{ $f->statut === 'remboursee' ? 'p-ok' : ($f->statut === 'refusee' ? 'p-att' : 'p-cours') }}">{{ $statutsFeuille[$f->statut] ?? $f->statut }}</span></li>
      @endforeach
    </ul>
  @endif
</div>

{{-- Mot de passe --}}
<div class="carte mdp" id="mdp">
  <details @if ($errors->has('actuel') || $errors->has('nouveau')) open @endif>
    <summary>🔑 {{ $t('Changer mon mot de passe', 'تغيير كلمة السر') }}</summary>
    @if ($errors->has('actuel') || $errors->has('nouveau'))<div class="err" style="margin-top:10px">{{ $errors->first('actuel') ?: $errors->first('nouveau') }}</div>@endif
    <form method="post" action="{{ route('espace.mot-de-passe') }}" style="margin-top:10px">
      @csrf
      <input type="password" name="actuel" placeholder="{{ $t('Mot de passe actuel', 'كلمة السر الحالية') }}" required>
      <input type="password" name="nouveau" placeholder="{{ $t('Nouveau mot de passe (6 caractères min.)', 'كلمة السر الجديدة (6 أحرف على الأقل)') }}" minlength="6" required>
      <input type="password" name="nouveau_confirmation" placeholder="{{ $t('Confirmer le nouveau mot de passe', 'تأكيد كلمة السر الجديدة') }}" minlength="6" required>
      <button class="btn" style="margin-top:4px">{{ $t('Enregistrer', 'حفظ') }}</button>
    </form>
  </details>
</div>

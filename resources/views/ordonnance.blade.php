@php $patient = $o->dossier->patient; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Ordonnance · {{ $patient->nom_complet }}</title>
<style>
  @page { size: A5 portrait; margin: 12mm; }
  body { font-family: Arial, Helvetica, sans-serif; background: #eef3f3; margin: 0; padding: 24px; color: #1b2b2c; }
  .actions { max-width: 560px; margin: 0 auto 12px; text-align: right; }
  .actions button { padding: 10px 18px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; background: #04a3b5; color: #fff; }
  .page { max-width: 560px; min-height: 780px; margin: 0 auto; background: #fff; padding: 28px 32px; box-shadow: 0 3px 14px rgba(0,0,0,.1); display: flex; flex-direction: column; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #04a3b5; padding-bottom: 12px; }
  .doc { text-align: right; font-size: 13px; line-height: 1.5; }
  .doc b { font-size: 16px; color: #0b5f6b; }
  .titre { text-align: center; font-size: 22px; letter-spacing: 6px; color: #0b5f6b; font-weight: bold; margin: 22px 0 6px; }
  .patient { display: flex; justify-content: space-between; font-size: 14px; margin: 12px 0 20px; }
  ol { padding-left: 20px; margin: 0; }
  li { margin-bottom: 16px; font-size: 15px; }
  li b { font-size: 16px; }
  li span { display: block; color: #3d5456; margin-top: 3px; }
  .notes { margin-top: 10px; font-style: italic; color: #3d5456; }
  .sign { margin-top: auto; display: flex; justify-content: flex-end; }
  .cachet { width: 200px; height: 90px; border: 1px dashed #9bb; border-radius: 8px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 6px; font-size: 12px; color: #7a9092; }
  .pied { border-top: 1px solid #dcebed; margin-top: 16px; padding-top: 8px; font-size: 11px; color: #6b8189; text-align: center; }
  @media print { body { background: #fff; padding: 0; } .actions { display: none; } .page { box-shadow: none; max-width: none; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Imprimer l'ordonnance</button></div>
<div class="page">
  <div class="head">
    <img src="/images/logo-wesmile.png" alt="We Smile" style="height:70px">
    <div class="doc"><b>{{ $o->docteur?->name }}</b><br>{{ $o->docteur?->specialite }}<br>{{ $cabinet['nom'] }}</div>
  </div>
  <div class="titre">ORDONNANCE</div>
  <div class="patient">
    <div>Patient : <b>{{ $patient->nom_complet }}</b>@if($patient->age) · {{ $patient->age }} ans @endif</div>
    <div>{{ $cabinet['ville'] ?? 'Rabat' }}, le {{ $o->date->format('d/m/Y') }}</div>
  </div>
  <ol>
    @foreach ($o->lignes as $l)
      <li><b>{{ $l['medicament'] }}</b>
        @if(!empty($l['posologie']) || !empty($l['duree']))<span>{{ $l['posologie'] ?? '' }}@if(!empty($l['duree'])) · pendant {{ $l['duree'] }}@endif</span>@endif
      </li>
    @endforeach
  </ol>
  @if ($o->notes)<div class="notes">{{ $o->notes }}</div>@endif
  <div class="sign"><div class="cachet">Signature et cachet</div></div>
  <div class="pied">{{ $cabinet['nom'] }} @if(!empty($cabinet['adresse'])) · {{ $cabinet['adresse'] }} @endif @if(!empty($cabinet['telephone'])) · Tél. {{ $cabinet['telephone'] }} @endif · Dossier n° {{ $o->dossier->numero }}</div>
</div>
</body>
</html>

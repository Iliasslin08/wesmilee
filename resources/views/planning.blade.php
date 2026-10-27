<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Planning · {{ $titre }}</title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;margin:0;padding:24px;color:#111}
  .head{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #0b5f6b;padding-bottom:8px;margin-bottom:14px}
  .logo{font-size:22px;font-weight:bold;color:#0b5f6b}.logo span{color:#80bb4b}
  h2{font-size:16px;margin:18px 0 6px;color:#0b5f6b}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th{background:#e6f2f2;text-align:left;padding:6px;border:1px solid #bcd}
  td{padding:6px;border:1px solid #cdd;vertical-align:top}
  tr:nth-child(even) td{background:#fafcfc}
  .h{font-weight:bold;white-space:nowrap}.vide{width:70px}
  .btn{margin-bottom:12px}
  @media print{.btn{display:none}body{padding:0}h2{break-after:avoid}tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="btn"><button onclick="window.print()" style="padding:10px 18px;background:#04a3b5;color:#fff;border:0;border-radius:6px;font-weight:bold;cursor:pointer">Imprimer</button></div>
<div class="head">
  <div><img src="/images/logo-wesmile.png" alt="We Smile" style="height:54px;display:block"><div style="font-size:12px;color:#555">{{ $cabinet['nom'] }} · {{ $cabinet['ville'] }} {{ $cabinet['telephone'] }}</div></div>
  <div style="text-align:right"><b>Planning · {{ $titre }}</b><br><span style="font-size:11px;color:#555">Imprimé le {{ now()->format('d/m/Y à H:i') }}</span></div>
</div>
@forelse ($parJour as $jour => $rdvs)
  <h2>{{ ucfirst(\Illuminate\Support\Carbon::parse($jour)->translatedFormat('l j F Y')) }} · {{ $rdvs->count() }} rendez-vous</h2>
  <table>
    <thead><tr><th>Heure</th><th>Patient</th><th>Téléphone</th><th>Docteur</th><th>Salle</th><th>Soin</th><th>Dossier</th><th class="vide">Arrivé</th></tr></thead>
    <tbody>
    @foreach ($rdvs as $r)
      <tr>
        <td class="h">{{ $r->debut->format('H:i') }}–{{ $r->fin?->format('H:i') }}</td>
        <td><b>{{ $r->patient->nom_complet }}</b>@if($r->urgence) · URGENCE @endif</td>
        <td>{{ $r->patient->telephone }}</td>
        <td>{{ $r->docteur?->name }}</td>
        <td>{{ $r->docteur?->salle }}</td>
        <td>{{ $r->mission }}</td>
        <td>{{ $r->patient->dossier?->numero }}</td>
        <td></td>
      </tr>
    @endforeach
    </tbody>
  </table>
@empty
  <p>Aucun rendez-vous.</p>
@endforelse
<script>if (location.search.includes('auto=1')) window.print()</script>
</body>
</html>

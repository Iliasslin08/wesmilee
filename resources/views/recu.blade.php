@php
    $dh = fn ($n) => number_format((float) $n, 2, ',', ' ');
    $patient = $p->dossier->patient;
    $coche = fn ($m) => $p->methode === $m ? '☒' : '☐';
    $adresse = collect([$cabinet['adresse'] ?? null, $cabinet['ville'] ?? null])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Reçu n° {{ $p->numero_recu }}</title>
<style>
  @page { size: A4 landscape; margin: 8mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Times New Roman', Times, serif; background: #e9e6df; margin: 0; padding: 24px; color: #1a1a1a; }
  .actions { max-width: 1000px; margin: 0 auto 12px; text-align: right; font-family: Arial, sans-serif; }
  .actions button { padding: 10px 18px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; background: #04a3b5; color: #fff; }

  /* carnet : souche (reste au cabinet) + reçu (remis au patient) */
  .carnet { max-width: 1000px; margin: 0 auto; display: grid; grid-template-columns: 260px 1fr; background: #fffdf6; box-shadow: 0 3px 14px rgba(0,0,0,.15); }
  .souche { padding: 18px 16px; border-right: 2px dashed #9a9486; font-size: 13px; position: relative; }
  .souche::after { content: '✂'; position: absolute; right: -11px; top: 46%; background: #fffdf6; color: #7a7466; font-size: 16px; }
  .recu { padding: 20px 26px; border: 3px double #0b5f6b; margin: 10px; }

  .num { font-family: 'Courier New', monospace; font-weight: bold; color: #b3261e; font-size: 20px; letter-spacing: 1px; }
  .titre { font-size: 26px; font-weight: bold; letter-spacing: 6px; color: #0b5f6b; text-align: center; }
  .entete { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0b5f6b; padding-bottom: 10px; margin-bottom: 14px; }
  .logo { font-family: Arial, sans-serif; font-size: 24px; font-weight: bold; color: #0b5f6b; }
  .logo span { color: #80bb4b; }
  .cab { font-size: 12px; color: #444; line-height: 1.4; }
  .montant-box { border: 2px solid #1a1a1a; padding: 6px 12px; font-size: 22px; font-weight: bold; font-family: 'Courier New', monospace; white-space: nowrap; }
  .montant-box small { font-size: 13px; font-family: 'Times New Roman', serif; }

  /* lignes pointillées comme sur un carnet */
  .ligne { display: flex; align-items: baseline; gap: 8px; margin: 13px 0; font-size: 16px; }
  .ligne .l { white-space: nowrap; }
  .ligne .v { flex: 1; border-bottom: 1px dotted #555; padding: 0 6px 2px; font-family: 'Segoe Script', 'Brush Script MT', 'Comic Sans MS', cursive; font-size: 18px; color: #1d3f8f; min-height: 24px; }
  .souche .ligne { font-size: 13px; margin: 9px 0; }
  .souche .ligne .v { font-size: 14px; }
  .methodes { display: flex; gap: 22px; font-size: 15px; margin: 12px 0; }
  .bas { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 18px; }
  .cachet { width: 230px; height: 95px; border: 1px solid #777; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 6px; font-size: 12px; color: #555; }
  .reste { color: #b3261e; }
  .mention { font-size: 11px; color: #666; margin-top: 10px; font-style: italic; }

  @media print {
    body { background: #fff; padding: 0; }
    .actions { display: none; }
    .carnet { box-shadow: none; max-width: none; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Imprimer le reçu</button></div>

<div class="carnet">
  <!-- SOUCHE : reste dans le carnet du cabinet -->
  <div class="souche">
    <div style="font-weight:bold;letter-spacing:2px;color:#0b5f6b">SOUCHE</div>
    <div class="num">N° {{ $p->numero_recu }}</div>
    <div class="ligne"><span class="l">Date :</span><span class="v">{{ $p->date->format('d/m/Y') }}</span></div>
    <div class="ligne"><span class="l">Patient :</span><span class="v">{{ $patient->nom_complet }}</span></div>
    <div class="ligne"><span class="l">Dossier :</span><span class="v">{{ $p->dossier->numero }}</span></div>
    <div class="ligne"><span class="l">Montant :</span><span class="v">{{ $dh($p->montant) }} DH</span></div>
    <div class="ligne"><span class="l">Paiement :</span><span class="v">{{ ['especes'=>'Espèces','carte'=>'Carte','cheque'=>'Chèque','virement'=>'Virement'][$p->methode] ?? $p->methode }}</span></div>
    <div class="ligne"><span class="l">Reste :</span><span class="v">{{ $dh($p->reste_apres) }} DH</span></div>
    <div class="ligne"><span class="l">Par :</span><span class="v">{{ $p->encaissePar?->name }}</span></div>
  </div>

  <!-- REÇU : remis au patient -->
  <div class="recu">
    <div class="entete">
      <div>
        <img src="/images/logo-wesmile.png" alt="We Smile" style="height:64px;display:block">
        <div class="cab">
          {{ $cabinet['nom'] }}<br>
          @if($adresse){{ $adresse }}<br>@endif
          @if(!empty($cabinet['telephone']))Tél. : {{ $cabinet['telephone'] }}@endif
        </div>
      </div>
      <div style="text-align:center">
        <div class="titre">REÇU</div>
        <div class="num">N° {{ $p->numero_recu }}</div>
      </div>
      <div class="montant-box">{{ $dh($p->montant) }} <small>DH</small></div>
    </div>

    <div class="ligne"><span class="l">Reçu de M. / Mme :</span><span class="v">{{ $p->facture_a ?: $patient->nom_complet }}</span></div>
    <div class="ligne"><span class="l">Patient :</span><span class="v">{{ $patient->nom_complet }}</span><span class="l">Dossier n° :</span><span class="v" style="flex:0 0 110px">{{ $p->dossier->numero }}</span></div>
    <div class="ligne"><span class="l">La somme de :</span><span class="v">{{ ucfirst($enLettres) }}</span></div>
    <div class="ligne"><span class="l">Pour :</span><span class="v">{{ $p->notes ?: 'Soins dentaires' }}</span></div>

    <div class="methodes">
      <span>Paiement :</span>
      <span>{{ $coche('especes') }} Espèces</span>
      <span>{{ $coche('cheque') }} Chèque</span>
      <span>{{ $coche('carte') }} Carte</span>
      <span>{{ $coche('virement') }} Virement</span>
    </div>

    <div class="ligne">
      <span class="l">Total du traitement :</span><span class="v">{{ $dh($totalTraitement) }} DH</span>
      <span class="l">Déjà payé :</span><span class="v">{{ $dh($dejaPaye) }} DH</span>
    </div>
    <div class="ligne">
      <span class="l"><b>Reste à payer :</b></span><span class="v reste"><b>{{ $p->reste_apres > 0 ? $dh($p->reste_apres).' DH' : 'Soldé' }}</b></span>
    </div>

    <div class="bas">
      <div>
        <div class="ligne" style="margin:0"><span class="l">Fait à {{ $cabinet['ville'] ?? 'Rabat' }}, le</span><span class="v" style="min-width:150px">{{ $p->date->format('d/m/Y') }}</span></div>
        <div class="mention">Encaissé par {{ $p->encaissePar?->name ?? '—' }} · Conservez ce reçu.</div>
      </div>
      <div class="cachet">Cachet et signature</div>
    </div>
  </div>
</div>
</body>
</html>

@php
    $patient = $dossier->patient;
    $fmt = fn ($v) => number_format((float) $v, 2, ',', ' ');
    // dents du schéma (numérotation FDI) ; largeur selon le type de dent
    $largeur = [1 => 24, 2 => 22, 3 => 24, 4 => 25, 5 => 25, 6 => 33, 7 => 31, 8 => 29];
    $haut = array_merge(range(18, 11), range(21, 28));
    $bas = array_merge(range(48, 41), range(31, 38));
    $total = array_sum(array_column($lignes, 'prix'));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Devis · {{ $patient->nom_complet }}</title>
<style>
  @page { size: A4 portrait; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; background: #eef3f3; margin: 0; padding: 24px; color: #111; }
  .actions { max-width: 760px; margin: 0 auto 12px; display: flex; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: #456; }
  .actions button { white-space: nowrap; padding: 10px 18px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; background: #04a3b5; color: #fff; }
  .actions button.sec { background: #fff; color: #04a3b5; border: 1px solid #04a3b5; }
  .page { max-width: 760px; min-height: 1060px; margin: 0 auto; background: #fff; padding: 30px 40px; box-shadow: 0 3px 14px rgba(0,0,0,.1); display: flex; flex-direction: column; }
  .logo { text-align: center; }
  .logo img { height: 80px; }
  h1 { text-align: center; font-size: 22px; margin: 18px 0 20px; letter-spacing: 1px; }
  .ligne-patient { display: flex; justify-content: space-between; align-items: baseline; padding: 0 60px; font-size: 14px; }
  .nom { font-weight: bold; text-decoration: underline; text-transform: uppercase; }
  .date { font-size: 13px; }
  .schema { text-align: center; margin: 14px 0 18px; }
  .schema svg { width: 440px; height: auto; }
  .dent .couronne { fill: #fff; stroke: #a9b4b6; stroke-width: 1; }
  .dent .racine { fill: #f4ede2; stroke: #c9bfae; stroke-width: .8; }
  .dent text { font-size: 9px; fill: #6b7a7c; text-anchor: middle; }
  .dent.choisie .couronne { fill: #9fe3ea; stroke: #04869a; stroke-width: 1.6; }
  .dent.choisie .racine { fill: #d6f3f6; stroke: #04869a; }
  .dent.choisie text { fill: #04697a; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { border: 1.5px solid #000; padding: 7px 6px; }
  th { font-weight: bold; text-align: center; }
  td.dents { text-align: center; width: 110px; }
  td.prix { text-align: right; width: 190px; }
  th.dents { width: 110px; } th.prix { width: 190px; }
  td.suppr, th.suppr { border: 0; width: 22px; padding: 0 0 0 4px; }
  td.suppr button { border: 0; background: none; color: #c33; cursor: pointer; font-size: 16px; }
  [contenteditable]:hover { background: #f2fbfc; }
  [contenteditable]:focus { outline: 2px solid #04a3b5; background: #fff; }
  .total { display: flex; justify-content: flex-end; margin-right: 22px; }
  .total div { width: 300px; display: flex; justify-content: space-between; padding: 8px 10px; border-bottom: 1.5px solid #000; font-size: 14px; }
  .total b:last-child { font-size: 15px; }
  .phrase { font-size: 12px; margin: 18px 0 0 14px; }
  .signatures { display: flex; justify-content: space-around; margin-top: 28px; font-size: 13px; min-height: 120px; }
  .pied { margin-top: auto; border-top: 1px solid #dcebed; padding-top: 8px; font-size: 11px; color: #6b8189; text-align: center; }
  @media print {
    body { background: #fff; padding: 0; }
    .actions, .suppr { display: none !important; }
    .page { box-shadow: none; max-width: none; min-height: 270mm; padding: 0; }
    [contenteditable]:hover { background: none; }
  }
</style>
</head>
<body>
<div class="actions">
  <span>Vous pouvez corriger le texte, les dents ou les montants avant d'imprimer (cliquez dessus).</span>
  <span style="display:flex;gap:8px">
    <button class="sec" type="button" onclick="ajouterLigne()">+ Ligne</button>
    <button type="button" onclick="window.print()">Imprimer le devis</button>
  </span>
</div>
<div class="page">
  <div class="logo"><img src="/images/logo-wesmile.png" alt="We Smile"></div>
  <h1>DEVIS</h1>
  <div class="ligne-patient">
    <span class="nom" contenteditable>{{ $patient->nom_complet }}</span>
    <span class="date">{{ $cabinet['ville'] ?? 'Rabat' }} le, <span contenteditable style="margin-left:40px">{{ now()->format('d/m/Y') }}</span></span>
  </div>

  <div class="schema">
    <svg viewBox="0 0 470 266" xmlns="http://www.w3.org/2000/svg" aria-label="Schéma dentaire">
      <rect x="4" y="34" width="462" height="50" rx="14" fill="#f2c4c8"/>
      <rect x="4" y="182" width="462" height="50" rx="14" fill="#f2c4c8"/>
      @foreach ([['dents' => $haut, 'haut' => true], ['dents' => $bas, 'haut' => false]] as $arcade)
        @php $x = 15; @endphp
        @foreach ($arcade['dents'] as $i => $n)
          @php
            $w = $largeur[$n % 10];
            if ($i === 8) { $x += 8; }
            $cx = $x + $w / 2;
            $molaire = $n % 10 >= 6;
          @endphp
          <g class="dent" data-dent="{{ $n }}">
            @if ($arcade['haut'])
              @if ($molaire)
                <path class="racine" d="M{{ $x + 3 }},76 Q{{ $x + 5 }},34 {{ $x + $w * .3 }},22 Q{{ $x + $w * .42 }},40 {{ $cx }},58 Q{{ $x + $w * .58 }},40 {{ $x + $w * .7 }},22 Q{{ $x + $w - 5 }},34 {{ $x + $w - 3 }},76 Z"/>
              @else
                <path class="racine" d="M{{ $x + 3 }},76 Q{{ $x + 4 }},36 {{ $cx }},{{ $n % 10 === 3 ? 14 : 22 }} Q{{ $x + $w - 4 }},36 {{ $x + $w - 3 }},76 Z"/>
              @endif
              <rect class="couronne" x="{{ $x + 1 }}" y="74" width="{{ $w - 2 }}" height="{{ $molaire ? 34 : 38 }}" rx="{{ $molaire ? 8 : 6 }}"/>
              <text x="{{ $cx }}" y="122">{{ $n }}</text>
            @else
              @if ($molaire)
                <path class="racine" d="M{{ $x + 3 }},190 Q{{ $x + 5 }},232 {{ $x + $w * .3 }},246 Q{{ $x + $w * .42 }},226 {{ $cx }},208 Q{{ $x + $w * .58 }},226 {{ $x + $w * .7 }},246 Q{{ $x + $w - 5 }},232 {{ $x + $w - 3 }},190 Z"/>
              @else
                <path class="racine" d="M{{ $x + 3 }},190 Q{{ $x + 4 }},230 {{ $cx }},{{ $n % 10 === 3 ? 254 : 246 }} Q{{ $x + $w - 4 }},230 {{ $x + $w - 3 }},190 Z"/>
              @endif
              <rect class="couronne" x="{{ $x + 1 }}" y="{{ $molaire ? 158 : 154 }}" width="{{ $w - 2 }}" height="{{ $molaire ? 34 : 38 }}" rx="{{ $molaire ? 8 : 6 }}"/>
              <text x="{{ $cx }}" y="140">{{ $n }}</text>
            @endif
          </g>
          @php $x += $w; @endphp
        @endforeach
      @endforeach
    </svg>
  </div>

  <table>
    <thead>
      <tr><th>ACTES</th><th class="dents">Dent(s)</th><th class="prix">Montant à payer</th><th class="suppr"></th></tr>
    </thead>
    <tbody id="lignes">
      @forelse ($lignes as $l)
        <tr>
          <td contenteditable>{{ $l['acte'] }}</td>
          <td class="dents" contenteditable>{{ $l['dents'] }}</td>
          <td class="prix" contenteditable>{{ $fmt($l['prix']) }}</td>
          <td class="suppr"><button type="button" title="Retirer la ligne" onclick="retirer(this)">×</button></td>
        </tr>
      @empty
        <tr>
          <td contenteditable></td><td class="dents" contenteditable></td><td class="prix" contenteditable>0,00</td>
          <td class="suppr"><button type="button" title="Retirer la ligne" onclick="retirer(this)">×</button></td>
        </tr>
      @endforelse
    </tbody>
  </table>
  <div class="total"><div><b>TOTAL :</b><b id="total">{{ $fmt($total) }}</b></div></div>

  <p class="phrase">Le patient reconnaît avoir eu la possibilité du choix de son traitement.</p>

  <div class="signatures">
    <span>Signature du Patient</span>
    <span>Signature du médecin dentiste</span>
  </div>

  <div class="pied">{{ $cabinet['nom'] ?? '' }}@if(!empty($cabinet['adresse'])) · {{ $cabinet['adresse'] }}@endif @if(!empty($cabinet['telephone'])) · Tél. {{ $cabinet['telephone'] }}@endif · Dossier n° {{ $dossier->numero }}</div>
</div>

<script>
  const corps = document.getElementById('lignes');
  const montant = (t) => parseFloat(String(t).replace(/[\s  ]/g, '').replace(',', '.')) || 0;
  const format = (v) => v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/[  ]/g, ' ');

  function actualiser() {
    let total = 0;
    const choisies = new Set();
    corps.querySelectorAll('tr').forEach((tr) => {
      total += montant(tr.querySelector('.prix').innerText);
      (tr.querySelector('.dents').innerText.match(/\d{2}/g) || []).forEach((d) => choisies.add(d));
    });
    document.getElementById('total').textContent = format(total);
    document.querySelectorAll('.dent').forEach((g) => g.classList.toggle('choisie', choisies.has(g.dataset.dent)));
  }
  function retirer(btn) { btn.closest('tr').remove(); actualiser(); }
  function ajouterLigne() {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td contenteditable></td><td class="dents" contenteditable></td><td class="prix" contenteditable>0,00</td>'
      + '<td class="suppr"><button type="button" title="Retirer la ligne" onclick="retirer(this)">×</button></td>';
    corps.appendChild(tr);
    tr.querySelector('td').focus();
  }
  // montant reformaté quand on quitte la case
  corps.addEventListener('focusout', (e) => { if (e.target.classList?.contains('prix')) e.target.innerText = format(montant(e.target.innerText)); actualiser(); });
  corps.addEventListener('input', actualiser);
  actualiser();
</script>
</body>
</html>

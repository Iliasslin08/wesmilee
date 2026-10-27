<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&display=swap">
<title>Carte patient · {{ $patient->nom_complet }}</title>
<style>
  @page { size: 85.6mm 54mm; margin: 0; }
  * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #e9eef0; }
  .actions { padding: 14px; text-align: center; font-family: Arial, sans-serif; }
  .actions button { padding: 10px 18px; border: 0; border-radius: 6px; font-weight: bold; cursor: pointer; background: #04a3b5; color: #fff; }
  .actions p { color: #555; font-size: 13px; margin: 8px 0 0; }
  /* Carte au format carte bancaire (CR80) */
  .carte { position: relative; width: 85.6mm; height: 54mm; margin: 10px auto; overflow: hidden; border-radius: 3mm;
    background: url('/images/carte-patient-fond.jpg?v={{ filemtime(public_path('images/carte-patient-fond.jpg')) }}') center / 100% 100% no-repeat; box-shadow: 0 4px 18px rgba(0,0,0,.18); }
  /* QR sans cadre, couleur turquoise */
  .qr { position: absolute; left: 59.3%; top: 35%; width: 27.4%; height: 45.6%; padding: 0; display: flex; align-items: center; justify-content: center; }
  .qr svg { width: 100%; height: 100%; display: block; }
  .qr svg rect { fill: transparent; }
  .qr svg path { fill: #048ea7; }
  /* Photo grande, sans cadre, fond enlevé à l'envoi (PNG transparent) */
  .photo { position: absolute; left: 3%; top: 4%; width: 45%; height: 82%; }
  .photo img { width: 100%; height: 100%; object-fit: cover; object-position: center top;
    -webkit-mask-image: linear-gradient(to bottom, #000 80%, transparent 100%); mask-image: linear-gradient(to bottom, #000 80%, transparent 100%); }
  .photo .vide { position: absolute; inset: 20% 15%; display: flex; align-items: center; justify-content: center; font-size: 2mm; color: #6b8189; text-align: center; border: 0.3mm dashed #9fb8be; border-radius: 1.5mm; }
  /* N° juste après « N° : » du fond, même taille ; on cache le trait */
  .num { position: absolute; left: 14.2%; bottom: 3.8%; font-size: 4.6mm; line-height: 1; font-weight: 800; color: #048ea7; letter-spacing: 0.2mm; }
  .nom { position: absolute; left: 47%; width: 44%; bottom: 3.6%; text-align: center; font-size: 4mm; line-height: 1.1; font-weight: 800; color: #048ea7; text-transform: uppercase; white-space: nowrap; }
  @media print { body { background: none; } .actions { display: none; } .carte { margin: 0; box-shadow: none; border-radius: 0; } }
</style>
</head>
<body>
<div class="actions">
  <button onclick="window.print()">Imprimer la carte</button>
  @if (preg_match('#//(localhost|127\.0\.0\.1)#', $lien))
    <p style="color:#b3261e;font-weight:bold">Attention : le QR code pointe vers « {{ $lien }} », qui ne marche pas sur le téléphone du patient.<br>
    Mettez l'adresse du PC dans le fichier .env : CARTE_URL=http://ADRESSE-IP-DU-PC:8000 (ou l'adresse en ligne), puis réimprimez.</p>
  @else
    <p style="color:#0b5f6b">QR code : {{ $lien }}</p>
  @endif
  <p>Format carte bancaire 85,6 × 54 mm · le QR code ouvre la page « Mon dossier » du patient (code : 4 derniers chiffres de son téléphone).</p>
</div>
<div class="carte">
  <div class="qr">{!! $qr !!}</div>
  <div class="photo">
    @if ($photo)
      <img src="{{ route('documents.show', $photo) }}" alt="Photo">
    @else
      <div class="vide">Photo à ajouter<br>(onglet Carte patient)</div>
    @endif
  </div>
  <div class="num">{{ $dossier?->numero }}</div>
  <div class="nom" id="nom">{{ $patient->nom_complet }}</div>
</div>
<script>
  // Nom long : on réduit la taille pour qu'il tienne sur une ligne
  const n = document.getElementById('nom'); let t = 4;
  while (n.scrollWidth > n.clientWidth && t > 2) { t -= 0.1; n.style.fontSize = t + 'mm'; }
</script>
@if (request('auto'))<script>window.onload = () => setTimeout(() => window.print(), 300)</script>@endif
</body>
</html>

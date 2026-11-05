{{-- Style commun du site patient (Mon espace, connexion, carte QR) --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap">
<style>
  :root { --p: #048ea7; --p2: #0b6f8a; --f: #0b3b44; --t: #12303a; --m: #5f777e; --bord: #dbe9eb; --fond: #f4f9fa; --clair: #e8f5f7; }
  * { box-sizing: border-box; }
  html { scroll-behavior: smooth; }
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: var(--fond); color: var(--t); font-size: 15px; line-height: 1.5; }
  a { color: var(--p); }
  .conteneur { max-width: 1160px; margin: 0 auto; padding: 0 20px; }
  /* barre du haut */
  .nav { position: sticky; top: 0; z-index: 20; background: rgba(255,255,255,.96); backdrop-filter: blur(8px); border-bottom: 1px solid var(--bord); }
  .nav .conteneur { display: flex; align-items: center; gap: 22px; height: 70px; }
  .nav .logo img { height: 44px; display: block; }
  .nav .liens { display: flex; gap: 4px; margin-inline-start: auto; align-items: center; }
  .nav .liens a, .nav .liens button { color: var(--t); text-decoration: none; font: 500 14px Poppins, Arial; padding: 8px 12px; border-radius: 10px; border: 0; background: none; cursor: pointer; white-space: nowrap; }
  .nav .liens a:hover, .nav .liens button:hover { background: var(--clair); color: var(--p); }
  .nav .liens .cta { background: var(--p); color: #fff; } .nav .liens .cta:hover { background: var(--p2); color: #fff; }
  .nav .langue { border: 1.5px solid var(--bord) !important; font-weight: 700 !important; }
  .nav .burger { display: none; margin-inline-start: auto; background: none; border: 0; font-size: 26px; cursor: pointer; color: var(--f); }
  @media (max-width: 860px) {
    .nav .burger { display: block; }
    .nav .liens { display: none; position: absolute; top: 70px; inset-inline: 0; background: #fff; flex-direction: column; align-items: stretch; padding: 10px 16px 16px; border-bottom: 1px solid var(--bord); box-shadow: 0 12px 24px rgba(0,0,0,.08); }
    .nav .liens.ouvert { display: flex; }
  }
  /* bandeau */
  .bandeau { background: radial-gradient(1200px 400px at 85% -10%, #2bc0d1 0%, transparent 60%), linear-gradient(135deg, #04748a, #0b3b44); color: #fff; }
  .bandeau h1 { font-size: clamp(26px, 4vw, 42px); line-height: 1.15; margin: 0 0 12px; font-weight: 800; }
  .bandeau p { opacity: .9; margin: 0; }
  .badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.25); border-radius: 999px; padding: 5px 14px; font-size: 13px; font-weight: 600; }
  /* cartes */
  .carte { background: #fff; border: 1px solid var(--bord); border-radius: 18px; padding: 20px; box-shadow: 0 6px 24px rgba(11,59,68,.06); margin-bottom: 20px; break-inside: avoid; }
  h2 { font-size: 17px; margin: 0 0 12px; color: var(--f); display: flex; align-items: center; gap: 8px; }
  h2 .ic { width: 34px; height: 34px; border-radius: 10px; background: var(--clair); display: inline-flex; align-items: center; justify-content: center; font-size: 17px; }
  .muted { color: var(--m); font-size: 13px; }
  .btn { display: inline-block; width: 100%; margin-top: 14px; padding: 13px; font: 700 15px Poppins, Arial; color: #fff; background: var(--p); border: 0; border-radius: 12px; cursor: pointer; text-align: center; text-decoration: none; }
  .btn:hover { background: var(--p2); } .btn:disabled { opacity: .45; }
  label { display: block; font-size: 13px; font-weight: 600; color: #48616a; margin: 12px 0 6px; }
  input, textarea { width: 100%; font: 15px Poppins, Arial; padding: 11px 13px; border: 1.5px solid #cfe2e5; border-radius: 11px; background: #fff; }
  input:focus, textarea:focus { outline: none; border-color: var(--p); box-shadow: 0 0 0 3px rgba(4,142,167,.12); }
  .err { background: #fdecea; color: #b3261e; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 10px; }
  .ok { background: #e3f4d6; color: #2c5f0e; border-radius: 12px; padding: 12px 14px; font-weight: 600; margin-bottom: 16px; }
  ul { list-style: none; padding: 0; margin: 0; } li { padding: 10px 0; border-bottom: 1px solid #edf4f5; } li:last-child { border: 0; }
  .pill { float: inline-end; border-radius: 20px; padding: 2px 11px; font-size: 12px; font-weight: 600; }
  .p-ok { background: #e3f4d6; color: #3d7a12; } .p-cours { background: #dff4f6; color: #037786; } .p-att { background: #fff1c9; color: #7a5a00; }
  .infos { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 14px; margin: 0; } .infos dt { color: var(--m); } .infos dd { margin: 0; font-weight: 600; }
  .totaux { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center; }
  .totaux div { background: var(--fond); border-radius: 12px; padding: 10px 4px; font-size: 13px; color: var(--m); } .totaux b { display: block; font-size: 18px; color: var(--t); }
  .reste b { color: #b3261e !important; } .solde b { color: #3d7a12 !important; }
  details summary { cursor: pointer; font-weight: 600; color: var(--f); }
  /* pied de page */
  .pied { background: var(--f); color: #cfe3e6; margin-top: 40px; padding: 34px 0 22px; font-size: 14px; }
  .pied .cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; }
  .pied b { color: #fff; display: block; margin-bottom: 6px; } .pied a { color: #9fe3ea; }
  .pied .bas { border-top: 1px solid rgba(255,255,255,.12); margin-top: 22px; padding-top: 14px; font-size: 12px; opacity: .75; text-align: center; }
</style>

@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); $AR = \App\Support\Langue::ar(); @endphp
<!DOCTYPE html>
<html lang="{{ $AR ? 'ar' : 'fr' }}" dir="{{ $AR ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t('Bientôt disponible', 'قريباً') }} · We Smile</title>
<style>
  body { margin: 0; font-family: 'Poppins', Arial, sans-serif; background: #f3f9f9; color: #12303a; display: flex; min-height: 100vh; align-items: center; justify-content: center; }
  .box { background: #fff; border-radius: 16px; padding: 28px 24px; width: min(360px, 92vw); text-align: center; box-shadow: 0 8px 30px rgba(11,95,107,.12); }
  img { height: 90px; } h1 { font-size: 20px; color: #0b5f6b; margin: 10px 0 4px; } p { color: #5b737a; font-size: 14px; }
</style>
</head>
<body>
@include('partials.langue')
<div class="box">
  <img src="/images/logo-wesmile.png" alt="We Smile">
  <h1>{{ $t('Bientôt disponible', 'قريباً') }}</h1>
  <p>{{ $t('Votre espace patient en ligne sera bientôt ouvert. Pour toute information, adressez-vous à l’accueil du centre.', 'سيتم قريباً فتح فضائكم الخاص عبر الإنترنت. لأي معلومة، يرجى التوجه إلى استقبال المركز.') }}</p>
</div>
</body>
</html>

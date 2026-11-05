{{-- Barre du haut du site patient. $liens = [[url, texte], …] ; $quitter = true pour le bouton « Quitter ». --}}
@php $AR = \App\Support\Langue::ar(); @endphp
<header class="nav">
  <div class="conteneur">
    <a class="logo" href="{{ route('espace.accueil') }}"><img src="/images/logo-wesmile.png" alt="We Smile"></a>
    <button class="burger" type="button" aria-label="Menu" onclick="document.getElementById('liens').classList.toggle('ouvert')">☰</button>
    <nav class="liens" id="liens">
      @foreach ($liens ?? [] as [$url, $texte])
        <a href="{{ $url }}" onclick="document.getElementById('liens').classList.remove('ouvert')">{{ $texte }}</a>
      @endforeach
      @if (!empty($cabinet['telephone']))<a href="tel:{{ preg_replace('/\s+/', '', $cabinet['telephone']) }}">📞 {{ $cabinet['telephone'] }}</a>@endif
      <a class="langue" href="{{ route('langue', $AR ? 'fr' : 'ar') }}">{{ $AR ? 'Français' : 'العربية' }}</a>
      @if (!empty($quitter))
        <form method="post" action="{{ route('espace.deconnexion') }}" style="margin:0">@csrf<button class="cta" type="submit">{{ \App\Support\Langue::t('Quitter', 'خروج') }}</button></form>
      @endif
    </nav>
  </div>
</header>

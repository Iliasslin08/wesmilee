{{-- Pied de page du site patient --}}
@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); @endphp
<footer class="pied">
  <div class="conteneur">
    <div class="cols">
      <div><b>{{ $cabinet['nom'] ?? 'Wesmile Center' }}</b>{{ $t('Centre dentaire', 'مركز طب الأسنان') }} · {{ $cabinet['ville'] ?? 'Rabat' }}@if (!empty($cabinet['slogan']))<br>🦷 {{ $t($cabinet['slogan'], $cabinet['slogan'] === 'Urgence dentaire 24h/24' ? 'طوارئ الأسنان 24/24' : $cabinet['slogan']) }}@endif</div>
      <div><b>{{ $t('Adresse', 'العنوان') }}</b>{{ $cabinet['adresse'] ?? '' }}@if (!empty($cabinet['ville'])), {{ $cabinet['ville'] }}@endif</div>
      <div><b>{{ $t('Contact', 'اتصال') }}</b>
        @if (!empty($cabinet['telephone']))<a href="tel:{{ preg_replace('/\s+/', '', $cabinet['telephone']) }}">{{ $cabinet['telephone'] }}</a><br>@endif
        @if (!empty($cabinet['whatsapp']))WhatsApp : {{ $cabinet['whatsapp'] }}<br>@endif
        @if (!empty($cabinet['site']))<a href="https://{{ preg_replace('#^https?://#', '', $cabinet['site']) }}">{{ $cabinet['site'] }}</a>@endif
      </div>
    </div>
    <div class="bas">{{ $t('Vos informations sont confidentielles (loi 09-08). Ne partagez pas votre mot de passe ni votre carte.', 'معلوماتكم سرية (القانون 09-08). لا تشاركوا كلمة السر ولا البطاقة.') }}</div>
  </div>
</footer>

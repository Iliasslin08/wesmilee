@php $t = fn ($fr, $ar) => \App\Support\Langue::t($fr, $ar); @endphp
{{-- Commentaire du patient (carte QR et « Mon espace ») : seul le chef le lit. --}}
<div class="{{ $classe ?? 'carte' }}" id="avis">
  <h2>{{ $t('Un commentaire pour nous ?', 'هل لديكم ملاحظة؟') }}</h2>
  @if (session('avis_ok'))
    <div style="background:#e3f4d6;color:#2c5f0e;border-radius:10px;padding:10px 12px;font-weight:600">✓ {{ session('avis_ok') }}</div>
  @else
    @if ($errors->has('avis') || $errors->has('commentaire'))
      <div style="background:#fdecea;color:#b3261e;border-radius:10px;padding:8px 12px;font-size:14px;margin-bottom:8px">{{ $errors->first('avis') ?: $errors->first('commentaire') }}</div>
    @endif
    <form method="post" action="{{ route('espace.avis') }}">
      @csrf
      <textarea name="commentaire" rows="3" maxlength="1000" required placeholder="{{ $t('Votre remarque, votre avis, une suggestion…', 'ملاحظتكم، رأيكم، اقتراح…') }}"
        style="width:100%;font:15px 'Poppins',Arial;padding:10px 12px;border:1.5px solid #cfe2e5;border-radius:10px;resize:vertical">{{ old('commentaire') }}</textarea>
      <button style="width:100%;margin-top:10px;padding:12px;font:700 15px 'Poppins',Arial;color:#fff;background:#048ea7;border:0;border-radius:12px;cursor:pointer">{{ $t('Envoyer', 'إرسال') }}</button>
      <div style="font-size:12px;color:#6b8189;margin-top:6px;text-align:center">{{ $t('Votre commentaire est lu par la direction du centre uniquement.', 'تقرأ ملاحظتكم إدارة المركز فقط.') }}</div>
    </form>
  @endif
</div>

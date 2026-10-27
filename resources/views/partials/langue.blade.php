{{-- Bouton Français / العربية (pages patient) --}}
<a href="{{ route('langue', \App\Support\Langue::ar() ? 'fr' : 'ar') }}"
   style="position:absolute;top:12px;{{ \App\Support\Langue::ar() ? 'left' : 'right' }}:12px;z-index:5;background:rgba(255,255,255,.92);color:#0b5f6b;border-radius:999px;padding:6px 14px;font:700 14px 'Poppins',Arial,sans-serif;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.12)">
  {{ \App\Support\Langue::ar() ? 'Français' : 'العربية' }}
</a>

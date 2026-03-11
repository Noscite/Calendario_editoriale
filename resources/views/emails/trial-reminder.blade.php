<x-mail::message>
# Ciao {{ $user->full_name ?? 'utente' }},

@if($daysRemaining === 1)
il tuo periodo di prova su **Kalendarium** scade **domani**.
@else
il tuo periodo di prova su **Kalendarium** scade tra **{{ $daysRemaining }} giorni**.
@endif

Per continuare a usare tutte le funzionalità — generazione calendari AI, pubblicazione automatica, immagini — attiva un piano a pagamento.

<x-mail::button :url="config('services.frontend_url') . '/settings'" color="primary">
Attiva il tuo piano
</x-mail::button>

Se hai domande, rispondi a questa email o usa la chat di supporto nella piattaforma.

Grazie,<br>
Il team Kalendarium
</x-mail::message>

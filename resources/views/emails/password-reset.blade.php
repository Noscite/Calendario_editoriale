<x-mail::message>
# Ciao {{ $user->full_name ?? 'utente' }},

Hai richiesto il reset della password per il tuo account Kalendarium.

Clicca il pulsante qui sotto per reimpostare la password. Il link è valido per **60 minuti**.

<x-mail::button :url="config('services.frontend_url') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email)" color="primary">
Reimposta password
</x-mail::button>

Se non hai richiesto il reset della password, ignora questa email. Il tuo account è al sicuro.

Grazie,<br>
Il team Kalendarium
</x-mail::message>

<x-mail::message>
# Sei stato invitato su Kalendarium

**{{ $invitation->inviter?->full_name ?? 'Un collega' }}** ti ha invitato a collaborare nell'organizzazione **{{ $invitation->organization?->name }}** su Kalendarium.

**Ruolo assegnato:** {{ ucfirst($invitation->role) }}

L'invito è valido per **7 giorni**. Clicca il pulsante per accettare e creare il tuo account.

<x-mail::button :url="config('services.frontend_url') . '/invitation/' . $invitation->token" color="primary">
Accetta invito
</x-mail::button>

Se non conosci chi ti ha invitato, puoi ignorare questa email.

Grazie,<br>
Il team Kalendarium
</x-mail::message>

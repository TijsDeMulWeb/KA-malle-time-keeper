<x-mail::message>
# Hallo {{ $user->name }}

Je bent uitgenodigd voor **{{ config('app.name') }}**. Hier hou je bij hoeveel tijd je aan je projecten besteedt.

Klik op de knop om je wachtwoord in te stellen en je account te activeren.

<x-mail::button :url="$url">
Account activeren
</x-mail::button>

Deze link is {{ $expiresInDays }} dagen geldig. Is hij verlopen? Vraag dan een nieuwe uitnodiging aan je leerkracht.

Groeten,<br>
{{ config('app.name') }}
</x-mail::message>

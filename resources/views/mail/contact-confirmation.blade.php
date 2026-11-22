{{-- E-mail de texto simples: sem escape HTML --}}
{!! __('mail.greeting', ['name' => $msg->name], $msg->locale) !!}

{!! __('mail.confirm_body', [], $msg->locale) !!}

{!! __('mail.your_message', [], $msg->locale) !!}
----
{!! $msg->message !!}
----

{!! __('mail.signature', [], $msg->locale) !!}
GEOFLORESTA - https://geofloresta.com

{{-- E-mail de texto simples: sem escape HTML (não há contexto HTML) --}}
Nova mensagem pelo formulário de contacto de geofloresta.com

Nome: {!! $msg->name !!}
E-mail: {!! $msg->email !!}
Organização: {!! $msg->organisation ?: '-' !!}
Assunto: {!! $msg->subject !!}
Idioma: {{ strtoupper($msg->locale) }}
Data: {{ $msg->created_at->timezone('Africa/Maputo')->format('d/m/Y H:i') }} (CAT)

{!! $msg->message !!}

-
Responda directamente a este e-mail. A mensagem está também gravada no painel (Mensagens de contacto, n.º {{ $msg->id }}).

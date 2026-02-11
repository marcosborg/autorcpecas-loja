<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Contacto CMS</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.45;">
    <h2 style="margin: 0 0 12px;">Novo pedido de contacto (CMS)</h2>

    <p style="margin: 0 0 10px;">
        <strong>Pagina:</strong> {{ $payload['page_title'] ?? '' }}<br>
        <strong>Slug:</strong> {{ $payload['page_slug'] ?? '' }}<br>
        <strong>URL:</strong> <a href="{{ $payload['page_url'] ?? '#' }}">{{ $payload['page_url'] ?? '' }}</a>
    </p>

    <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 14px 0;">

    <p style="margin: 0 0 10px;">
        <strong>Cliente:</strong> {{ $payload['customer_name'] ?? '' }}<br>
        <strong>Email:</strong> {{ $payload['customer_email'] ?? '' }}<br>
        <strong>Telefone:</strong> {{ $payload['customer_phone'] ?? '' }}
    </p>

    @if (!empty($payload['customer_message'] ?? ''))
        <p style="margin: 0 0 6px;"><strong>Mensagem:</strong></p>
        <p style="margin: 0; white-space: pre-wrap;">{{ $payload['customer_message'] }}</p>
    @endif
</body>
</html>


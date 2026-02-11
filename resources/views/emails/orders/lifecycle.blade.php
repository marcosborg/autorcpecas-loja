@php
    $appName = config('app.name', 'Auto RC Peças');
    $title = (string) ($context['title'] ?? 'Atualização da sua encomenda');
    $intro = trim((string) ($context['intro'] ?? ''));
    $highlight = trim((string) ($context['highlight'] ?? ''));
    $buttonLabel = trim((string) ($context['button_label'] ?? 'Ver encomenda'));
    $buttonUrl = trim((string) ($context['button_url'] ?? url('/loja/conta/encomendas/'.$order->id)));
    $rows = is_array($context['rows'] ?? null) ? $context['rows'] : [];
@endphp
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#111111;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f5f5;padding:20px 8px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e4e4e4;border-radius:10px;overflow:hidden;">
                <tr>
                    <td style="background:#700000;padding:18px 22px;color:#ffffff;">
                        <div style="font-size:20px;font-weight:700;line-height:1.2;">{{ $appName }}</div>
                        <div style="font-size:13px;opacity:.92;line-height:1.4;margin-top:4px;">Notificação de encomenda</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px;">
                        <h1 style="margin:0 0 12px;font-size:23px;line-height:1.25;color:#111111;">{{ $title }}</h1>

                        @if ($intro !== '')
                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#313131;">{{ $intro }}</p>
                        @endif

                        @if ($highlight !== '')
                            <div style="margin:0 0 14px;padding:12px 14px;border:1px solid #ecd7d7;background:#fff7f7;border-radius:8px;font-size:16px;font-weight:700;color:#700000;">
                                {{ $highlight }}
                            </div>
                        @endif

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin:8px 0 16px;">
                            @foreach ($rows as $row)
                                @php
                                    $label = trim((string) ($row['label'] ?? ''));
                                    $value = trim((string) ($row['value'] ?? ''));
                                @endphp
                                @if ($label !== '' && $value !== '')
                                    <tr>
                                        <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;font-size:14px;color:#606060;width:38%;">{{ $label }}</td>
                                        <td style="padding:7px 0;border-bottom:1px solid #f0f0f0;font-size:14px;color:#111111;font-weight:600;">{{ $value }}</td>
                                    </tr>
                                @endif
                            @endforeach
                        </table>

                        <div style="margin-top:18px;">
                            <a href="{{ $buttonUrl }}" style="display:inline-block;background:#700000;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:11px 18px;border-radius:8px;">
                                {{ $buttonLabel }}
                            </a>
                        </div>

                        <p style="margin:18px 0 0;font-size:12px;line-height:1.5;color:#7a7a7a;">
                            Se tiveres dúvidas, responde a este email ou contacta-nos em marketing@autorcpecas.pt.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:14px 22px;border-top:1px solid #efefef;background:#fafafa;font-size:12px;color:#808080;">
                        © {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>


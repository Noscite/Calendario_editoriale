<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abbonamento non attivo — Kalendarium</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            max-width: 520px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: #3DAFA8;
            padding: 2rem;
            text-align: center;
        }
        .header .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .header .icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        .body {
            padding: 2rem;
        }
        h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #222;
            margin-bottom: 0.75rem;
        }
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .status-box {
            background: #fff8f0;
            border: 1px solid #D4724A;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #a04020;
        }
        .billing-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .billing-table td {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .billing-table td:first-child {
            color: #777;
            width: 40%;
        }
        .billing-table td:last-child {
            font-weight: 600;
        }
        .cta {
            display: block;
            background: #D4724A;
            color: #fff;
            text-decoration: none;
            text-align: center;
            padding: 0.85rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.2s;
        }
        .cta:hover { background: #b85e3a; }
        .footer {
            padding: 1rem 2rem;
            background: #f8f9fa;
            text-align: center;
            font-size: 0.8rem;
            color: #999;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="logo">Kalendarium</div>
        </div>
        <div class="body">
            <h1>Il tuo abbonamento non è attivo</h1>

            @if($subscription)
                <div class="status-box">
                    Stato attuale:
                    <strong>
                        @php
                            $labels = [
                                'trial'           => 'Trial scaduto',
                                'pending_payment' => 'In attesa di pagamento',
                                'expired'         => 'Scaduto',
                                'cancelled'       => 'Cancellato',
                            ];
                        @endphp
                        {{ $labels[$subscription->status] ?? ucfirst($subscription->status) }}
                    </strong>
                </div>
            @endif

            <p>
                Kalendarium funziona con pagamento tramite <strong>bonifico bancario</strong>.
                Invia il pagamento usando i dati qui sotto e il tuo account verrà attivato entro 24 ore lavorative.
            </p>

            <table class="billing-table">
                <tr>
                    <td>Intestatario</td>
                    <td>{{ $billing['company_name'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td>IBAN</td>
                    <td>{{ $billing['iban'] ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Email</td>
                    <td>{{ $billing['company_email'] ?? '—' }}</td>
                </tr>
                @if($billing['company_phone'] ?? false)
                <tr>
                    <td>Telefono</td>
                    <td>{{ $billing['company_phone'] }}</td>
                </tr>
                @endif
            </table>

            <p>Dopo aver effettuato il bonifico, scrivici all'indirizzo email sopra indicando la tua organizzazione e il riferimento del pagamento.</p>

            <a href="mailto:{{ $billing['company_email'] ?? '' }}" class="cta">
                Contattaci per attivare l'abbonamento
            </a>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Noscite srl — Kalendarium
        </div>
    </div>
</body>
</html>

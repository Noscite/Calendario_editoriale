<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Abbonamento attivato — Kalendarium</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,0.07);">

        <!-- Header -->
        <tr>
          <td style="background:#3DAFA8;padding:32px;text-align:center;">
            <div style="font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">Kalendarium</div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:40px 40px 32px;">
            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#1a1a2e;">Il tuo abbonamento è attivo!</h1>
            <p style="margin:0 0 16px;font-size:15px;color:#555;line-height:1.6;">
              Ottimo! L'abbonamento di <strong>{{ $organization->name }}</strong> è stato attivato con successo.
              Puoi ora accedere a tutte le funzionalità di Kalendarium.
            </p>
            <table width="100%" cellpadding="12" cellspacing="0" style="background:#f0faf9;border:1px solid #c8ebe9;border-radius:8px;margin-bottom:24px;font-size:14px;color:#444;border-collapse:collapse;">
              <tr style="border-bottom:1px solid #c8ebe9;">
                <td style="padding:10px 12px;color:#555;width:45%;">Periodo</td>
                <td style="padding:10px 12px;font-weight:600;">{{ $subscription->paid_period_months }} mes{{ $subscription->paid_period_months === 1 ? 'e' : 'i' }}</td>
              </tr>
              <tr style="border-bottom:1px solid #c8ebe9;">
                <td style="padding:10px 12px;color:#555;">Scadenza</td>
                <td style="padding:10px 12px;font-weight:600;">{{ $subscription->paid_period_ends_at?->format('d/m/Y') }}</td>
              </tr>
              <tr>
                <td style="padding:10px 12px;color:#555;">Riferimento</td>
                <td style="padding:10px 12px;font-weight:600;font-family:monospace;">{{ $subscription->payment_reference }}</td>
              </tr>
            </table>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#3DAFA8;border-radius:8px;">
                  <a href="{{ config('app.frontend_url', config('app.url')) }}" style="display:block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
                    Accedi a Kalendarium →
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;border-top:1px solid #eee;text-align:center;font-size:12px;color:#999;">
            Per assistenza: <a href="mailto:{{ config('billing.company_email') }}" style="color:#3DAFA8;">{{ config('billing.company_email') }}</a><br>
            &copy; {{ date('Y') }} Noscite srl — Kalendarium
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>

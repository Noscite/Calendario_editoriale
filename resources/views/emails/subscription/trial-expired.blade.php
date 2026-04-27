<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Il tuo trial è scaduto — Kalendarium</title>
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
            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#1a1a2e;">Il tuo periodo di prova è scaduto</h1>
            <p style="margin:0 0 16px;font-size:15px;color:#555;line-height:1.6;">
              Il trial gratuito di <strong>{{ $organization->name }}</strong> è terminato.
              Per continuare a usare Kalendarium è necessario attivare un abbonamento.
            </p>
            <table width="100%" cellpadding="12" cellspacing="0" style="background:#fff8f0;border:1px solid #D4724A;border-radius:8px;margin-bottom:24px;">
              <tr>
                <td style="font-size:14px;color:#a04020;">
                  <strong>Come attivare il tuo abbonamento</strong><br>
                  Effettua un bonifico bancario all'IBAN indicato di seguito e scrivici all'email con il riferimento del pagamento.
                </td>
              </tr>
            </table>
            <table width="100%" cellpadding="8" cellspacing="0" style="margin-bottom:24px;font-size:14px;color:#444;border-collapse:collapse;">
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px 0;color:#777;width:40%;">Intestatario</td>
                <td style="padding:8px 0;font-weight:600;">{{ config('billing.company_name') }}</td>
              </tr>
              <tr style="border-bottom:1px solid #eee;">
                <td style="padding:8px 0;color:#777;">IBAN</td>
                <td style="padding:8px 0;font-weight:600;font-family:monospace;">{{ config('billing.iban') }}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;color:#777;">Email</td>
                <td style="padding:8px 0;font-weight:600;">{{ config('billing.company_email') }}</td>
              </tr>
            </table>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="background:#D4724A;border-radius:8px;">
                  <a href="mailto:{{ config('billing.company_email') }}" style="display:block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">
                    Contattaci per attivare
                  </a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="background:#f8f9fa;padding:20px 40px;border-top:1px solid #eee;text-align:center;font-size:12px;color:#999;">
            &copy; {{ date('Y') }} Noscite srl — Kalendarium
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>

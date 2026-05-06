import { Link } from 'react-router-dom';
import { ArrowLeft, Shield } from 'lucide-react';

export default function Privacy() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8]">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <Link
          to="/"
          className="inline-flex items-center gap-2 text-white/80 hover:text-white mb-8"
        >
          <ArrowLeft className="w-4 h-4" />
          Torna alla home
        </Link>

        <div className="bg-white rounded-2xl shadow-xl p-8">
          <div className="flex items-center gap-3 mb-6">
            <Shield className="w-8 h-8 text-[#3DAFA8]" />
            <h1 className="text-3xl font-bold text-[#2C3E50]">Privacy Policy</h1>
          </div>

          <p className="text-sm text-gray-500 mb-8">Ultimo aggiornamento: 6 maggio 2026</p>

          <div className="prose max-w-none text-gray-700 space-y-8">

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">1. Titolare del trattamento</h2>
              <p>
                <strong>Noscite SRLS</strong> — Via Monte Grappa 13, Corsico (MI) 20094 — P.IVA 14385240966<br />
                Email: <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a>
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">2. Tipologia di dati raccolti</h2>
              <ul className="list-disc pl-6 space-y-1">
                <li><strong>Dati di registrazione</strong>: email, password, nome, ragione sociale aziendale.</li>
                <li><strong>Dati di profilo brand</strong>: nome brand, settore, descrizione, tone of voice, voice examples.</li>
                <li><strong>Dati di utilizzo</strong>: log di accesso, IP, browser, azioni eseguite sulla piattaforma.</li>
                <li><strong>Dati delle recensioni Google</strong>: testi delle review, autori, valutazioni (recuperati via Google Business Profile API previa autorizzazione esplicita del cliente).</li>
                <li><strong>Dati dei post social</strong>: testi e immagini generati per Facebook, Instagram, LinkedIn, Google Business Profile.</li>
                <li><strong>Token OAuth</strong>: token di accesso a Facebook, Instagram, LinkedIn, Google (criptati a riposo).</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">3. Finalità del trattamento</h2>
              <ul className="list-disc pl-6 space-y-1">
                <li><strong>Erogazione del servizio</strong> — base giuridica: contratto (art. 6.1.b GDPR).</li>
                <li><strong>Comunicazioni transazionali</strong> (registrazione, reset password, notifiche di sistema) — base giuridica: contratto.</li>
                <li><strong>Comunicazioni marketing</strong> (newsletter, promozioni) — base giuridica: consenso esplicito (art. 6.1.a GDPR), revocabile in qualsiasi momento.</li>
                <li><strong>Sicurezza e prevenzione frodi</strong> — base giuridica: legittimo interesse (art. 6.1.f GDPR).</li>
                <li><strong>Adempimenti fiscali e contabili</strong> — base giuridica: obbligo legale (art. 6.1.c GDPR).</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">4. Conservazione dei dati</h2>
              <ul className="list-disc pl-6 space-y-1">
                <li><strong>Account attivo</strong>: per tutta la durata del rapporto contrattuale.</li>
                <li><strong>Dopo cancellazione account</strong>: 90 giorni di grace period, poi soft-delete completo.</li>
                <li><strong>Dati fiscali (fatture)</strong>: 10 anni come da normativa italiana.</li>
                <li><strong>Log di sicurezza</strong>: 12 mesi.</li>
                <li><strong>Email marketing dopo revoca consenso</strong>: cancellazione entro 30 giorni.</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">5. Comunicazione dei dati a terzi (sub-processor)</h2>
              <p>
                I dati possono essere trasmessi ai seguenti sub-processor con cui Noscite SRLS ha
                stipulato contratti DPA conformi GDPR:
              </p>

              <div className="overflow-x-auto mt-4">
                <table className="w-full text-sm border border-gray-200 rounded-lg">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Fornitore</th>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Sede</th>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Finalità</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">Anthropic, PBC</td>
                      <td className="px-4 py-2 border-b border-gray-100">USA (SCC)</td>
                      <td className="px-4 py-2 border-b border-gray-100">Claude API per scoring/reply review e generazione contenuti. DPA firmato.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">OpenAI, LLC</td>
                      <td className="px-4 py-2 border-b border-gray-100">USA (SCC)</td>
                      <td className="px-4 py-2 border-b border-gray-100">GPT-4o mini, embeddings text-embedding-3-small. DPA firmato.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">Google Ireland Ltd</td>
                      <td className="px-4 py-2 border-b border-gray-100">Irlanda</td>
                      <td className="px-4 py-2 border-b border-gray-100">Google Business Profile API per gestione recensioni e profilo del cliente.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">Meta Platforms Ireland Ltd</td>
                      <td className="px-4 py-2 border-b border-gray-100">Irlanda</td>
                      <td className="px-4 py-2 border-b border-gray-100">Facebook + Instagram API per pubblicazione contenuti.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">LinkedIn Ireland Unlimited Co.</td>
                      <td className="px-4 py-2 border-b border-gray-100">Irlanda</td>
                      <td className="px-4 py-2 border-b border-gray-100">LinkedIn API per pubblicazione su pagine LinkedIn.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">Sendinblue SAS / Brevo</td>
                      <td className="px-4 py-2 border-b border-gray-100">Francia</td>
                      <td className="px-4 py-2 border-b border-gray-100">Email transazionali e marketing (marketing solo previo consenso).</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">OVH SAS</td>
                      <td className="px-4 py-2 border-b border-gray-100">Francia</td>
                      <td className="px-4 py-2 border-b border-gray-100">Hosting VPS in datacenter europeo. Database PostgreSQL gestito da Noscite SRLS sul VPS (non sub-processor esterno).</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100">ElevenLabs Inc</td>
                      <td className="px-4 py-2 border-b border-gray-100">USA (SCC previsto)</td>
                      <td className="px-4 py-2 border-b border-gray-100">Voice profiling per onboarding brand. <strong>Attualmente non utilizzato</strong>: predisposto per attivazione futura, con preavviso e consenso quando disponibile.</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2">Stripe Payments Europe Ltd</td>
                      <td className="px-4 py-2">Irlanda</td>
                      <td className="px-4 py-2">Processamento pagamenti. <strong>Attualmente in fase di integrazione, non ancora attivo</strong>; verrà attivato con preavviso al cliente.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p className="mt-3">
                Per i sub-processor extra-UE (Anthropic, OpenAI), i trasferimenti avvengono sulla
                base delle <strong>Clausole Contrattuali Standard (SCC)</strong> approvate dalla
                Commissione Europea.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">6. Diritti dell&apos;utente (art. 15-22 GDPR)</h2>
              <ul className="list-disc pl-6 space-y-1">
                <li>Accesso ai propri dati</li>
                <li>Rettifica</li>
                <li>Cancellazione (right to be forgotten)</li>
                <li>Limitazione del trattamento</li>
                <li>Portabilità</li>
                <li>Opposizione</li>
                <li>Reclamo al Garante della Privacy (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener noreferrer" className="text-[#3DAFA8] hover:underline">www.garanteprivacy.it</a>)</li>
              </ul>
              <p className="mt-3">
                Per esercitare questi diritti, contatta <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a>.
                Risposta entro 30 giorni.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">7. Cookie</h2>
              <p>
                Vedi la nostra <Link to="/cookies" className="text-[#3DAFA8] hover:underline">Cookie Policy</Link> completa.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">8. Minori</h2>
              <p>
                Il servizio è riservato a soggetti di età &gt;= 18 anni con partita IVA o
                rappresentanti di società. Non raccogliamo dati di minori.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">9. Sicurezza</h2>
              <ul className="list-disc pl-6 space-y-1">
                <li>Connessioni cifrate (HTTPS/TLS 1.3)</li>
                <li>Password hashate con bcrypt</li>
                <li>Token OAuth criptati a riposo</li>
                <li>Backup giornalieri criptati</li>
                <li>Accessi tracciati e auditati</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">10. Modifiche alla Privacy Policy</h2>
              <p>
                In caso di modifiche sostanziali, gli utenti saranno notificati via email con almeno
                30 giorni di anticipo.
              </p>
            </section>

          </div>

          <div className="mt-8 pt-6 border-t text-sm text-gray-500">
            <p><strong>Noscite SRLS</strong> — P.IVA 14385240966 — Via Monte Grappa 13, Corsico (MI) 20094</p>
            <p><strong>Email:</strong> <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a></p>
          </div>
        </div>
      </div>
    </div>
  );
}

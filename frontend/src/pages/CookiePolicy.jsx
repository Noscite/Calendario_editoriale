import { Link } from 'react-router-dom';
import { ArrowLeft, Cookie } from 'lucide-react';

export default function CookiePolicy() {
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
            <Cookie className="w-8 h-8 text-[#3DAFA8]" />
            <h1 className="text-3xl font-bold text-[#2C3E50]">Cookie Policy</h1>
          </div>

          <p className="text-sm text-gray-500 mb-8">Ultimo aggiornamento: 6 maggio 2026</p>

          <div className="prose max-w-none text-gray-700 space-y-8">

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">Cosa sono i cookie</h2>
              <p>
                I cookie sono piccoli file di testo che i siti web visitati installano sul tuo dispositivo.
                Servono per far funzionare il sito o per migliorarne l&apos;utilizzo.
              </p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">Cookie utilizzati da Kalendarium</h2>

              <h3 className="text-lg font-semibold text-[#2C3E50] mt-6 mb-2">Cookie tecnici (necessari)</h3>
              <p>Sono indispensabili per il funzionamento del sito e non richiedono consenso.</p>

              <div className="overflow-x-auto mt-4">
                <table className="w-full text-sm border border-gray-200 rounded-lg">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Nome</th>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Provider</th>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Scopo</th>
                      <th className="text-left px-4 py-2 border-b border-gray-200 font-semibold text-[#2C3E50]">Durata</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100 font-mono text-xs">laravel_session</td>
                      <td className="px-4 py-2 border-b border-gray-100">Kalendarium</td>
                      <td className="px-4 py-2 border-b border-gray-100">Mantenere la sessione utente autenticata</td>
                      <td className="px-4 py-2 border-b border-gray-100">Sessione</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 border-b border-gray-100 font-mono text-xs">XSRF-TOKEN</td>
                      <td className="px-4 py-2 border-b border-gray-100">Kalendarium</td>
                      <td className="px-4 py-2 border-b border-gray-100">Protezione CSRF</td>
                      <td className="px-4 py-2 border-b border-gray-100">Sessione</td>
                    </tr>
                    <tr>
                      <td className="px-4 py-2 font-mono text-xs">cookie_consent</td>
                      <td className="px-4 py-2">Kalendarium</td>
                      <td className="px-4 py-2">Memorizza le tue preferenze cookie</td>
                      <td className="px-4 py-2">1 anno</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h3 className="text-lg font-semibold text-[#2C3E50] mt-6 mb-2">Cookie analytics (opzionali)</h3>
              <p>
                Attualmente Kalendarium NON utilizza cookie analytics di terze parti. Se in futuro verranno
                aggiunti (es. Google Analytics), saranno attivati solo previo tuo consenso esplicito.
              </p>

              <h3 className="text-lg font-semibold text-[#2C3E50] mt-6 mb-2">Cookie marketing (opzionali)</h3>
              <p>Kalendarium NON utilizza cookie di profilazione o marketing.</p>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">Come gestire i cookie</h2>
              <p>
                Puoi modificare le tue preferenze in qualsiasi momento cliccando sul link &quot;Preferenze
                cookie&quot; nel footer del sito.
              </p>
              <p className="mt-2">Puoi anche disabilitare i cookie direttamente dal tuo browser:</p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li>Chrome: Impostazioni → Privacy e sicurezza → Cookie</li>
                <li>Firefox: Impostazioni → Privacy e sicurezza</li>
                <li>Safari: Preferenze → Privacy</li>
              </ul>
            </section>

            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">Titolare del trattamento</h2>
              <p>
                Noscite SRLS — Via Monte Grappa 13, Corsico (MI) 20094 — P.IVA 14385240966<br />
                Email: <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a>
              </p>
            </section>

          </div>

          <div className="mt-8 pt-6 border-t text-sm text-gray-500">
            <p><strong>Noscite SRLS</strong> — P.IVA 14385240966</p>
            <p>Via Monte Grappa 13, Corsico (MI) 20094</p>
          </div>
        </div>
      </div>
    </div>
  );
}

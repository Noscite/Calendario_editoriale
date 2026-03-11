import { Link } from 'react-router-dom';
import { ArrowLeft, Trash2, Monitor, Mail, ShieldOff } from 'lucide-react';

export default function DataDeletion() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8]">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <Link
          to="/login"
          className="inline-flex items-center gap-2 text-white/80 hover:text-white mb-8"
        >
          <ArrowLeft className="w-4 h-4" />
          Torna alla home
        </Link>

        <div className="bg-white rounded-2xl shadow-xl p-8">
          <div className="flex items-center gap-3 mb-6">
            <Trash2 className="w-8 h-8 text-[#3DAFA8]" />
            <h1 className="text-3xl font-bold text-[#2C3E50]">Cancellazione dei Dati</h1>
          </div>

          <p className="text-sm text-gray-500 mb-8">Ultimo aggiornamento: 11 marzo 2026</p>

          <div className="prose max-w-none text-gray-700 space-y-8">

            {/* 1 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">1. Diritto alla Cancellazione dei Dati</h2>
              <p>
                Ai sensi del Regolamento (UE) 2016/679 (GDPR), l&apos;utente ha il diritto di richiedere
                in qualsiasi momento la cancellazione di tutti i dati personali raccolti tramite Noscite
                Calendar, inclusi i dati ottenuti attraverso l&apos;integrazione con Facebook e Instagram.
              </p>
              <p className="mt-2">
                Noscite SRLS si impegna a trattare ogni richiesta in modo tempestivo e trasparente,
                garantendo la completa rimozione delle informazioni personali dai propri sistemi nei tempi
                indicati di seguito.
              </p>
            </section>

            {/* 2 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">2. Cosa viene eliminato</h2>
              <p>La richiesta di cancellazione comporta l&apos;eliminazione definitiva dei seguenti dati:</p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li>Account utente e credenziali di accesso</li>
                <li>Dati del profilo (nome, email, informazioni aziendali)</li>
                <li>Brand e progetti editoriali creati sulla piattaforma</li>
                <li>Post generati e tutti i contenuti del calendario editoriale</li>
                <li>Token di accesso a Facebook, Instagram, LinkedIn e Google Business Profile</li>
                <li>Metriche e statistiche raccolte dalle piattaforme social</li>
                <li>Documenti brand caricati sulla piattaforma</li>
              </ul>
            </section>

            {/* 3 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">3. Come richiedere la cancellazione</h2>
              <p className="mb-4">Sono disponibili tre modalità per richiedere la cancellazione dei dati:</p>

              <div className="space-y-4">
                {/* Metodo 1 */}
                <div className="border border-gray-200 rounded-xl p-5 bg-gray-50">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-[#3DAFA8] text-white flex items-center justify-center font-bold text-sm shrink-0">1</div>
                    <Monitor className="w-5 h-5 text-[#2C3E50]" />
                    <h3 className="font-semibold text-[#2C3E50] text-lg">Dall&apos;app (più veloce)</h3>
                  </div>
                  <p>
                    Accedi a <a href="https://calendar.noscite.it" className="text-[#3DAFA8] hover:underline">calendar.noscite.it</a> →
                    Impostazioni → Account → <strong>&quot;Elimina account e tutti i dati&quot;</strong> → Conferma con la tua password.
                  </p>
                  <p className="mt-1 text-sm text-gray-500">L&apos;eliminazione sarà avviata immediatamente dopo la conferma.</p>
                </div>

                {/* Metodo 2 */}
                <div className="border border-gray-200 rounded-xl p-5 bg-gray-50">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-[#3DAFA8] text-white flex items-center justify-center font-bold text-sm shrink-0">2</div>
                    <Mail className="w-5 h-5 text-[#2C3E50]" />
                    <h3 className="font-semibold text-[#2C3E50] text-lg">Via email</h3>
                  </div>
                  <p>
                    Invia una email a <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a> con
                    oggetto <strong>&quot;Richiesta cancellazione dati&quot;</strong>, indicando l&apos;indirizzo email associato al tuo account.
                  </p>
                  <p className="mt-1 text-sm text-gray-500">Risponderemo entro 72 ore con conferma di presa in carico della richiesta.</p>
                </div>

                {/* Metodo 3 */}
                <div className="border border-gray-200 rounded-xl p-5 bg-gray-50">
                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-8 h-8 rounded-full bg-[#3DAFA8] text-white flex items-center justify-center font-bold text-sm shrink-0">3</div>
                    <ShieldOff className="w-5 h-5 text-[#2C3E50]" />
                    <h3 className="font-semibold text-[#2C3E50] text-lg">Revoca accesso da Facebook/Instagram</h3>
                  </div>
                  <p>
                    Vai su <strong>Facebook</strong> → Impostazioni → App e siti web → Noscite Calendar → <strong>Rimuovi</strong>.
                  </p>
                  <p className="mt-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    Attenzione: questa operazione revoca i permessi di accesso ai dati social ma <strong>non elimina</strong> i
                    dati del tuo account Noscite Calendar. Per l&apos;eliminazione completa di tutti i dati,
                    utilizza il Metodo 1 o il Metodo 2.
                  </p>
                </div>
              </div>
            </section>

            {/* 4 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">4. Tempi di elaborazione</h2>
              <ul className="list-none space-y-2 mt-2">
                <li className="flex items-start gap-2">
                  <span className="font-semibold text-[#2C3E50] shrink-0">Conferma della richiesta:</span>
                  <span>entro 72 ore dalla ricezione</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="font-semibold text-[#2C3E50] shrink-0">Eliminazione completa dai sistemi:</span>
                  <span>entro 30 giorni</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="font-semibold text-[#2C3E50] shrink-0">Eliminazione dai backup:</span>
                  <span>entro 90 giorni</span>
                </li>
              </ul>
              <p className="mt-3">
                Al completamento della procedura, riceverai una email di conferma all&apos;indirizzo
                associato al tuo account.
              </p>
            </section>

            {/* 5 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">5. Dati che non possono essere eliminati</h2>
              <p>In conformità alla normativa vigente, alcuni dati potrebbero non essere eliminabili:</p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li>
                  Dati richiesti per obblighi legali o fiscali (ad esempio le fatture, che devono essere
                  conservate per 10 anni ai sensi della legislazione italiana).
                </li>
                <li>
                  Dati anonimizzati e aggregati che non consentono in alcun modo l&apos;identificazione
                  dell&apos;utente.
                </li>
              </ul>
            </section>

            {/* 6 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">6. Contatti</h2>
              <p>Per qualsiasi domanda relativa alla cancellazione dei dati:</p>
              <ul className="list-none mt-2 space-y-1">
                <li><strong>Email:</strong> <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a></li>
                <li><strong>Titolare del trattamento:</strong> Noscite SRLS — P.IVA 14385240966</li>
              </ul>
            </section>

          </div>

          <div className="mt-8 pt-6 border-t text-sm text-gray-500">
            <p><strong>Noscite SRLS</strong> — P.IVA 14385240966</p>
            <p><strong>Email:</strong> privacy@noscite.it</p>
          </div>
        </div>
      </div>
    </div>
  );
}

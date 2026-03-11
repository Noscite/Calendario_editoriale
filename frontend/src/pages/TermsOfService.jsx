import { Link } from 'react-router-dom';
import { ArrowLeft, FileText } from 'lucide-react';

export default function TermsOfService() {
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
            <FileText className="w-8 h-8 text-[#3DAFA8]" />
            <h1 className="text-3xl font-bold text-[#2C3E50]">Termini di Servizio</h1>
          </div>

          <p className="text-sm text-gray-500 mb-8">Ultimo aggiornamento: 11 marzo 2026</p>

          <div className="prose max-w-none text-gray-700 space-y-8">

            {/* 1 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">1. Informazioni sul Servizio</h2>
              <p>
                Noscite Calendar (di seguito &quot;il Servizio&quot;) è una piattaforma SaaS accessibile
                all&apos;indirizzo <a href="https://calendar.noscite.it" className="text-[#3DAFA8] hover:underline">calendar.noscite.it</a> che
                consente di pianificare, generare e pubblicare contenuti per i social media attraverso
                strumenti di intelligenza artificiale, gestione editoriale e integrazioni con piattaforme
                terze.
              </p>
              <p className="mt-2">
                Il Servizio è gestito da <strong>Noscite SRLS</strong>, P.IVA 14385240966, con sede legale in
                Italia (di seguito &quot;Noscite&quot;, &quot;noi&quot; o &quot;la Società&quot;).
              </p>
              <p className="mt-2">
                Utilizzando il Servizio, l&apos;utente accetta integralmente i presenti Termini di Servizio.
                In caso di disaccordo, è pregato di non utilizzare la piattaforma.
              </p>
            </section>

            {/* 2 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">2. Accesso al Servizio e Account</h2>
              <p>
                Per accedere al Servizio è necessario creare un account fornendo un indirizzo email valido
                e una password sicura. L&apos;utente deve avere almeno 18 anni di età o l&apos;età minima
                prevista dalla legislazione del proprio Paese per stipulare un contratto.
              </p>
              <p className="mt-2">
                L&apos;utente è l&apos;unico responsabile della riservatezza delle proprie credenziali di
                accesso e di tutte le attività eseguite tramite il proprio account. È tenuto a comunicare
                tempestivamente a <a href="mailto:info@noscite.it" className="text-[#3DAFA8] hover:underline">info@noscite.it</a> qualsiasi
                uso non autorizzato o sospetta violazione della sicurezza del proprio account.
              </p>
              <p className="mt-2">
                Noscite si riserva il diritto di rifiutare la registrazione o di chiudere account che
                risultino in violazione dei presenti Termini.
              </p>
            </section>

            {/* 3 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">3. Piani e Pagamenti</h2>
              <p>Il Servizio è disponibile nei seguenti piani di abbonamento:</p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li><strong>Basic</strong> — € 29,00/mese</li>
                <li><strong>Standard</strong> — € 79,00/mese</li>
                <li><strong>Pro</strong> — € 199,00/mese</li>
              </ul>
              <p className="mt-2">
                Tutti i prezzi si intendono IVA esclusa, ove applicabile. Noscite può offrire un periodo
                di prova gratuita (trial) la cui durata è indicata al momento della registrazione. Al termine
                del trial, l&apos;abbonamento si rinnova automaticamente al piano selezionato, salvo
                cancellazione da parte dell&apos;utente prima della scadenza.
              </p>
              <p className="mt-2">
                Gli abbonamenti si rinnovano automaticamente alla scadenza di ciascun periodo di
                fatturazione. L&apos;utente può annullare il rinnovo in qualsiasi momento dalle impostazioni
                del proprio account; la cancellazione avrà effetto al termine del periodo già pagato.
              </p>
              <p className="mt-2">
                Non sono previsti rimborsi per periodi parzialmente utilizzati, salvo vizi o
                malfunzionamenti imputabili a Noscite e debitamente documentati.
              </p>
            </section>

            {/* 4 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">4. Utilizzo Accettabile</h2>
              <p>L&apos;utente si impegna a utilizzare il Servizio in modo lecito e conforme ai presenti Termini. In particolare, è vietato:</p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li>generare, distribuire o pubblicare contenuti illegali, diffamatori, osceni, discriminatori o che incitino alla violenza;</li>
                <li>utilizzare il Servizio per inviare messaggi indesiderati (spam) o comunicazioni commerciali non autorizzate;</li>
                <li>tentare di effettuare reverse engineering, decompilazione o disassemblaggio del software;</li>
                <li>abusare delle API del Servizio, inclusi tentativi di sovraccarico, scraping automatizzato o aggiramento dei limiti di utilizzo;</li>
                <li>violare diritti di proprietà intellettuale di terzi tramite i contenuti generati;</li>
                <li>condividere le credenziali del proprio account con terzi non autorizzati;</li>
                <li>utilizzare il Servizio per finalità che violino la normativa vigente, incluse le norme sulla protezione dei dati personali.</li>
              </ul>
              <p className="mt-2">
                La violazione di queste disposizioni può comportare la sospensione o la chiusura
                immediata dell&apos;account, senza diritto a rimborso.
              </p>
            </section>

            {/* 5 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">5. Integrazioni con Piattaforme Terze</h2>
              <p>
                Il Servizio consente l&apos;integrazione con piattaforme di terze parti, tra cui Meta
                (Instagram e Facebook), LinkedIn e Google Business Profile.
              </p>
              <p className="mt-2">
                Collegando il proprio account a tali piattaforme, l&apos;utente autorizza espressamente
                Noscite Calendar ad operare come applicazione di terze parti per suo conto, al fine di
                pubblicare contenuti, raccogliere metriche e gestire la presenza social dell&apos;utente
                nei limiti delle funzionalità offerte dal Servizio.
              </p>
              <p className="mt-2">
                Noscite non è responsabile per eventuali modifiche, limitazioni o interruzioni delle API
                messe a disposizione dalle piattaforme terze, né per eventuali conseguenze derivanti da
                tali variazioni sull&apos;operatività del Servizio.
              </p>
              <p className="mt-2">
                L&apos;utente è tenuto a rispettare i termini di servizio di ciascuna piattaforma
                collegata.
              </p>
            </section>

            {/* 6 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">6. Contenuti dell&apos;Utente</h2>
              <p>
                L&apos;utente mantiene la piena titolarità dei contenuti creati, caricati o pubblicati
                tramite il Servizio. Noscite non rivendica alcun diritto di proprietà su tali contenuti.
              </p>
              <p className="mt-2">
                Utilizzando il Servizio, l&apos;utente concede a Noscite una licenza limitata, non
                esclusiva e revocabile per elaborare, memorizzare e trasmettere i propri contenuti nella
                misura strettamente necessaria all&apos;erogazione del Servizio (ad esempio: generazione
                di testi tramite IA, programmazione della pubblicazione, analisi delle performance).
              </p>
              <p className="mt-2">
                L&apos;utente è l&apos;unico responsabile della liceità, accuratezza e conformità dei
                contenuti pubblicati tramite il Servizio. Noscite non effettua alcun controllo preventivo
                sui contenuti e non risponde di eventuali violazioni commesse dall&apos;utente.
              </p>
            </section>

            {/* 7 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">7. Dati Personali e Privacy</h2>
              <p>
                Il trattamento dei dati personali dell&apos;utente è regolato dalla nostra Privacy Policy,
                disponibile alla
                pagina <a href="https://noscite.it/privacy-policy" className="text-[#3DAFA8] hover:underline" target="_blank" rel="noopener noreferrer">noscite.it/privacy-policy</a>.
              </p>
              <p className="mt-2">
                Utilizzando il Servizio, l&apos;utente dichiara di aver letto e compreso l&apos;informativa
                sul trattamento dei dati personali ai sensi del Regolamento (UE) 2016/679 (GDPR).
              </p>
              <p className="mt-2">
                Per qualsiasi richiesta relativa al trattamento dei dati personali, è possibile contattare
                il titolare del trattamento all&apos;indirizzo <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a>.
              </p>
            </section>

            {/* 8 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">8. Limitazione di Responsabilità</h2>
              <p>
                Il Servizio è fornito &quot;così com&apos;è&quot; (<em>as is</em>) e &quot;come
                disponibile&quot; (<em>as available</em>). Noscite si impegna a garantire la massima
                continuità operativa, ma non garantisce un uptime del 100% né l&apos;assenza di errori o
                interruzioni.
              </p>
              <p className="mt-2">
                Nei limiti consentiti dalla legge applicabile, Noscite non sarà responsabile per:
              </p>
              <ul className="list-disc pl-6 mt-2 space-y-1">
                <li>perdite indirette, incidentali o consequenziali;</li>
                <li>mancati guadagni, perdita di dati o interruzioni dell&apos;attività;</li>
                <li>danni derivanti dall&apos;utilizzo o dall&apos;impossibilità di utilizzo del Servizio;</li>
                <li>azioni di terze parti, incluse le piattaforme social integrate.</li>
              </ul>
              <p className="mt-2">
                La responsabilità complessiva di Noscite nei confronti dell&apos;utente, per qualsiasi
                causa, non potrà in ogni caso eccedere l&apos;importo totale corrisposto dall&apos;utente
                nei dodici (12) mesi precedenti l&apos;evento che ha dato origine alla richiesta di
                risarcimento.
              </p>
            </section>

            {/* 9 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">9. Sospensione e Terminazione</h2>
              <p>
                Noscite si riserva il diritto di sospendere o terminare l&apos;accesso al Servizio, con o
                senza preavviso, nel caso in cui l&apos;utente violi i presenti Termini o utilizzi il
                Servizio in modo abusivo o fraudolento.
              </p>
              <p className="mt-2">
                L&apos;utente può cancellare il proprio account in qualsiasi momento tramite le
                impostazioni della piattaforma o inviando una richiesta a <a href="mailto:info@noscite.it" className="text-[#3DAFA8] hover:underline">info@noscite.it</a>.
              </p>
              <p className="mt-2">
                In caso di cancellazione dell&apos;account, i dati dell&apos;utente saranno conservati
                per un periodo massimo di 30 (trenta) giorni, trascorsi i quali verranno eliminati
                definitivamente dai nostri sistemi, salvo diversi obblighi di conservazione previsti dalla
                legge.
              </p>
            </section>

            {/* 10 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">10. Modifiche ai Termini</h2>
              <p>
                Noscite si riserva il diritto di modificare i presenti Termini di Servizio in qualsiasi
                momento. Le modifiche saranno comunicate con almeno 15 (quindici) giorni di preavviso
                tramite comunicazione via email all&apos;indirizzo associato all&apos;account
                dell&apos;utente.
              </p>
              <p className="mt-2">
                L&apos;utilizzo continuato del Servizio dopo l&apos;entrata in vigore delle modifiche
                costituisce accettazione implicita dei nuovi Termini. In caso di disaccordo, l&apos;utente
                può cancellare il proprio account prima dell&apos;entrata in vigore delle modifiche.
              </p>
            </section>

            {/* 11 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">11. Legge Applicabile e Foro Competente</h2>
              <p>
                I presenti Termini di Servizio sono regolati dalla legge italiana. Per qualsiasi
                controversia derivante dall&apos;interpretazione, esecuzione o risoluzione dei presenti
                Termini, sarà competente in via esclusiva il Foro di Milano, salvo diversa disposizione
                inderogabile di legge a favore del consumatore.
              </p>
            </section>

            {/* 12 */}
            <section>
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-3">12. Contatti</h2>
              <p>Per qualsiasi domanda o chiarimento relativo ai presenti Termini di Servizio, è possibile contattarci ai seguenti recapiti:</p>
              <ul className="list-none mt-2 space-y-1">
                <li><strong>Ragione sociale:</strong> Noscite SRLS</li>
                <li><strong>P.IVA:</strong> 14385240966</li>
                <li><strong>Email:</strong> <a href="mailto:info@noscite.it" className="text-[#3DAFA8] hover:underline">info@noscite.it</a></li>
                <li><strong>Email privacy:</strong> <a href="mailto:privacy@noscite.it" className="text-[#3DAFA8] hover:underline">privacy@noscite.it</a></li>
                <li><strong>Sito web:</strong> <a href="https://noscite.it" className="text-[#3DAFA8] hover:underline" target="_blank" rel="noopener noreferrer">noscite.it</a></li>
              </ul>
            </section>

          </div>

          <div className="mt-8 pt-6 border-t text-sm text-gray-500">
            <p><strong>Noscite SRLS</strong> — P.IVA 14385240966</p>
            <p><strong>Email:</strong> info@noscite.it</p>
          </div>
        </div>
      </div>
    </div>
  );
}

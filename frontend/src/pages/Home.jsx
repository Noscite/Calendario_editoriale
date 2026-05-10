import { Link } from 'react-router-dom';
import { Calendar, BrainCircuit, Users, FileText } from 'lucide-react';

export default function Home() {
  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8]">
      {/* Header */}
      <header className="px-6 py-4 flex justify-between items-center max-w-6xl mx-auto">
        <div className="flex items-center gap-2">
          <Calendar className="w-8 h-8 text-white" />
          <span className="text-2xl font-bold text-white">Noscite Calendar</span>
        </div>
        <div className="flex gap-4">
          <Link to="/login" className="text-white hover:underline">Accedi</Link>
          <Link to="/register" className="bg-white text-[#2C3E50] px-4 py-2 rounded-lg hover:bg-gray-100">
            Registrati
          </Link>
        </div>
      </header>

      {/* Hero */}
      <main className="max-w-6xl mx-auto px-6 py-16">
        <div className="text-center mb-16">
          <h1 className="text-5xl font-bold text-white mb-6 leading-tight">
            Il tuo brand,<br />tradotto in voce digitale
          </h1>
          <p className="text-xl text-white/80 max-w-3xl mx-auto mb-10">
            Kalendarium analizza in profondità i tuoi materiali aziendali — brand book,
            documenti, tono di voce — e genera buyer personas su misura e piani editoriali
            costruiti sui tuoi contenuti reali. Non sui template di tutti.
          </p>
          <Link
            to="/register"
            className="inline-block bg-white text-[#2C3E50] px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-100 transition-colors"
          >
            Inizia Gratis
          </Link>
        </div>

        {/* Differenziatori */}
        <div className="grid md:grid-cols-3 gap-8 mb-16">
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <BrainCircuit className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Analisi profonda del brand</h3>
            <p className="text-white/70">
              Non solo nome e logo. Leggiamo i tuoi documenti aziendali, riconosciamo il
              tono di voce, capiamo i valori. È così che il sistema impara davvero chi sei.
            </p>
          </div>
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <Users className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Buyer personas su misura</h3>
            <p className="text-white/70">
              Niente template generici. Le personas nascono dai tuoi materiali reali
              e si raffinano col tuo feedback. Sono le tue, non quelle del manuale di marketing.
            </p>
          </div>
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <FileText className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Calendari sui tuoi contenuti</h3>
            <p className="text-white/70">
              I post nascono dai tuoi documenti aziendali, non da ricerche online generiche.
              Ogni contenuto suona come te, perché viene da te.
            </p>
          </div>
        </div>

        {/* Commodity features in secondo piano */}
        <div className="text-center text-white/60 text-sm">
          E poi, ovviamente: calendario visuale, pubblicazione automatica su LinkedIn,
          Facebook, Instagram e Google Business, gestione recensioni e analytics.
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-white/20 mt-auto">
        <div className="max-w-6xl mx-auto px-6 py-8 flex flex-col md:flex-row justify-between items-center gap-4">
          <p className="text-white/60 text-sm">
            © 2026 Noscite di Stefano Andrello - P.IVA 14385240966
          </p>
          <div className="flex gap-6 text-sm">
            <a href="https://noscite.it/privacy-policy" target="_blank" rel="noopener noreferrer" className="text-white/60 hover:text-white">Privacy Policy</a>
            <a href="/terms" className="text-white/60 hover:text-white">Termini di Servizio</a>
            <a href="https://noscite.it/cookie-policy" target="_blank" rel="noopener noreferrer" className="text-white/60 hover:text-white">Cookie Policy</a>
            <a href="mailto:info@noscite.it" className="text-white/60 hover:text-white">info@noscite.it</a>
          </div>
        </div>
      </footer>
    </div>
  );
}

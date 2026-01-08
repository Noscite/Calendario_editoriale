import { Link } from 'react-router-dom';
import { Calendar, Sparkles, Share2, Clock } from 'lucide-react';

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
          <h1 className="text-5xl font-bold text-white mb-6">
            Piano Editoriale AI per PMI
          </h1>
          <p className="text-xl text-white/80 max-w-2xl mx-auto mb-8">
            Crea, pianifica e pubblica automaticamente i tuoi contenuti social 
            con l'intelligenza artificiale. Risparmia tempo, aumenta l'engagement.
          </p>
          <Link 
            to="/register" 
            className="inline-block bg-white text-[#2C3E50] px-8 py-4 rounded-xl text-lg font-semibold hover:bg-gray-100 transition-colors"
          >
            Inizia Gratis
          </Link>
        </div>

        {/* Features */}
        <div className="grid md:grid-cols-3 gap-8 mb-16">
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <Sparkles className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Generazione AI</h3>
            <p className="text-white/70">Contenuti personalizzati per il tuo brand generati automaticamente</p>
          </div>
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <Calendar className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Calendario Visuale</h3>
            <p className="text-white/70">Pianifica e visualizza tutti i tuoi post in un calendario intuitivo</p>
          </div>
          <div className="bg-white/10 backdrop-blur rounded-xl p-6 text-white">
            <Share2 className="w-10 h-10 mb-4" />
            <h3 className="text-xl font-semibold mb-2">Multi-Piattaforma</h3>
            <p className="text-white/70">Pubblica su LinkedIn, Facebook, Instagram e Google Business</p>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-white/20 mt-auto">
        <div className="max-w-6xl mx-auto px-6 py-8 flex flex-col md:flex-row justify-between items-center gap-4">
          <p className="text-white/60 text-sm">
            © 2026 Noscite di Stefano Andrello - P.IVA 14385240966
          </p>
          <div className="flex gap-6 text-sm">
            <Link to="/privacy" className="text-white/60 hover:text-white">Privacy Policy</Link>
            <a href="mailto:info@noscite.it" className="text-white/60 hover:text-white">Contatti</a>
          </div>
        </div>
      </footer>
    </div>
  );
}

import { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Sparkles, MessageSquare, BarChart3, ChevronDown, ArrowRight,
  Utensils, Scissors, Briefcase, HeartPulse, Store, GraduationCap,
  Check, Star, Mail,
} from 'lucide-react';

const SECTORS = [
  { icon: Utensils, label: 'Ristorazione', desc: 'Pizzerie, ristoranti, bar' },
  { icon: Scissors, label: 'Beauty', desc: 'Parrucchieri, estetiste' },
  { icon: Briefcase, label: 'Studi professionali', desc: 'Commercialisti, avvocati, consulenti' },
  { icon: HeartPulse, label: 'Sanitario non regolamentato', desc: 'Osteopati, fisioterapisti, nutrizionisti' },
  { icon: Store, label: 'Retail di prossimità', desc: 'Negozi, boutique' },
  { icon: GraduationCap, label: 'Formazione e corsi', desc: 'Scuole private, percorsi formativi' },
];

const FAQS = [
  {
    q: 'Quanto durano i 14 giorni di prova?',
    a: 'Esatto: hai 14 giorni dal momento della registrazione per esplorare Kalendarium senza limiti di funzionalità. Allo scadere, puoi continuare scegliendo un piano oppure il tuo account passa in stato non attivo (i dati restano per 90 giorni in caso tu cambi idea).',
  },
  {
    q: 'Cosa posso fare durante il trial?',
    a: 'Puoi creare il tuo brand, esplorare la piattaforma e generare il tuo primo calendario editoriale. Le funzioni di pubblicazione automatica e di gestione delle recensioni si attivano col primo abbonamento pagato — vogliamo essere onesti: il trial è per provare, non per usare il sistema in produzione.',
  },
  {
    q: 'Devo dare la mia carta di credito per il trial?',
    a: 'No. Il trial non richiede carta. La inserirai solo se decidi di continuare con un piano a pagamento.',
  },
  {
    q: 'Le risposte automatiche alle recensioni sono davvero automatiche?',
    a: 'Di default no — generiamo bozze on-brand, tu controlli e approvi. Puoi attivare la modalità auto-pilota brand per brand, scegliendo quali condizioni (rating minimo, sentiment positivo) abilitano l\'invio automatico. Le recensioni urgenti o sospette di fake vengono comunque sempre escluse dall\'auto-pilota.',
  },
  {
    q: 'I miei dati sono al sicuro?',
    a: 'Connessioni HTTPS/TLS 1.3, password hashate con bcrypt, token OAuth criptati a riposo, backup giornalieri criptati. Hosting in Francia (OVH). Lista completa dei sub-processor nella nostra Privacy Policy.',
  },
];

function Faq({ item }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="border-b border-gray-200 last:border-0">
      <button
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center justify-between gap-4 py-4 text-left"
      >
        <span className="font-medium text-[#2C3E50]">{item.q}</span>
        <ChevronDown
          size={20}
          className={`text-gray-400 transition-transform shrink-0 ${open ? 'rotate-180' : ''}`}
        />
      </button>
      {open && <p className="pb-4 text-gray-600 leading-relaxed">{item.a}</p>}
    </div>
  );
}

function PricingCard({ tier, price, period, features, ctaLabel, ctaTo, highlighted, footnote }) {
  return (
    <div
      className={`rounded-2xl p-7 flex flex-col ${
        highlighted
          ? 'bg-[#2C3E50] text-white shadow-2xl ring-4 ring-[#3DAFA8]/30 lg:-translate-y-2'
          : 'bg-white border border-gray-200 text-[#2C3E50]'
      }`}
    >
      {highlighted && (
        <div className="inline-flex items-center gap-1 self-start mb-3 text-xs px-3 py-1 rounded-full bg-[#3DAFA8] text-white font-semibold">
          <Star size={12} /> Consigliato
        </div>
      )}
      <h3 className="text-xl font-semibold">{tier}</h3>
      <div className="mt-3">
        <span className="text-4xl font-bold" style={{ fontFamily: '"Playfair Display", serif' }}>{price}</span>
        {period && <span className={`text-sm ${highlighted ? 'text-white/70' : 'text-gray-500'}`}>{period}</span>}
      </div>
      <ul className="mt-6 space-y-2 text-sm flex-1">
        {features.map((f) => (
          <li key={f} className="flex items-start gap-2">
            <Check size={16} className={`mt-0.5 shrink-0 ${highlighted ? 'text-[#3DAFA8]' : 'text-[#3DAFA8]'}`} />
            <span className={highlighted ? 'text-white/90' : 'text-gray-700'}>{f}</span>
          </li>
        ))}
      </ul>
      <Link
        to={ctaTo}
        className={`mt-7 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition ${
          highlighted
            ? 'bg-[#3DAFA8] text-white hover:bg-[#2c8d87]'
            : 'border border-[#3DAFA8] text-[#3DAFA8] hover:bg-[#3DAFA8] hover:text-white'
        }`}
      >
        {ctaLabel}
      </Link>
      {footnote && (
        <p className={`mt-3 text-xs text-center ${highlighted ? 'text-white/60' : 'text-gray-500'}`}>{footnote}</p>
      )}
    </div>
  );
}

export default function HomePage() {
  return (
    <div className="bg-white min-h-screen">
      {/* ================= HEADER ================= */}
      <header className="border-b border-gray-100 bg-white/90 backdrop-blur sticky top-0 z-30">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <span className="w-9 h-9 rounded-full bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] flex items-center justify-center">
              <Sparkles size={18} className="text-white" />
            </span>
            <span
              className="text-2xl text-[#2C3E50] tracking-tight"
              style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
            >
              Kalendarium
            </span>
          </Link>
          <nav className="hidden sm:flex items-center gap-6 text-sm">
            <a href="#funzionalita" className="text-gray-600 hover:text-[#2C3E50]">Funzionalità</a>
            <a href="#pricing" className="text-gray-600 hover:text-[#2C3E50]">Pricing</a>
            <a href="#faq" className="text-gray-600 hover:text-[#2C3E50]">FAQ</a>
            <Link to="/login" className="text-gray-600 hover:text-[#2C3E50]">Accedi</Link>
            <Link
              to="/register"
              className="px-4 py-2 rounded-lg bg-[#3DAFA8] text-white text-sm font-semibold hover:bg-[#2c8d87] transition"
            >
              Inizia gratis
            </Link>
          </nav>
          <Link
            to="/register"
            className="sm:hidden px-3 py-1.5 rounded-lg bg-[#3DAFA8] text-white text-xs font-semibold"
          >
            Inizia gratis
          </Link>
        </div>
      </header>

      {/* ================= HERO (A) ================= */}
      <section className="relative">
        <div className="max-w-6xl mx-auto px-4 sm:px-6 py-12 lg:py-20 grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center">
          <div>
            <h1
              className="text-4xl sm:text-5xl lg:text-6xl text-[#2C3E50] leading-tight"
              style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
            >
              La tua presenza digitale, gestita dall&apos;AI
            </h1>
            <p className="mt-6 text-lg text-gray-600 leading-relaxed max-w-xl">
              Calendario editoriale, social media, recensioni Google. Tutto in un&apos;unica
              piattaforma. Per le PMI italiane che vogliono crescere senza perdere la propria voce.
            </p>
            <div className="mt-8 flex flex-wrap items-center gap-4">
              <Link
                to="/register"
                className="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-[#3DAFA8] text-white font-semibold hover:bg-[#2c8d87] transition shadow-lg shadow-[#3DAFA8]/30"
              >
                Inizia gratis 14 giorni <ArrowRight size={18} />
              </Link>
              <Link to="/login" className="text-sm text-gray-600 hover:text-[#2C3E50]">
                Hai già un account? <span className="underline">Accedi</span>
              </Link>
            </div>
            <p className="mt-4 text-xs text-gray-500">Nessuna carta richiesta per il trial.</p>
          </div>
          <div className="relative">
            <img
              src="/hero-v2.webp"
              alt="Kalendarium dashboard"
              className="rounded-2xl shadow-2xl w-full h-auto"
              loading="eager"
            />
          </div>
        </div>
      </section>

      {/* ================= COSA FA (B) ================= */}
      <section id="funzionalita" className="bg-gray-50 py-16 lg:py-20">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <h2
            className="text-3xl sm:text-4xl text-center text-[#2C3E50] mb-12"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Cosa fa Kalendarium
          </h2>
          <div className="grid md:grid-cols-3 gap-6">
            {[
              {
                Icon: Sparkles,
                title: 'Calendario editoriale AI',
                desc: 'Genera post on-brand per Facebook, Instagram, LinkedIn, Google Business Profile. Pianifica un mese in pochi minuti.',
              },
              {
                Icon: MessageSquare,
                title: 'Risposte automatiche alle recensioni',
                desc: 'Le recensioni Google del tuo brand vengono analizzate e ricevono risposte personalizzate, on-brand, sotto controllo umano.',
              },
              {
                Icon: BarChart3,
                title: 'Analytics che capisce il tuo settore',
                desc: 'Sentiment, topic, urgency. Capisci cosa funziona e cosa preoccupa i tuoi clienti.',
              },
            ].map(({ Icon, title, desc }) => (
              <div key={title} className="bg-white rounded-2xl p-7 shadow-sm border border-gray-100">
                <div className="w-12 h-12 rounded-xl bg-[#3DAFA8]/10 flex items-center justify-center mb-4">
                  <Icon size={22} className="text-[#3DAFA8]" />
                </div>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-2">{title}</h3>
                <p className="text-sm text-gray-600 leading-relaxed">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ================= PER CHI È (C) ================= */}
      <section className="py-16 lg:py-20">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <h2
            className="text-3xl sm:text-4xl text-center text-[#2C3E50] mb-12"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Per chi è
          </h2>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {SECTORS.map(({ icon: Icon, label, desc }) => (
              <div
                key={label}
                className="rounded-xl border border-gray-200 p-5 hover:border-[#3DAFA8] hover:shadow-md transition"
              >
                <div className="flex items-center gap-3 mb-2">
                  <Icon size={20} className="text-[#D4724A]" />
                  <h3 className="font-semibold text-[#2C3E50]">{label}</h3>
                </div>
                <p className="text-sm text-gray-600">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ================= COME FUNZIONA (D) ================= */}
      <section className="bg-gray-50 py-16 lg:py-20">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <h2
            className="text-3xl sm:text-4xl text-center text-[#2C3E50] mb-12"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Come funziona
          </h2>
          <div className="grid md:grid-cols-3 gap-6">
            {[
              {
                num: 1,
                title: 'Crea il tuo brand',
                desc: 'Compila il profilo del tuo business (settore, tono di voce, valori). L\'AI imparerà dal tuo materiale.',
              },
              {
                num: 2,
                title: 'Collega i social e Google',
                desc: 'Facebook, Instagram, LinkedIn, Google Business Profile. Bastano pochi click.',
              },
              {
                num: 3,
                title: 'Lascia che l\'AI lavori',
                desc: 'Calendario editoriale generato, recensioni gestite, sempre con la tua approvazione finale (o auto-pilota se preferisci).',
              },
            ].map(({ num, title, desc }) => (
              <div key={num} className="bg-white rounded-2xl p-7 shadow-sm border border-gray-100">
                <div
                  className="w-10 h-10 rounded-full bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] flex items-center justify-center text-white font-bold mb-4"
                  style={{ fontFamily: '"Playfair Display", serif' }}
                >
                  {num}
                </div>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-2">{title}</h3>
                <p className="text-sm text-gray-600 leading-relaxed">{desc}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ================= PRICING (E) ================= */}
      <section id="pricing" className="py-16 lg:py-20">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <h2
            className="text-3xl sm:text-4xl text-center text-[#2C3E50] mb-3"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Prezzi semplici, valore concreto
          </h2>
          <p className="text-center text-gray-600 mb-12">
            <strong>Inizia con 14 giorni di prova gratuita.</strong> Nessuna carta richiesta.
          </p>
          <div className="grid md:grid-cols-3 gap-6 items-stretch">
            <PricingCard
              tier="Standard"
              price="€79"
              period="/mese"
              features={[
                '1 brand',
                'Post mensili',
                'Recensioni gestite (50/mese)',
                '1 utente',
              ]}
              ctaLabel="Inizia gratis"
              ctaTo="/register"
            />
            <PricingCard
              tier="Pro"
              price="€199"
              period="/mese"
              features={[
                '5 brand',
                'Post illimitati',
                'Recensioni illimitate (500/mese)',
                '5 utenti',
                'Auto-pilota recensioni',
              ]}
              ctaLabel="Inizia gratis"
              ctaTo="/register"
              highlighted
            />
            <PricingCard
              tier="Enterprise"
              price="Custom"
              features={[
                'Brand illimitati',
                'Recensioni illimitate',
                'Supporto dedicato',
                'Integrazioni custom',
              ]}
              ctaLabel="Contattaci"
              ctaTo="/register"
              footnote="Scrivici a service@noscite.it"
            />
          </div>
        </div>
      </section>

      {/* ================= FAQ (F) ================= */}
      <section id="faq" className="bg-gray-50 py-16 lg:py-20">
        <div className="max-w-3xl mx-auto px-4 sm:px-6">
          <h2
            className="text-3xl sm:text-4xl text-center text-[#2C3E50] mb-10"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Domande frequenti
          </h2>
          <div className="bg-white rounded-2xl border border-gray-100 px-6 sm:px-8">
            {FAQS.map((f) => (
              <Faq key={f.q} item={f} />
            ))}
          </div>
        </div>
      </section>

      {/* ================= CTA FINALE (G) ================= */}
      <section className="bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] py-20">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 text-center">
          <h2
            className="text-3xl sm:text-4xl text-white mb-6"
            style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
          >
            Pronto a portare il tuo business su Google?
          </h2>
          <p className="text-white/80 mb-8 text-lg">
            14 giorni di prova gratuita. Nessuna carta richiesta.
          </p>
          <Link
            to="/register"
            className="inline-flex items-center gap-2 px-8 py-3 rounded-lg bg-white text-[#2C3E50] font-semibold hover:bg-gray-100 transition shadow-xl"
          >
            Inizia gratis 14 giorni <ArrowRight size={18} />
          </Link>
        </div>
      </section>

      {/* ================= FOOTER (H) ================= */}
      <footer className="bg-[#1a1f2e] text-gray-400 py-14">
        <div className="max-w-6xl mx-auto px-4 sm:px-6">
          <div className="grid md:grid-cols-4 gap-10 mb-10">
            <div>
              <span
                className="text-xl text-white"
                style={{ fontFamily: '"Playfair Display", serif', fontWeight: 600 }}
              >
                Kalendarium
              </span>
              <p className="mt-3 text-sm leading-relaxed">
                AI per la tua presenza digitale: calendario editoriale, social, recensioni, analytics.
                Pensato per le PMI italiane.
              </p>
            </div>
            <div>
              <h3 className="text-white text-sm font-semibold mb-3">Prodotto</h3>
              <ul className="space-y-2 text-sm">
                <li><a href="#funzionalita" className="hover:text-white transition">Funzionalità</a></li>
                <li><a href="#pricing" className="hover:text-white transition">Pricing</a></li>
                <li><a href="#faq" className="hover:text-white transition">FAQ</a></li>
                <li><Link to="/login" className="hover:text-white transition">Login</Link></li>
              </ul>
            </div>
            <div>
              <h3 className="text-white text-sm font-semibold mb-3">Legale</h3>
              <ul className="space-y-2 text-sm">
                <li><Link to="/privacy" className="hover:text-white transition">Privacy Policy</Link></li>
                <li><Link to="/terms" className="hover:text-white transition">Termini di Servizio</Link></li>
                <li><Link to="/data-deletion" className="hover:text-white transition">Cancellazione Dati</Link></li>
                <li><Link to="/cookies" className="hover:text-white transition">Cookie Policy</Link></li>
                <li>
                  <button
                    onClick={() => window.dispatchEvent(new Event('kalendarium:open-cookie-banner'))}
                    className="hover:text-white transition text-left"
                  >
                    Preferenze cookie
                  </button>
                </li>
              </ul>
            </div>
            <div>
              <h3 className="text-white text-sm font-semibold mb-3">Contatti</h3>
              <ul className="space-y-2 text-sm">
                <li className="flex items-center gap-2">
                  <Mail size={14} />
                  <a href="mailto:service@noscite.it" className="hover:text-white transition">service@noscite.it</a>
                </li>
                <li className="flex items-center gap-2">
                  <Mail size={14} />
                  <a href="mailto:privacy@noscite.it" className="hover:text-white transition">privacy@noscite.it</a>
                </li>
              </ul>
            </div>
          </div>
          <div className="pt-6 border-t border-white/10 text-xs">
            © 2026 Noscite SRLS — P.IVA 14385240966 — Via Monte Grappa 13, Corsico (MI)
          </div>
        </div>
      </footer>
    </div>
  );
}

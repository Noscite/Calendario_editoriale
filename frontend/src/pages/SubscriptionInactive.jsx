import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, Mail, ArrowLeft } from 'lucide-react';
import { subscriptions } from '../services/api';

const TITLES = {
  expired:         'Il tuo abbonamento non è più attivo',
  cancelled:       'Il tuo abbonamento non è più attivo',
  pending_payment: 'Il tuo trial è scaduto',
};

export default function SubscriptionInactive() {
  const [status, setStatus] = useState(null);

  useEffect(() => {
    let cancelled = false;
    subscriptions
      .getCurrentSubscription()
      .then((res) => {
        if (!cancelled) setStatus(res?.data?.subscription_status ?? null);
      })
      .catch(() => {
        // 401/402/etc — la pagina mostra il default
      });
    return () => { cancelled = true; };
  }, []);

  const title = TITLES[status] || 'Accesso non disponibile';

  const handleBackToLogin = () => {
    localStorage.removeItem('token');
  };

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
      <div className="max-w-lg w-full bg-white rounded-2xl shadow-xl p-8 text-center">
        <div className="mx-auto w-16 h-16 rounded-full bg-[#3DAFA8]/10 flex items-center justify-center mb-6">
          <AlertTriangle className="w-8 h-8 text-[#3DAFA8]" />
        </div>

        <h1
          className="text-2xl font-semibold text-[#2C3E50] mb-3"
          style={{ fontFamily: '"Playfair Display", serif' }}
        >
          {title}
        </h1>

        <p className="text-gray-600 leading-relaxed mb-8">
          Per continuare a usare Kalendarium contatta il nostro team. Ti aiuteremo
          ad attivare il piano più adatto alle tue esigenze.
        </p>

        <a
          href="mailto:service@noscite.it?subject=Attivazione%20piano%20Kalendarium"
          className="w-full inline-flex items-center justify-center gap-2 bg-[#3DAFA8] text-white px-6 py-3 rounded-xl hover:bg-[#2d8e88] transition-colors font-medium"
        >
          <Mail size={18} />
          Contattaci per attivare il piano
        </a>

        <Link
          to="/login"
          onClick={handleBackToLogin}
          className="mt-4 inline-flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-[#D4724A] transition-colors"
        >
          <ArrowLeft size={14} />
          Torna al login
        </Link>
      </div>
    </div>
  );
}

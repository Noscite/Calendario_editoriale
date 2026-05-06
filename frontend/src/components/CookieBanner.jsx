import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Cookie, X } from 'lucide-react';

const STORAGE_KEY = 'cookie_consent';

function readConsent() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
}

function writeConsent(prefs) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...prefs, timestamp: Date.now() }));
  } catch {
    /* localStorage off — best-effort */
  }
}

export default function CookieBanner() {
  const [visible, setVisible] = useState(false);
  const [showCustom, setShowCustom] = useState(false);
  const [analytics, setAnalytics] = useState(false);
  const [marketing, setMarketing] = useState(false);

  useEffect(() => {
    if (!readConsent()) {
      // Mostra il banner dopo un piccolo delay per non disturbare il LCP
      const t = setTimeout(() => setVisible(true), 400);
      return () => clearTimeout(t);
    }
  }, []);

  if (!visible) return null;

  const closeAll = (prefs) => {
    writeConsent(prefs);
    setVisible(false);
    setShowCustom(false);
  };

  const acceptAll = () => closeAll({ necessary: true, analytics: true, marketing: true });
  const onlyNecessary = () => closeAll({ necessary: true, analytics: false, marketing: false });
  const saveCustom = () => closeAll({ necessary: true, analytics, marketing });

  return (
    <>
      {/* Banner principale */}
      {!showCustom && (
        <div
          role="dialog"
          aria-live="polite"
          aria-label="Preferenze cookie"
          className="fixed bottom-4 right-4 left-4 sm:left-auto sm:max-w-md z-50 bg-white border border-gray-200 rounded-2xl shadow-xl p-5"
        >
          <div className="flex items-start gap-3 mb-3">
            <Cookie className="w-5 h-5 text-[#3DAFA8] mt-0.5 shrink-0" />
            <p className="text-sm text-gray-700 leading-relaxed">
              Utilizziamo cookie tecnici necessari al funzionamento del sito e, previo tuo consenso,
              cookie analytics per migliorare l&apos;esperienza.{' '}
              <Link to="/cookies" className="text-[#3DAFA8] hover:underline font-medium">
                Maggiori informazioni
              </Link>.
            </p>
          </div>
          <div className="flex flex-wrap gap-2 justify-end">
            <button
              onClick={() => setShowCustom(true)}
              className="text-xs px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
            >
              Personalizza
            </button>
            <button
              onClick={onlyNecessary}
              className="text-xs px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
            >
              Solo necessari
            </button>
            <button
              onClick={acceptAll}
              className="text-xs px-3 py-2 rounded-lg bg-[#3DAFA8] text-white hover:bg-[#2c8d87] transition font-medium"
            >
              Accetta tutti
            </button>
          </div>
        </div>
      )}

      {/* Modale Personalizza */}
      {showCustom && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative">
            <button
              onClick={() => setShowCustom(false)}
              aria-label="Chiudi"
              className="absolute top-3 right-3 text-gray-400 hover:text-gray-700"
            >
              <X size={20} />
            </button>
            <div className="flex items-center gap-2 mb-4">
              <Cookie className="w-5 h-5 text-[#3DAFA8]" />
              <h2 className="text-lg font-semibold text-[#2C3E50]">Preferenze cookie</h2>
            </div>
            <p className="text-sm text-gray-600 mb-5">
              Scegli quali cookie autorizzare. I cookie necessari sono indispensabili e non
              disattivabili.
            </p>

            <div className="space-y-4">
              <label className="flex items-start gap-3 opacity-70 cursor-not-allowed">
                <input type="checkbox" checked disabled className="mt-1 h-4 w-4" />
                <div>
                  <div className="font-medium text-sm text-[#2C3E50]">Necessari</div>
                  <div className="text-xs text-gray-500">
                    Sessione utente, protezione CSRF, memoria delle preferenze cookie. Sempre attivi.
                  </div>
                </div>
              </label>

              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={analytics}
                  onChange={(e) => setAnalytics(e.target.checked)}
                  className="mt-1 h-4 w-4 accent-[#3DAFA8]"
                />
                <div>
                  <div className="font-medium text-sm text-[#2C3E50]">Analytics</div>
                  <div className="text-xs text-gray-500">
                    Permettono di capire come viene usato il sito e migliorare l&apos;esperienza.
                    Attualmente Kalendarium non usa analytics di terze parti.
                  </div>
                </div>
              </label>

              <label className="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  checked={marketing}
                  onChange={(e) => setMarketing(e.target.checked)}
                  className="mt-1 h-4 w-4 accent-[#3DAFA8]"
                />
                <div>
                  <div className="font-medium text-sm text-[#2C3E50]">Marketing</div>
                  <div className="text-xs text-gray-500">
                    Per comunicazioni promozionali profilate. Kalendarium non utilizza cookie di
                    profilazione.
                  </div>
                </div>
              </label>
            </div>

            <div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-100">
              <button
                onClick={() => setShowCustom(false)}
                className="text-xs px-3 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition"
              >
                Annulla
              </button>
              <button
                onClick={saveCustom}
                className="text-xs px-3 py-2 rounded-lg bg-[#3DAFA8] text-white hover:bg-[#2c8d87] transition font-medium"
              >
                Salva preferenze
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}

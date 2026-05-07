import { Lock } from 'lucide-react';

const FEATURE_LABELS = {
  social_auto_publish:          'Pubblicazione automatica sui social',
  social_account_connect:       'Connessione account social',
  multiple_calendars_per_month: 'Più calendari per mese',
  advanced_export:              'Export avanzato',
};

export default function FeatureGatedModal({ isOpen, onClose, feature }) {
  if (!isOpen) return null;

  const label = FEATURE_LABELS[feature] || 'Questa funzionalità';

  return (
    <div
      className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
      onClick={onClose}
    >
      <div
        className="bg-white rounded-xl max-w-md w-full p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center gap-3 mb-4">
          <div className="bg-amber-100 p-2 rounded-full">
            <Lock className="w-5 h-5 text-amber-600" />
          </div>
          <h3 className="text-lg font-semibold text-gray-900">
            Disponibile dopo l'attivazione
          </h3>
        </div>
        <p className="text-gray-600 mb-6">
          <span className="font-medium">{label}</span> è disponibile dopo
          l'attivazione del piano. Contattaci per scegliere il piano più adatto
          alle tue esigenze.
        </p>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Chiudi
          </button>
          <a
            href="mailto:service@noscite.it?subject=Attivazione%20piano%20Kalendarium"
            className="flex-1 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg text-center hover:bg-[#2d8e88] transition-colors"
          >
            Contattaci
          </a>
        </div>
      </div>
    </div>
  );
}

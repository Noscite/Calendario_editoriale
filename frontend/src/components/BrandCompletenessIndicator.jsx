import { CheckCircle2, AlertTriangle } from 'lucide-react';

/**
 * <BrandCompletenessIndicator /> — barra progress + lista campi mancanti.
 * Compatto (≤80px) per uso nel header sticky del Wizard.
 *
 * Props:
 *   - score:          number 0-100
 *   - threshold:      number (default 70) — soglia generazione
 *   - sections:       object (shape come da BrandCompletenessService)
 *   - missing:        array di {section, label}
 *   - onClickMissing: (sectionId) => void   — click su pill → salta allo step
 */
export default function BrandCompletenessIndicator({
  score = 0,
  threshold = 70,
  sections = {},
  missing = [],
  onClickMissing,
}) {
  const safeScore = Math.max(0, Math.min(100, Math.round(score)));
  const aboveThreshold = safeScore >= threshold;

  // gradient inline per evitare classi Tailwind dinamiche non purgate
  const fillStyle = {
    width: `${safeScore}%`,
    backgroundImage: aboveThreshold
      ? 'linear-gradient(90deg, #34d399 0%, #059669 100%)'   // green-400 → green-600
      : 'linear-gradient(90deg, #5eead4 0%, #0d9488 100%)',  // teal-300 → teal-600
  };

  return (
    <div
      className="bg-gray-50 border border-gray-200 rounded-lg px-4 py-3"
      aria-label="Indicatore di completezza del brand"
    >
      <div className="flex items-center justify-between gap-3 mb-2">
        <div className="flex items-center gap-2 text-sm">
          {aboveThreshold ? (
            <CheckCircle2 size={16} className="text-emerald-600" />
          ) : (
            <AlertTriangle size={16} className="text-amber-500" />
          )}
          <span className="font-semibold text-[#2C3E50]">
            {safeScore}% completo
          </span>
          <span className="text-gray-500 hidden sm:inline">
            — soglia generazione {threshold}%
          </span>
        </div>

        {/* Mini badge per sezione */}
        <div className="hidden md:flex items-center gap-1 text-[11px]">
          {Object.entries(sections).map(([key, sec]) => (
            <span
              key={key}
              title={`${sec.label}: ${sec.earned}/${sec.weight}`}
              className={[
                'px-2 py-0.5 rounded-full',
                sec.complete
                  ? 'bg-emerald-100 text-emerald-700'
                  : sec.earned > 0
                    ? 'bg-amber-100 text-amber-800'
                    : 'bg-gray-200 text-gray-500',
              ].join(' ')}
            >
              {sec.label}
            </span>
          ))}
        </div>
      </div>

      <div
        role="progressbar"
        aria-valuenow={safeScore}
        aria-valuemin={0}
        aria-valuemax={100}
        className="h-2 bg-gray-200 rounded-full overflow-hidden"
      >
        <div className="h-full transition-all duration-500" style={fillStyle} />
      </div>

      {missing.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-1.5">
          {missing.map((m, i) => (
            <button
              key={i}
              type="button"
              onClick={() => onClickMissing?.(m.section)}
              className="bg-amber-100 text-amber-900 px-2.5 py-0.5 rounded-full text-xs hover:bg-amber-200 transition-colors"
              title="Clicca per saltare alla sezione"
            >
              {m.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

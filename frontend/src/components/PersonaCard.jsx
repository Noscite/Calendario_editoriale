import {
  Linkedin, Instagram, Facebook, Mail, Target, Edit3, RefreshCw,
} from 'lucide-react';

/**
 * <PersonaCard /> — vista compatta di una buyer persona.
 *
 * Estratto come copia 1:1 da BuyerPersonasStep.jsx (PR pre-PR-WIZARD-2)
 * per riuso nel wizard Project nuovo. Il file legacy resta invariato
 * come safety net del flusso esistente.
 *
 * Props:
 *   - persona:      object { name, demographics, digital_behavior,
 *                            pain_points, interests, buying_triggers, weight }
 *   - readOnly:     boolean (default true) — nasconde gli action button
 *   - onEdit:       () => void  (mostrato solo se !readOnly)
 *   - onRegenerate: () => void  (mostrato solo se !readOnly)
 */

const platformIcons = {
  linkedin: Linkedin,
  instagram: Instagram,
  facebook: Facebook,
  newsletter: Mail,
};

export default function PersonaCard({
  persona,
  readOnly = true,
  onEdit,
  onRegenerate,
}) {
  const {
    name,
    demographics,
    digital_behavior,
    pain_points,
    interests,
    weight,
  } = persona ?? {};

  return (
    <div className="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-gradient-to-br from-teal-400 to-teal-600
                          rounded-full flex items-center justify-center text-white font-bold text-lg">
            {name?.charAt(0) || '?'}
          </div>
          <div>
            <h4 className="font-semibold">{name}</h4>
            <p className="text-sm text-gray-500">{demographics?.role}</p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {weight && (
            <span className="px-2 py-1 bg-teal-100 text-teal-700 rounded text-xs font-medium">
              {Math.round(weight * 100)}%
            </span>
          )}
          {!readOnly && onEdit && (
            <button
              type="button"
              onClick={onEdit}
              className="p-1.5 text-gray-400 hover:text-[#3DAFA8] hover:bg-gray-50 rounded"
              aria-label="Modifica persona"
              title="Modifica"
            >
              <Edit3 size={14} />
            </button>
          )}
          {!readOnly && onRegenerate && (
            <button
              type="button"
              onClick={onRegenerate}
              className="p-1.5 text-gray-400 hover:text-[#3DAFA8] hover:bg-gray-50 rounded"
              aria-label="Rigenera persona"
              title="Rigenera"
            >
              <RefreshCw size={14} />
            </button>
          )}
        </div>
      </div>

      {/* Demographics */}
      <div className="mb-3 text-sm text-gray-600">
        <span className="inline-flex items-center gap-1 mr-3">
          📍 {demographics?.location}
        </span>
        <span className="inline-flex items-center gap-1">
          🎂 {demographics?.age_range}
        </span>
      </div>

      {/* Digital Behavior Pills */}
      <div className="flex flex-wrap gap-1 mb-3">
        {Object.entries(digital_behavior || {}).slice(0, 3).map(([platform, data]) => {
          const Icon = platformIcons[platform] || Target;
          return (
            <span
              key={platform}
              className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100
                         rounded text-xs text-gray-700"
            >
              <Icon className="w-3 h-3" />
              {data.best_times?.[0] || ''}
            </span>
          );
        })}
      </div>

      {/* Pain Points */}
      {pain_points && pain_points.length > 0 && (
        <div className="mb-2">
          <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Pain Points</p>
          <p className="text-sm text-gray-600">{pain_points.slice(0, 3).join(' • ')}</p>
        </div>
      )}

      {/* Interests */}
      {interests && interests.length > 0 && (
        <div>
          <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Interessi</p>
          <div className="flex flex-wrap gap-1">
            {interests.slice(0, 4).map((interest, i) => (
              <span key={i} className="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">
                {interest}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

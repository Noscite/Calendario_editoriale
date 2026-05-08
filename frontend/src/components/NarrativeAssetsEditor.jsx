import { Plus, Trash2, Library } from 'lucide-react';

/**
 * <NarrativeAssetsEditor /> — repeater controlled per Brand.narrative_assets.
 * Shape: [{type, name, details}], type ∈ course/book/product/event/partner/service.
 *
 * Controlled component: la lista vive nel parent via value/onChange.
 * Niente save interno — il submit reale lo fa il Wizard al next step.
 *
 * Props:
 *   - value:    array (controlled, default [])
 *   - onChange: (newArray) => void
 *   - error:    string|null (errore esterno mostrato sotto il titolo)
 */

const TYPE_OPTIONS = [
  { value: 'course',  label: 'Corso',    emoji: '🎓' },
  { value: 'book',    label: 'Libro',    emoji: '📘' },
  { value: 'product', label: 'Prodotto', emoji: '🛠️' },
  { value: 'event',   label: 'Evento',   emoji: '📅' },
  { value: 'partner', label: 'Partner',  emoji: '🤝' },
  { value: 'service', label: 'Servizio', emoji: '⚙️' },
];

const DETAILS_MAX = 240;

function emptyAsset() {
  return { type: 'course', name: '', details: '' };
}

export default function NarrativeAssetsEditor({ value = [], onChange, error = null }) {
  const items = Array.isArray(value) ? value : [];

  const updateItem = (idx, patch) => {
    const next = items.map((it, i) => (i === idx ? { ...it, ...patch } : it));
    onChange?.(next);
  };

  const removeItem = (idx) => {
    onChange?.(items.filter((_, i) => i !== idx));
  };

  const addItem = () => {
    onChange?.([...items, emptyAsset()]);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h4 className="font-semibold text-[#2C3E50] flex items-center gap-2">
            <Library size={18} className="text-[#3DAFA8]" /> Asset narrativi del brand
          </h4>
          <p className="text-sm text-gray-500 mt-1">
            Elenca i tuoi <strong>corsi, libri, prodotti, eventi, partner, servizi reali</strong>.
            L'AI userà SOLO questa lista per nominarli — niente nomi inventati.
          </p>
        </div>
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-400 text-red-700 px-3 py-2 rounded text-sm">
          {error}
        </div>
      )}

      {items.length === 0 && (
        <div className="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 text-center text-sm text-gray-500">
          Nessun asset ancora. Aggiungi almeno 1 voce per descrivere ciò che il brand offre.
        </div>
      )}

      <div className="space-y-3">
        {items.map((it, idx) => (
          <div key={idx} className="bg-white border border-gray-200 rounded-lg p-4">
            <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
              {/* Type select */}
              <div className="md:col-span-3">
                <label className="block text-xs font-medium text-gray-600 mb-1">Tipo *</label>
                <select
                  value={it.type ?? ''}
                  onChange={(e) => updateItem(idx, { type: e.target.value })}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                >
                  {TYPE_OPTIONS.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.emoji} {opt.label}
                    </option>
                  ))}
                </select>
              </div>

              {/* Name */}
              <div className="md:col-span-4">
                <label className="block text-xs font-medium text-gray-600 mb-1">Nome *</label>
                <input
                  type="text"
                  value={it.name ?? ''}
                  onChange={(e) => updateItem(idx, { name: e.target.value })}
                  placeholder="Es. PRIMUS, Restare Umani"
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  maxLength={120}
                />
              </div>

              {/* Details */}
              <div className="md:col-span-4">
                <label className="block text-xs font-medium text-gray-600 mb-1">
                  Dettagli (opzionale)
                </label>
                <textarea
                  value={it.details ?? ''}
                  onChange={(e) => updateItem(idx, { details: e.target.value.slice(0, DETAILS_MAX) })}
                  rows={2}
                  placeholder="Durata, target, anno, focus..."
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-none"
                />
                <span className="text-[11px] text-gray-400">
                  {(it.details ?? '').length}/{DETAILS_MAX}
                </span>
              </div>

              {/* Remove */}
              <div className="md:col-span-1 flex md:justify-end pt-1 md:pt-6">
                <button
                  type="button"
                  onClick={() => removeItem(idx)}
                  className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                  aria-label={`Rimuovi asset ${idx + 1}`}
                >
                  <Trash2 size={16} />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      <button
        type="button"
        onClick={addItem}
        className="flex items-center gap-2 px-4 py-2 border border-dashed border-[#3DAFA8] text-[#3DAFA8] rounded-lg hover:bg-teal-50 transition-colors text-sm"
      >
        <Plus size={16} /> Aggiungi asset
      </button>
    </div>
  );
}

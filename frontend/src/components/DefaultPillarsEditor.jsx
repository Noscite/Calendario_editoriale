import { Plus, Trash2, Target, AlertCircle } from 'lucide-react';

/**
 * <DefaultPillarsEditor /> — repeater controlled per Brand.default_content_pillars.
 * Shape: [{name, description}], min 4 max 6, name unique within set.
 *
 * Controlled component: la lista vive nel parent via value/onChange.
 * Niente save interno. Validazione client-side (len, unique) — il server
 * applica sue regole; questo è solo per UX immediata.
 *
 * Props:
 *   - value:    array (controlled)
 *   - onChange: (newArray) => void
 *   - error:    string|null
 */

const MIN_PILLARS = 4;
const MAX_PILLARS = 6;
const NAME_MAX = 60;
const DESC_MAX = 200;

function emptyPillar() {
  return { name: '', description: '' };
}

function normalizeNameForUniqueCheck(s) {
  return String(s ?? '').trim().toLowerCase();
}

export default function DefaultPillarsEditor({ value = [], onChange, error = null }) {
  const items = Array.isArray(value) ? value : [];
  const count = items.length;

  // Validazione locale: name unique case-insensitive (per evidenziare le righe duplicate)
  const seenCounts = items.reduce((acc, it) => {
    const key = normalizeNameForUniqueCheck(it.name);
    if (key !== '') {
      acc[key] = (acc[key] ?? 0) + 1;
    }
    return acc;
  }, {});

  const isDuplicate = (idx) => {
    const key = normalizeNameForUniqueCheck(items[idx]?.name);
    return key !== '' && (seenCounts[key] ?? 0) > 1;
  };

  const updateItem = (idx, patch) => {
    const next = items.map((it, i) => (i === idx ? { ...it, ...patch } : it));
    onChange?.(next);
  };

  const removeItem = (idx) => {
    onChange?.(items.filter((_, i) => i !== idx));
  };

  const addItem = () => {
    if (count >= MAX_PILLARS) return;
    onChange?.([...items, emptyPillar()]);
  };

  const countTone =
    count < MIN_PILLARS ? 'text-amber-600'
    : count > MAX_PILLARS ? 'text-red-600'
    : 'text-emerald-600';

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h4 className="font-semibold text-[#2C3E50] flex items-center gap-2">
            <Target size={18} className="text-[#3DAFA8]" /> Default content pillars del brand
          </h4>
          <p className="text-sm text-gray-500 mt-1">
            Configura tra <strong>{MIN_PILLARS} e {MAX_PILLARS}</strong> pillar che caratterizzano la voce del tuo brand.
            Esempi: <em>Frontiera tecnica, Pattern operativi, Manifesto, Backstage, Author Brand</em>.
            Ogni Project ricaverà da qui la propria selezione.
          </p>
        </div>
        <span className={`text-sm font-medium whitespace-nowrap ${countTone}`}>
          {count} / {MAX_PILLARS}
        </span>
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-400 text-red-700 px-3 py-2 rounded text-sm">
          {error}
        </div>
      )}

      {count === 0 && (
        <div className="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-6 text-center text-sm text-gray-500">
          Aggiungi almeno {MIN_PILLARS} pillar per completare questa sezione.
        </div>
      )}

      <div className="space-y-3">
        {items.map((it, idx) => {
          const dup = isDuplicate(idx);
          return (
            <div
              key={idx}
              className={[
                'bg-white border rounded-lg p-4',
                dup ? 'border-red-300 bg-red-50/30' : 'border-gray-200',
              ].join(' ')}
            >
              <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                <div className="md:col-span-4">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Nome pillar *</label>
                  <input
                    type="text"
                    value={it.name ?? ''}
                    onChange={(e) => updateItem(idx, { name: e.target.value.slice(0, NAME_MAX) })}
                    placeholder="Es. Frontiera tecnica"
                    className={[
                      'w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:border-transparent',
                      dup ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-[#3DAFA8]',
                    ].join(' ')}
                  />
                  {dup && (
                    <p className="text-xs text-red-600 mt-1 flex items-center gap-1">
                      <AlertCircle size={12} /> Nome duplicato
                    </p>
                  )}
                </div>

                <div className="md:col-span-7">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Descrizione *</label>
                  <textarea
                    value={it.description ?? ''}
                    onChange={(e) => updateItem(idx, { description: e.target.value.slice(0, DESC_MAX) })}
                    rows={2}
                    placeholder="1-2 frasi su cosa contiene questo pillar"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-none"
                  />
                  <span className="text-[11px] text-gray-400">
                    {(it.description ?? '').length}/{DESC_MAX}
                  </span>
                </div>

                <div className="md:col-span-1 flex md:justify-end pt-1 md:pt-6">
                  <button
                    type="button"
                    onClick={() => removeItem(idx)}
                    className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Rimuovi pillar ${idx + 1}`}
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            </div>
          );
        })}
      </div>

      <button
        type="button"
        onClick={addItem}
        disabled={count >= MAX_PILLARS}
        className={[
          'flex items-center gap-2 px-4 py-2 border border-dashed rounded-lg text-sm transition-colors',
          count >= MAX_PILLARS
            ? 'border-gray-200 text-gray-300 cursor-not-allowed'
            : 'border-[#3DAFA8] text-[#3DAFA8] hover:bg-teal-50',
        ].join(' ')}
      >
        <Plus size={16} /> Aggiungi pillar
      </button>
    </div>
  );
}

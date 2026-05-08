import { Plus, Trash2, Calendar, AlertCircle } from 'lucide-react';

/**
 * <SpecialDatesEditor /> — repeater controlled per Project.special_dates.
 * Shape: [{date: 'YYYY-MM-DD', description: string}].
 *
 * Stile coerente con NarrativeAssetsEditor di PR-WIZARD-1.
 *
 * Validazione locale (UX immediato):
 *  - date: required, formato YYYY-MM-DD
 *  - description: required, min 5 char, max DESC_MAX char
 *
 * Sort automatico per data crescente al render.
 *
 * Props:
 *   - value:    array<{date, description}>
 *   - onChange: (newArray) => void
 *   - error:    string|null
 */

const DESC_MIN = 5;
const DESC_MAX = 240;

function emptyDate() {
  return { date: '', description: '' };
}

function sortByDate(items) {
  return [...items].sort((a, b) => {
    const da = a.date || '';
    const db = b.date || '';
    if (da === db) return 0;
    if (da === '') return 1;
    if (db === '') return -1;
    return da < db ? -1 : 1;
  });
}

export default function SpecialDatesEditor({ value = [], onChange, error = null }) {
  const items = Array.isArray(value) ? value : [];

  const updateItem = (idx, patch) => {
    const next = items.map((it, i) => (i === idx ? { ...it, ...patch } : it));
    onChange?.(next);
  };

  const removeItem = (idx) => {
    onChange?.(items.filter((_, i) => i !== idx));
  };

  const addItem = () => {
    onChange?.([...items, emptyDate()]);
  };

  const sortNow = () => {
    onChange?.(sortByDate(items));
  };

  const isDescTooShort = (desc) => {
    const trimmed = (desc || '').trim();
    return trimmed.length > 0 && trimmed.length < DESC_MIN;
  };

  return (
    <div className="space-y-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h4 className="font-semibold text-[#2C3E50] flex items-center gap-2">
            <Calendar size={18} className="text-[#3DAFA8]" /> Date speciali del project
          </h4>
          <p className="text-sm text-gray-500 mt-1">
            Eventi, anniversari, lanci, scadenze: l'AI userà queste date come ancore narrative quando rilevante.
          </p>
        </div>
        {items.length > 1 && (
          <button
            type="button"
            onClick={sortNow}
            className="text-xs text-[#3DAFA8] hover:text-[#2C3E50] underline whitespace-nowrap"
          >
            Ordina per data
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-400 text-red-700 px-3 py-2 rounded text-sm">
          {error}
        </div>
      )}

      {items.length === 0 && (
        <div className="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-5 text-center text-sm text-gray-500">
          Nessuna data speciale. Aggiungi date rilevanti per il calendario (opzionale).
        </div>
      )}

      <div className="space-y-3">
        {items.map((it, idx) => {
          const tooShort = isDescTooShort(it.description);
          return (
            <div key={idx} className="bg-white border border-gray-200 rounded-lg p-4">
              <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                <div className="md:col-span-3">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Data *</label>
                  <input
                    type="date"
                    value={it.date ?? ''}
                    onChange={(e) => updateItem(idx, { date: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  />
                </div>

                <div className="md:col-span-8">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Descrizione *</label>
                  <input
                    type="text"
                    value={it.description ?? ''}
                    onChange={(e) => updateItem(idx, { description: e.target.value.slice(0, DESC_MAX) })}
                    placeholder="Es. Lancio nuovo prodotto, anniversario azienda…"
                    className={[
                      'w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:border-transparent',
                      tooShort ? 'border-amber-400 focus:ring-amber-200' : 'border-gray-300 focus:ring-[#3DAFA8]',
                    ].join(' ')}
                  />
                  <div className="flex items-center justify-between mt-1">
                    {tooShort ? (
                      <span className="text-[11px] text-amber-700 flex items-center gap-1">
                        <AlertCircle size={11} /> Min {DESC_MIN} caratteri
                      </span>
                    ) : (
                      <span />
                    )}
                    <span className="text-[11px] text-gray-400">
                      {(it.description ?? '').length}/{DESC_MAX}
                    </span>
                  </div>
                </div>

                <div className="md:col-span-1 flex md:justify-end pt-1 md:pt-6">
                  <button
                    type="button"
                    onClick={() => removeItem(idx)}
                    className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Rimuovi data ${idx + 1}`}
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
        className="flex items-center gap-2 px-4 py-2 border border-dashed border-[#3DAFA8] text-[#3DAFA8] rounded-lg hover:bg-teal-50 transition-colors text-sm"
      >
        <Plus size={16} /> Aggiungi data speciale
      </button>
    </div>
  );
}

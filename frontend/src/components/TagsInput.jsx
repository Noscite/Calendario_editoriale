import { useState } from 'react';
import { X } from 'lucide-react';

/**
 * <TagsInput /> — input testuale + chip rimovibili per liste di stringhe.
 * Estratto da EditBrandWizard.jsx in PR-WIZARD-2 per riuso nel wizard Project.
 *
 * Controlled component: il parent gestisce lo stato via value/onChange.
 *
 * Props:
 *   - value:        array<string> (controlled)
 *   - onChange:     (newArray) => void
 *   - placeholder:  string (mostrato solo quando il box è vuoto)
 *   - helper:       string (testo secondario sotto al box)
 *   - error:        string|null (testo rosso sotto al box, bordo rosso al box)
 *   - maxItems:     number|null (default null = no limit). Se raggiunto, input
 *                    disabilitato + hint "Max X tag"
 *   - ariaLabel:    string (applicato all'input per accessibility)
 *
 * Comportamento:
 *   - Enter o Tab → addTag (trim, skip se vuoto, dedup case-insensitive)
 *   - Backspace su input vuoto → removeTag dell'ultimo
 *   - onBlur → addTag (commit pending draft)
 *   - Click X sul chip → removeTag(idx)
 */
export default function TagsInput({
  value = [],
  onChange,
  placeholder,
  helper,
  error = null,
  maxItems = null,
  ariaLabel,
}) {
  const [draft, setDraft] = useState('');
  const tags = Array.isArray(value) ? value : [];
  const atMax = typeof maxItems === 'number' && tags.length >= maxItems;

  const addTag = () => {
    const v = draft.trim();
    if (!v) return;
    if (atMax) {
      setDraft('');
      return;
    }
    const lower = v.toLowerCase();
    if (tags.some((t) => String(t).toLowerCase() === lower)) {
      setDraft('');
      return;
    }
    onChange?.([...tags, v]);
    setDraft('');
  };

  const removeTag = (idx) => {
    onChange?.(tags.filter((_, i) => i !== idx));
  };

  const onKeyDown = (e) => {
    if (e.key === 'Enter' || e.key === 'Tab') {
      // Per Tab, aggiungi solo se draft non vuoto: altrimenti lascia
      // navigare normalmente al prossimo campo.
      if (e.key === 'Tab' && draft.trim() === '') return;
      e.preventDefault();
      addTag();
    } else if (e.key === 'Backspace' && draft === '' && tags.length > 0) {
      removeTag(tags.length - 1);
    }
  };

  return (
    <div>
      <div
        className={[
          'flex flex-wrap gap-2 px-2 py-2 border rounded-lg bg-white',
          error ? 'border-red-300' : 'border-gray-300',
        ].join(' ')}
      >
        {tags.map((t, i) => (
          <span
            key={`${t}-${i}`}
            className="inline-flex items-center gap-1 bg-teal-50 text-[#2C3E50] border border-teal-200 rounded-full px-2.5 py-0.5 text-xs"
          >
            {t}
            <button
              type="button"
              onClick={() => removeTag(i)}
              className="text-gray-400 hover:text-red-500"
              aria-label={`Rimuovi ${t}`}
            >
              <X size={12} />
            </button>
          </span>
        ))}
        <input
          type="text"
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={onKeyDown}
          onBlur={addTag}
          placeholder={tags.length === 0 ? placeholder : ''}
          disabled={atMax}
          aria-label={ariaLabel}
          className="flex-1 min-w-[140px] px-1 py-1 text-sm focus:outline-none disabled:bg-transparent disabled:text-gray-400"
        />
      </div>
      {atMax && (
        <p className="text-xs text-gray-500 mt-1">Max {maxItems} tag raggiunto.</p>
      )}
      {!atMax && helper && <p className="text-xs text-gray-500 mt-1">{helper}</p>}
      {error && <p className="text-xs text-red-600 mt-1">{error}</p>}
    </div>
  );
}

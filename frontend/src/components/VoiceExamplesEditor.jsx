/**
 * VoiceExamplesEditor.jsx
 *
 * Editor per i voice_examples del Brand. Componente collassabile coerente
 * con lo stile delle altre sezioni di BrandDetail (API Keys, Calendars).
 *
 * Props:
 *   brandId: number      — id del brand
 *   initialValue: array  — voice_examples correnti (dal Brand)
 *   onSaved: function    — callback dopo salvataggio riuscito (riceve nuovo array)
 *
 * Vincoli applicati lato UI (paritari al backend):
 *   - max 5 esempi
 *   - content: 20–2000 caratteri
 *   - platform: linkedin | instagram | facebook | google_business
 *   - note: opzionale, max 150
 */

import { useState } from 'react';
import {
  Mic, Plus, Trash2, Save, Loader2,
  ChevronDown, ChevronUp, GripVertical,
} from 'lucide-react';
import { brands } from '../services/api';

const PLATFORM_OPTIONS = [
  { value: 'linkedin', label: 'LinkedIn' },
  { value: 'instagram', label: 'Instagram' },
  { value: 'facebook', label: 'Facebook' },
  { value: 'google_business', label: 'Google Business' },
];

const MAX_EXAMPLES = 5;
const CONTENT_MIN = 20;
const CONTENT_MAX = 2000;
const NOTE_MAX = 150;

function emptyExample() {
  return { platform: 'linkedin', content: '', note: '' };
}

export default function VoiceExamplesEditor({ brandId, initialValue = [], onSaved }) {
  const [open, setOpen] = useState(true);
  const [examples, setExamples] = useState(
    Array.isArray(initialValue) && initialValue.length > 0
      ? initialValue
      : []
  );
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [expandedItems, setExpandedItems] = useState(() =>
    new Set(initialValue.map((_, i) => i))
  );

  const addExample = () => {
    if (examples.length >= MAX_EXAMPLES) return;
    const next = [...examples, emptyExample()];
    setExamples(next);
    setExpandedItems((s) => new Set([...s, next.length - 1]));
  };

  const removeExample = (index) => {
    setExamples((current) => current.filter((_, i) => i !== index));
    setExpandedItems((s) => {
      const next = new Set();
      for (const idx of s) {
        if (idx < index) next.add(idx);
        else if (idx > index) next.add(idx - 1);
      }
      return next;
    });
  };

  const updateExample = (index, patch) => {
    setExamples((current) =>
      current.map((ex, i) => (i === index ? { ...ex, ...patch } : ex))
    );
  };

  const toggleItem = (index) => {
    setExpandedItems((s) => {
      const next = new Set(s);
      if (next.has(index)) next.delete(index);
      else next.add(index);
      return next;
    });
  };

  const validate = () => {
    if (examples.length > MAX_EXAMPLES) {
      return `Massimo ${MAX_EXAMPLES} esempi consentiti.`;
    }
    for (let i = 0; i < examples.length; i++) {
      const ex = examples[i];
      if (!ex.platform) return `Esempio ${i + 1}: piattaforma mancante.`;
      const len = (ex.content || '').trim().length;
      if (len < CONTENT_MIN) {
        return `Esempio ${i + 1}: il testo deve avere almeno ${CONTENT_MIN} caratteri (attuali: ${len}).`;
      }
      if (len > CONTENT_MAX) {
        return `Esempio ${i + 1}: il testo supera ${CONTENT_MAX} caratteri.`;
      }
      if ((ex.note || '').length > NOTE_MAX) {
        return `Esempio ${i + 1}: la nota supera ${NOTE_MAX} caratteri.`;
      }
    }
    return null;
  };

  const handleSave = async () => {
    setMessage(null);
    const err = validate();
    if (err) {
      setMessage({ type: 'error', text: err });
      return;
    }

    setSaving(true);
    try {
      const payload = examples.map((ex) => ({
        platform: ex.platform,
        content: (ex.content || '').trim(),
        note: (ex.note || '').trim(),
      }));

      await brands.update(brandId, { voice_examples: payload });

      setMessage({ type: 'success', text: 'Esempi di voce salvati correttamente.' });
      onSaved?.(payload);
    } catch (e) {
      const msg =
        e?.response?.data?.message ||
        e?.response?.data?.detail ||
        e?.message ||
        'Errore durante il salvataggio.';
      setMessage({ type: 'error', text: msg });
    } finally {
      setSaving(false);
    }
  };

  const platformLabel = (p) =>
    PLATFORM_OPTIONS.find((o) => o.value === p)?.label || p;

  return (
    <div className="bg-white rounded-xl shadow-sm">
      <button
        onClick={() => setOpen((o) => !o)}
        className="w-full flex items-center justify-between p-6 text-left"
      >
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
            <Mic size={20} className="text-gray-600" />
          </div>
          <div>
            <h3 className="font-semibold text-[#2C3E50]">Esempi di voce del brand</h3>
            <p className="text-sm text-gray-500">
              {examples.length} di {MAX_EXAMPLES} esempi · l'AI imita il tuo registro
            </p>
          </div>
        </div>
        {open ? <ChevronUp size={20} className="text-gray-400" /> : <ChevronDown size={20} className="text-gray-400" />}
      </button>

      {open && (
        <div className="px-6 pb-6 space-y-4 border-t pt-4">
          <div className="text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-lg p-3">
            <p className="font-medium text-[#2C3E50] mb-1">Perché serve</p>
            <p>
              Inserisci 2–5 post pubblicati o approvati che rappresentano la voce autentica del
              brand. L'AI userà questi come riferimento concreto per imitare il tuo stile —
              un esempio reale vale più di 1000 parole di descrizione.
            </p>
          </div>

          {examples.length === 0 && (
            <div className="text-center py-6 text-sm text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
              Nessun esempio inserito. Aggiungine almeno 2 per migliorare la qualità dei contenuti generati.
            </div>
          )}

          {examples.map((ex, index) => {
            const isExpanded = expandedItems.has(index);
            const charCount = (ex.content || '').trim().length;
            const charValid = charCount >= CONTENT_MIN && charCount <= CONTENT_MAX;

            return (
              <div
                key={index}
                className="border border-gray-200 rounded-lg overflow-hidden"
              >
                <div className="flex items-center justify-between p-3 bg-gray-50 border-b border-gray-200">
                  <button
                    type="button"
                    onClick={() => toggleItem(index)}
                    className="flex items-center gap-2 text-sm font-medium text-[#2C3E50] flex-1 text-left"
                  >
                    <GripVertical size={14} className="text-gray-400" />
                    <span>Esempio {index + 1} — {platformLabel(ex.platform)}</span>
                    {!isExpanded && ex.content && (
                      <span className="text-xs text-gray-500 font-normal truncate">
                        · {ex.content.substring(0, 60)}{ex.content.length > 60 ? '…' : ''}
                      </span>
                    )}
                  </button>
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => removeExample(index)}
                      className="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded"
                      title="Elimina esempio"
                    >
                      <Trash2 size={14} />
                    </button>
                    <button
                      type="button"
                      onClick={() => toggleItem(index)}
                      className="p-1.5 text-gray-400 hover:text-gray-700"
                    >
                      {isExpanded ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                    </button>
                  </div>
                </div>

                {isExpanded && (
                  <div className="p-4 space-y-3">
                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">
                        Piattaforma
                      </label>
                      <select
                        value={ex.platform}
                        onChange={(e) => updateExample(index, { platform: e.target.value })}
                        className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-[#3DAFA8]"
                      >
                        {PLATFORM_OPTIONS.map((opt) => (
                          <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                      </select>
                    </div>

                    <div>
                      <div className="flex items-center justify-between mb-1">
                        <label className="block text-xs font-medium text-gray-600">
                          Testo del post
                        </label>
                        <span className={`text-xs ${charValid ? 'text-gray-500' : 'text-red-600'}`}>
                          {charCount} / {CONTENT_MAX} {charCount < CONTENT_MIN && `(min ${CONTENT_MIN})`}
                        </span>
                      </div>
                      <textarea
                        value={ex.content || ''}
                        onChange={(e) => updateExample(index, { content: e.target.value })}
                        rows={5}
                        placeholder="Incolla qui il testo integrale di un post che rappresenta la tua voce autentica."
                        className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-[#3DAFA8] resize-y"
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-medium text-gray-600 mb-1">
                        Nota <span className="text-gray-400">(opzionale, max {NOTE_MAX} caratteri)</span>
                      </label>
                      <input
                        type="text"
                        value={ex.note || ''}
                        onChange={(e) => updateExample(index, { note: e.target.value })}
                        maxLength={NOTE_MAX}
                        placeholder="es: tono che usiamo per gli annunci"
                        className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-[#3DAFA8]"
                      />
                    </div>
                  </div>
                )}
              </div>
            );
          })}

          {examples.length < MAX_EXAMPLES && (
            <button
              type="button"
              onClick={addExample}
              className="w-full flex items-center justify-center gap-2 py-3 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-600 hover:border-[#3DAFA8] hover:text-[#3DAFA8] transition-colors"
            >
              <Plus size={16} />
              Aggiungi esempio ({examples.length}/{MAX_EXAMPLES})
            </button>
          )}

          {message && (
            <div
              className={`p-3 rounded-lg text-sm ${
                message.type === 'success'
                  ? 'bg-green-50 text-green-700 border border-green-200'
                  : 'bg-red-50 text-red-700 border border-red-200'
              }`}
            >
              {message.text}
            </div>
          )}

          <div className="flex justify-end pt-2">
            <button
              onClick={handleSave}
              disabled={saving}
              className="flex items-center gap-2 px-5 py-2.5 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 text-sm font-medium transition-colors"
            >
              {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
              Salva esempi di voce
            </button>
          </div>
        </div>
      )}
    </div>
  );
}

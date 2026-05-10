import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, Trash2 } from 'lucide-react';
import { campaigns as campaignsApi } from '../services/api';

const STATUS_OPTIONS = [
  { value: 'draft',     label: 'Bozza' },
  { value: 'planning',  label: 'Pianificazione' },
  { value: 'active',    label: 'Attiva' },
  { value: 'completed', label: 'Conclusa' },
  { value: 'archived',  label: 'Archiviata' },
];

export default function CampaignForm({ initial = null, brands = [], onSaved }) {
  const navigate = useNavigate();
  const [form, setForm] = useState(() => ({
    name:        initial?.name        ?? '',
    description: initial?.description ?? '',
    brief:       initial?.brief       ?? '',
    start_date:  initial?.start_date  ?? '',
    end_date:    initial?.end_date    ?? '',
    status:      initial?.status      ?? 'draft',
    brand_ids:   initial?.brands?.map(b => b.id) ?? [],
  }));
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);

  const isEdit = Boolean(initial?.id);

  const update = (patch) => setForm(prev => ({ ...prev, ...patch }));

  const toggleBrand = (id) => {
    update({
      brand_ids: form.brand_ids.includes(id)
        ? form.brand_ids.filter(x => x !== id)
        : [...form.brand_ids, id],
    });
  };

  const handleSubmit = async (e) => {
    e?.preventDefault();
    setSaving(true);
    setError(null);
    try {
      const payload = {
        name: form.name,
        description: form.description || null,
        brief: form.brief || null,
        start_date: form.start_date || null,
        end_date: form.end_date || null,
        brand_ids: form.brand_ids,
      };
      // In create non passiamo status (default draft); in edit sì
      if (isEdit) payload.status = form.status;

      const res = isEdit
        ? await campaignsApi.update(initial.id, payload)
        : await campaignsApi.create(payload);

      onSaved?.(res.data);
      navigate('/campaigns');
    } catch (err) {
      const apiError = err.response?.data?.error;
      if (apiError?.code) {
        setError(apiError.message);
      } else {
        setError('Errore salvataggio. Riprova.');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm(`Vuoi davvero eliminare la campagna "${initial.name}"?`)) return;
    setSaving(true);
    setError(null);
    try {
      await campaignsApi.delete(initial.id);
      navigate('/campaigns');
    } catch (err) {
      setError(err.response?.data?.error?.message || 'Eliminazione non consentita');
      setSaving(false);
    }
  };

  const isDeletable = isEdit && ['draft', 'archived'].includes(form.status);

  return (
    <form onSubmit={handleSubmit} className="p-6 max-w-3xl mx-auto space-y-6">
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => navigate('/campaigns')}
          className="inline-flex items-center gap-2 text-gray-600 hover:text-gray-800"
        >
          <ArrowLeft size={18} /> Indietro
        </button>
        <h1 className="text-xl font-bold text-[#2C3E50]">
          {isEdit ? 'Modifica campagna' : 'Nuova campagna'}
        </h1>
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-400 text-red-700 px-4 py-3 rounded">
          {error}
        </div>
      )}

      <div className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
          <input
            type="text"
            value={form.name}
            onChange={e => update({ name: e.target.value })}
            required
            maxLength={255}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            placeholder="es. Lancio Sagra del Riso 2026"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Descrizione</label>
          <textarea
            value={form.description}
            onChange={e => update({ description: e.target.value })}
            rows={2}
            maxLength={500}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            placeholder="Breve descrizione visibile nella lista campagne"
          />
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Brief</label>
          <textarea
            value={form.brief}
            onChange={e => update({ brief: e.target.value })}
            rows={4}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            placeholder="Brief operativo della campagna: obiettivi, target, tone, riferimenti…"
          />
        </div>

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data inizio</label>
            <input
              type="date"
              value={form.start_date}
              onChange={e => update({ start_date: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data fine</label>
            <input
              type="date"
              value={form.end_date}
              onChange={e => update({ end_date: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
          </div>
        </div>

        {isEdit && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Stato</label>
            <select
              value={form.status}
              onChange={e => update({ status: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            >
              {STATUS_OPTIONS.map(s => (
                <option key={s.value} value={s.value}>{s.label}</option>
              ))}
            </select>
            <p className="text-xs text-gray-500 mt-1">
              Transizioni consentite: draft↔planning, planning→active, active→completed, completed→archived.
            </p>
          </div>
        )}
      </div>

      <div className="bg-white border border-gray-200 rounded-lg p-5">
        <h3 className="font-semibold text-[#2C3E50] mb-2">Brand coinvolti</h3>
        <p className="text-sm text-gray-500 mb-3">
          Una campagna può coinvolgere uno o più dei tuoi brand. I post generati dalla campagna verranno pubblicati sui canali dei brand selezionati.
        </p>
        {brands.length === 0 ? (
          <div className="text-sm text-gray-500 italic">Nessun brand disponibile.</div>
        ) : (
          <div className="space-y-2">
            {brands.map(b => (
              <label key={b.id} className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form.brand_ids.includes(b.id)}
                  onChange={() => toggleBrand(b.id)}
                  className="w-4 h-4 text-[#3DAFA8] rounded border-gray-300 focus:ring-[#3DAFA8]"
                />
                <span className="text-sm text-gray-700">{b.name}</span>
              </label>
            ))}
          </div>
        )}
      </div>

      <div className="flex items-center justify-between">
        {isEdit && isDeletable ? (
          <button
            type="button"
            onClick={handleDelete}
            disabled={saving}
            className="inline-flex items-center gap-2 text-red-600 hover:text-red-800 disabled:opacity-50"
          >
            <Trash2 size={18} /> Elimina campagna
          </button>
        ) : <div />}
        <button
          type="submit"
          disabled={saving || !form.name.trim()}
          className="inline-flex items-center gap-2 bg-[#3DAFA8] hover:bg-[#2E8A85] disabled:opacity-50 text-white px-5 py-2 rounded-lg font-medium"
        >
          <Save size={18} /> {saving ? 'Salvataggio...' : (isEdit ? 'Salva modifiche' : 'Crea campagna')}
        </button>
      </div>
    </form>
  );
}

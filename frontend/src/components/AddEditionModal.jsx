import { useState } from 'react';
import { X, Layers, Loader2 } from 'lucide-react';
import { projects as projectsApi } from '../services/api';

export default function AddEditionModal({ project, onClose, onCreated }) {
  const [form, setForm] = useState({
    name: `${project.name} — Ed. ${(project.editions?.length || 0) + (project.edition_number ? 1 : 2)}`,
    start_date: project.end_date
      ? new Date(new Date(project.end_date).getTime() + 86400000).toISOString().split('T')[0]
      : new Date().toISOString().split('T')[0],
    end_date: project.end_date
      ? new Date(new Date(project.end_date).getTime() + 31 * 86400000).toISOString().split('T')[0]
      : new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const res = await projectsApi.addEdition(project.id, form);
      onCreated(res.data);
    } catch (err) {
      setError(err.response?.data?.message || 'Errore nella creazione dell\'edizione');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-2xl shadow-xl max-w-md w-full">
        <div className="flex items-center justify-between p-6 border-b">
          <h2 className="text-lg font-semibold text-[#2C3E50] flex items-center gap-2">
            <Layers className="text-[#3DAFA8]" size={20} />
            Nuova Edizione
          </h2>
          <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
            <X size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <p className="text-sm text-gray-500">
            La nuova edizione erediterà piattaforme, brief, personas e impostazioni
            dal calendario &quot;{project.name}&quot;.
          </p>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Nome edizione
            </label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              required
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Data inizio
              </label>
              <input
                type="date"
                value={form.start_date}
                onChange={(e) => setForm({ ...form, start_date: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Data fine
              </label>
              <input
                type="date"
                value={form.end_date}
                onChange={(e) => setForm({ ...form, end_date: e.target.value })}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                required
              />
            </div>
          </div>

          {error && (
            <p className="text-sm text-red-600 bg-red-50 rounded-lg p-3">{error}</p>
          )}

          <div className="flex justify-end gap-3 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800"
            >
              Annulla
            </button>
            <button
              type="submit"
              disabled={loading}
              className="flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] transition-colors disabled:opacity-50 text-sm"
            >
              {loading ? <Loader2 size={16} className="animate-spin" /> : <Layers size={16} />}
              Crea Edizione
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

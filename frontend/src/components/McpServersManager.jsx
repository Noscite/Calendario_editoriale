import { useState, useEffect, useCallback } from 'react';

/**
 * MCP servers manager riusato per Brand (default ereditato dalle campagne)
 * e Campaign (override / aggiunta rispetto al brand).
 *
 * Props:
 *   - scope: 'brand' | 'campaign'
 *   - listFn:   () => Promise<{data: {data: McpServer[]}}>
 *   - createFn: (payload) => Promise
 *   - deleteFn: (mcpId)   => Promise
 *   - showOverrideToggle: bool (solo per campaign)
 */
export default function McpServersManager({
  scope = 'brand',
  listFn,
  createFn,
  deleteFn,
  showOverrideToggle = false,
}) {
  const [servers, setServers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [form, setForm] = useState({
    name: '',
    url: '',
    api_key: '',
    override_brand_mcp: false,
  });

  const load = useCallback(async () => {
    try {
      const res = await listFn();
      setServers(res.data?.data || []);
    } catch (err) {
      console.error('Errore caricamento MCP servers:', err);
    } finally {
      setLoading(false);
    }
  }, [listFn]);

  useEffect(() => {
    load();
  }, [load]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!form.name.trim() || !form.url.trim()) {
      setError('Nome e URL sono obbligatori');
      return;
    }
    setSubmitting(true);
    setError(null);

    try {
      const payload = {
        name:    form.name.trim(),
        url:     form.url.trim(),
        api_key: form.api_key.trim() || null,
      };
      if (showOverrideToggle) {
        payload.override_brand_mcp = form.override_brand_mcp;
      }
      await createFn(payload);
      setForm({ name: '', url: '', api_key: '', override_brand_mcp: false });
      setShowForm(false);
      await load();
    } catch (err) {
      setError(err.response?.data?.errors?.url?.[0] || err.response?.data?.message || 'Errore creazione');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async (mcpId) => {
    if (!confirm('Eliminare questo connettore MCP?')) return;
    try {
      await deleteFn(mcpId);
      await load();
    } catch (err) {
      setError('Errore eliminazione');
    }
  };

  return (
    <div className="bg-white rounded-xl shadow-sm p-6 mt-6">
      <div className="flex items-center justify-between mb-1">
        <h3 className="font-semibold text-[#2C3E50]">
          🔌 Connettori MCP {scope === 'campaign' ? '(campagna)' : '(brand)'}
        </h3>
        <button
          onClick={() => setShowForm(!showForm)}
          className="text-sm text-[#3DAFA8] hover:text-[#2C3E50]"
        >
          {showForm ? 'Annulla' : '+ Aggiungi'}
        </button>
      </div>
      <p className="text-sm text-gray-500 mb-4">
        {scope === 'campaign'
          ? 'Connettori MCP specifici per questa campagna. Se "Override" è attivato, sostituiscono i connettori del brand.'
          : 'Connettori MCP usati come fonti dati esterne dall\'AI durante la generazione dei post delle campagne del brand.'}
      </p>

      {showForm && (
        <form onSubmit={handleSubmit} className="bg-gray-50 rounded-lg p-4 mb-4 space-y-3">
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">Nome *</label>
            <input
              type="text"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
              placeholder="Es. Catalogo prodotti"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">URL endpoint *</label>
            <input
              type="url"
              value={form.url}
              onChange={(e) => setForm({ ...form, url: e.target.value })}
              placeholder="https://mcp.example.com/sse"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
            />
          </div>
          <div>
            <label className="block text-xs font-medium text-gray-600 mb-1">
              API key <span className="text-gray-400 font-normal">(opzionale)</span>
            </label>
            <input
              type="password"
              value={form.api_key}
              onChange={(e) => setForm({ ...form, api_key: e.target.value })}
              placeholder="Token di autenticazione"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
            />
            <p className="text-xs text-gray-400 mt-1">Cifrata at-rest, mai esposta in lettura.</p>
          </div>

          {showOverrideToggle && (
            <label className="flex items-start gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={form.override_brand_mcp}
                onChange={(e) => setForm({ ...form, override_brand_mcp: e.target.checked })}
                className="mt-1"
              />
              <span className="text-sm">
                <strong>Override</strong> dei connettori del brand
                <span className="block text-xs text-gray-500">
                  Se selezionato, i connettori del brand NON saranno usati per questa campagna.
                </span>
              </span>
            </label>
          )}

          {error && <div className="text-sm text-red-600">{error}</div>}

          <div className="flex gap-2">
            <button
              type="submit"
              disabled={submitting}
              className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg text-sm hover:bg-[#2C3E50] disabled:opacity-50"
            >
              {submitting ? 'Salvataggio…' : 'Salva connettore'}
            </button>
            <button
              type="button"
              onClick={() => setShowForm(false)}
              className="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"
            >
              Annulla
            </button>
          </div>
        </form>
      )}

      {loading ? (
        <div className="text-sm text-gray-400 py-3">Caricamento…</div>
      ) : servers.length === 0 ? (
        <div className="text-sm text-gray-400 py-3">Nessun connettore configurato.</div>
      ) : (
        <ul className="divide-y divide-gray-100">
          {servers.map((s) => (
            <li key={s.id} className="py-3 flex items-center gap-3">
              <span className="text-xl">🔌</span>
              <div className="flex-1 min-w-0">
                <div className="font-medium text-sm text-[#2C3E50] truncate" title={s.name}>
                  {s.name}
                  {s.override_brand_mcp && (
                    <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700">
                      override
                    </span>
                  )}
                  {!s.is_active && (
                    <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-200 text-gray-600">
                      inattivo
                    </span>
                  )}
                </div>
                <div className="text-xs text-gray-400 truncate">{s.url}</div>
                {s.has_api_key && (
                  <div className="text-xs text-gray-400">🔑 API key cifrata</div>
                )}
              </div>
              <button
                onClick={() => handleDelete(s.id)}
                className="text-gray-400 hover:text-red-500 text-sm"
                title="Elimina"
              >
                🗑️
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

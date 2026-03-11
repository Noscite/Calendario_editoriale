import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, Key, Plus, Trash2, Copy, CheckCircle, 
  AlertCircle, Loader2, Eye, EyeOff, Shield, Clock
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

const SCOPE_OPTIONS = [
  { id: 'read', label: 'Lettura', desc: 'Visualizza brands, progetti e post' },
  { id: 'write', label: 'Scrittura', desc: 'Crea, modifica ed elimina post' },
  { id: 'publish', label: 'Pubblicazione', desc: 'Schedula e pubblica sui social' },
  { id: 'admin', label: 'Admin', desc: 'Accesso completo a tutte le funzionalità' },
];

export default function ApiKeys() {
  const navigate = useNavigate();
  const [keys, setKeys] = useState([]);
  const [loading, setLoading] = useState(true);
  const [creating, setCreating] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [message, setMessage] = useState(null);
  const [newKeyRevealed, setNewKeyRevealed] = useState(null);
  const [copied, setCopied] = useState(false);
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  
  const [form, setForm] = useState({
    name: '',
    scopes: ['read', 'write'],
    expires_in_days: null
  });
  
  const token = localStorage.getItem('token');
  
  useEffect(() => {
    fetchKeys();
  }, []);
  
  const fetchKeys = async () => {
    try {
      const res = await fetch(`${API_URL}/api-keys/`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        setKeys(await res.json());
      }
    } catch (err) {
      console.error('Error fetching API keys:', err);
    } finally {
      setLoading(false);
    }
  };
  
  const handleCreate = async (e) => {
    e.preventDefault();
    if (!form.name.trim()) {
      setMessage({ type: 'error', text: 'Inserisci un nome per la chiave' });
      return;
    }
    setCreating(true);
    setMessage(null);
    
    try {
      const res = await fetch(`${API_URL}/api-keys/`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          name: form.name,
          scopes: form.scopes,
          expires_in_days: form.expires_in_days || null
        })
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore creazione chiave');
      }
      
      const data = await res.json();
      setNewKeyRevealed(data.raw_key);
      setMessage({ type: 'success', text: 'API Key creata! Copiala ora, non sarà più visibile.' });
      setShowForm(false);
      setForm({ name: '', scopes: ['read', 'write'], expires_in_days: null });
      fetchKeys();
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setCreating(false);
    }
  };
  
  const handleRevoke = async (keyId) => {
    try {
      const res = await fetch(`${API_URL}/api-keys/${keyId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        setMessage({ type: 'success', text: 'API Key revocata con successo' });
        setDeleteConfirm(null);
        fetchKeys();
      }
    } catch (err) {
      setMessage({ type: 'error', text: 'Errore nella revoca' });
    }
  };
  
  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };
  
  const toggleScope = (scope) => {
    if (scope === 'admin') {
      setForm({ ...form, scopes: form.scopes.includes('admin') ? ['read'] : ['admin'] });
      return;
    }
    if (form.scopes.includes('admin')) return;
    const newScopes = form.scopes.includes(scope)
      ? form.scopes.filter(s => s !== scope)
      : [...form.scopes, scope];
    if (newScopes.length === 0) newScopes.push('read');
    setForm({ ...form, scopes: newScopes });
  };
  
  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('it-IT', {
      day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm">
        <div className="max-w-4xl mx-auto px-4 py-4 flex items-center gap-4">
          <button onClick={() => navigate('/profile')} className="text-gray-600 hover:text-[#3DAFA8]">
            <ArrowLeft size={24} />
          </button>
          <div>
            <h1 className="text-2xl font-bold text-[#2C3E50]">API Keys</h1>
            <p className="text-sm text-gray-500">Gestisci le chiavi di accesso per integrazioni esterne</p>
          </div>
        </div>
      </header>
      
      <main className="max-w-4xl mx-auto px-4 py-8">
        {/* Messages */}
        {message && (
          <div className={`mb-6 p-4 rounded-lg flex items-center gap-2 ${
            message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
          }`}>
            {message.type === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
            {message.text}
          </div>
        )}
        
        {/* Revealed Key Banner */}
        {newKeyRevealed && (
          <div className="mb-6 p-4 bg-amber-50 border border-amber-300 rounded-lg">
            <div className="flex items-center gap-2 mb-2">
              <AlertCircle size={20} className="text-amber-600" />
              <span className="font-semibold text-amber-800">Salva questa chiave ora!</span>
            </div>
            <p className="text-sm text-amber-700 mb-3">
              Non sarà più possibile visualizzarla. Se la perdi, dovrai crearne una nuova.
            </p>
            <div className="flex items-center gap-2">
              <code className="flex-1 bg-white px-4 py-2 rounded border border-amber-200 text-sm font-mono break-all">
                {newKeyRevealed}
              </code>
              <button
                onClick={() => copyToClipboard(newKeyRevealed)}
                className="flex items-center gap-1 px-3 py-2 bg-amber-600 text-white rounded hover:bg-amber-700 shrink-0"
              >
                {copied ? <CheckCircle size={16} /> : <Copy size={16} />}
                {copied ? 'Copiata!' : 'Copia'}
              </button>
            </div>
            <button 
              onClick={() => setNewKeyRevealed(null)}
              className="mt-3 text-sm text-amber-600 hover:text-amber-800"
            >
              Ho salvato la chiave, chiudi questo avviso
            </button>
          </div>
        )}
        
        {/* Info Box */}
        <div className="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
          <h3 className="font-semibold text-blue-800 flex items-center gap-2 mb-1">
            <Shield size={18} /> Come funzionano le API Keys
          </h3>
          <p className="text-sm text-blue-700">
            Le API Keys ti permettono di integrare il tuo calendario editoriale con servizi esterni (MCP Hub, CRM, automazioni).
            Ogni chiave ha permessi specifici e può essere revocata in qualsiasi momento.
            Usa l'header <code className="bg-blue-100 px-1 rounded">X-API-Key</code> nelle tue chiamate API.
          </p>
          <p className="text-sm text-blue-700 mt-1">
            Base URL: <code className="bg-blue-100 px-1 rounded">https://calendar.noscite.it/api/v1</code>
          </p>
        </div>
        
        {/* Create Button */}
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-lg font-semibold text-[#2C3E50]">Le tue chiavi ({keys.length})</h2>
          <button
            onClick={() => setShowForm(!showForm)}
            className="flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50]"
          >
            <Plus size={18} />
            Nuova API Key
          </button>
        </div>
        
        {/* Create Form */}
        {showForm && (
          <div className="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 className="font-semibold mb-4">Crea nuova API Key</h3>
            <form onSubmit={handleCreate} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  placeholder="Es: MCP Hub, CRM Integration, Test..."
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Permessi</label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {SCOPE_OPTIONS.map(scope => (
                    <label
                      key={scope.id}
                      className={`flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition ${
                        form.scopes.includes(scope.id)
                          ? 'border-[#3DAFA8] bg-[#3DAFA8]/5'
                          : 'border-gray-200 hover:border-gray-300'
                      } ${form.scopes.includes('admin') && scope.id !== 'admin' ? 'opacity-40' : ''}`}
                    >
                      <input
                        type="checkbox"
                        checked={form.scopes.includes(scope.id)}
                        onChange={() => toggleScope(scope.id)}
                        className="mt-0.5 accent-[#3DAFA8]"
                      />
                      <div>
                        <span className="font-medium text-sm">{scope.label}</span>
                        <p className="text-xs text-gray-500">{scope.desc}</p>
                      </div>
                    </label>
                  ))}
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Scadenza (opzionale)</label>
                <select
                  value={form.expires_in_days || ''}
                  onChange={(e) => setForm({ ...form, expires_in_days: e.target.value ? parseInt(e.target.value) : null })}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                >
                  <option value="">Nessuna scadenza</option>
                  <option value="7">7 giorni</option>
                  <option value="30">30 giorni</option>
                  <option value="90">90 giorni</option>
                  <option value="365">1 anno</option>
                </select>
              </div>
              
              <div className="flex gap-3">
                <button
                  type="submit"
                  disabled={creating}
                  className="flex items-center gap-2 px-6 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50"
                >
                  {creating ? <Loader2 className="animate-spin" size={18} /> : <Key size={18} />}
                  Genera API Key
                </button>
                <button
                  type="button"
                  onClick={() => setShowForm(false)}
                  className="px-6 py-2 border rounded-lg hover:bg-gray-50"
                >
                  Annulla
                </button>
              </div>
            </form>
          </div>
        )}
        
        {/* Keys List */}
        {keys.length === 0 ? (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <Key size={48} className="mx-auto text-gray-300 mb-4" />
            <h3 className="text-lg font-medium text-gray-500 mb-2">Nessuna API Key</h3>
            <p className="text-gray-400">Crea la tua prima chiave per iniziare ad usare le API.</p>
          </div>
        ) : (
          <div className="space-y-3">
            {keys.map(k => (
              <div key={k.id} className="bg-white rounded-xl shadow-sm p-5">
                <div className="flex items-start justify-between">
                  <div>
                    <div className="flex items-center gap-3 mb-1">
                      <Key size={18} className="text-[#3DAFA8]" />
                      <h3 className="font-semibold">{k.name}</h3>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 mb-2">
                      <code className="text-sm text-gray-500 bg-gray-100 px-2 py-0.5 rounded">{k.key_prefix}...</code>
                      {k.scopes?.map(s => (
                        <span key={s} className="text-xs px-2 py-0.5 rounded-full bg-[#3DAFA8]/10 text-[#3DAFA8] font-medium">
                          {s}
                        </span>
                      ))}
                    </div>
                    <div className="flex items-center gap-4 text-xs text-gray-400">
                      <span className="flex items-center gap-1">
                        <Clock size={12} />
                        Creata: {formatDate(k.created_at)}
                      </span>
                      {k.last_used_at && (
                        <span>Ultimo uso: {formatDate(k.last_used_at)}</span>
                      )}
                      {k.expires_at && (
                        <span className={new Date(k.expires_at) < new Date() ? 'text-red-500' : ''}>
                          Scade: {formatDate(k.expires_at)}
                        </span>
                      )}
                    </div>
                  </div>
                  
                  <div>
                    {deleteConfirm === k.id ? (
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-red-600">Confermi?</span>
                        <button
                          onClick={() => handleRevoke(k.id)}
                          className="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600"
                        >
                          Revoca
                        </button>
                        <button
                          onClick={() => setDeleteConfirm(null)}
                          className="px-3 py-1 border text-sm rounded hover:bg-gray-50"
                        >
                          No
                        </button>
                      </div>
                    ) : (
                      <button
                        onClick={() => setDeleteConfirm(k.id)}
                        className="p-2 text-gray-400 hover:text-red-500"
                        title="Revoca chiave"
                      >
                        <Trash2 size={18} />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

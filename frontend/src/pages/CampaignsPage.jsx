import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Info, Megaphone, Calendar, Building2 } from 'lucide-react';
import { campaigns as campaignsApi } from '../services/api';

const STATUS_LABELS = {
  draft:     { label: 'Bozza',          color: 'bg-gray-100 text-gray-700' },
  planning:  { label: 'Generazione…',   color: 'bg-yellow-100 text-yellow-800' },
  active:    { label: 'Attiva',         color: 'bg-green-100 text-green-800' },
  completed: { label: 'Conclusa',       color: 'bg-blue-100 text-blue-800' },
  archived:  { label: 'Archiviata',     color: 'bg-gray-200 text-gray-500' },
};

export default function CampaignsPage() {
  const [campaigns, setCampaigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    campaignsApi.list()
      .then(res => setCampaigns(res.data?.data || []))
      .catch(err => setError(err.response?.data?.detail || 'Errore caricamento campagne'))
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="p-6 text-gray-500">Caricamento...</div>;
  if (error)   return <div className="p-6 text-red-600">{error}</div>;

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-[#2C3E50]">Storico Campagne</h1>
        <p className="text-sm text-gray-500 mt-1">
          Lista delle campagne lanciate dai calendari dei tuoi project.
        </p>
      </div>

      <div className="mb-5 bg-blue-50 border border-blue-100 rounded-lg p-4 flex items-start gap-3">
        <Info size={20} className="text-blue-600 mt-0.5 shrink-0" />
        <div className="text-sm text-blue-800">
          Le campagne si creano dal calendario di un project, tramite il bottone{' '}
          <strong>Aggiungi Post → Campagna AI</strong>. Questa pagina è un archivio read-only
          dello storico.
        </div>
      </div>

      {campaigns.length === 0 && (
        <div className="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-12 text-center">
          <Megaphone size={48} className="mx-auto text-gray-400 mb-3" />
          <h3 className="font-semibold text-gray-700">Nessuna campagna ancora</h3>
          <p className="text-sm text-gray-500 mt-2">
            Apri il calendario di un project e usa <strong>Aggiungi Post → Campagna AI</strong> per lanciare la prima campagna.
          </p>
        </div>
      )}

      <div className="space-y-3">
        {campaigns.map(c => {
          const statusInfo = STATUS_LABELS[c.status] || STATUS_LABELS.draft;
          return (
            <Link
              key={c.id}
              to={`/campaign/${c.id}/edit`}
              className="block bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md hover:border-[#3DAFA8] transition-all"
            >
              <div className="flex items-start justify-between gap-4">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-3 mb-1">
                    <h3 className="font-semibold text-[#2C3E50] truncate">{c.name}</h3>
                    <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${statusInfo.color}`}>
                      {statusInfo.label}
                    </span>
                  </div>
                  {c.brief && (
                    <p className="text-sm text-gray-600 mt-1 line-clamp-2">{c.brief}</p>
                  )}
                  <div className="flex items-center gap-4 mt-2 text-xs text-gray-500 flex-wrap">
                    {(c.start_date || c.end_date) && (
                      <div className="flex items-center gap-1">
                        <Calendar size={14} />
                        {c.start_date || '—'} → {c.end_date || '—'}
                      </div>
                    )}
                    {c.brands?.length > 0 && (
                      <div className="flex items-center gap-1">
                        <Building2 size={14} />
                        {c.brands.map(b => b.name).join(', ')}
                      </div>
                    )}
                    {c.pillar && (
                      <span className="px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 font-medium">
                        {c.pillar}
                      </span>
                    )}
                  </div>
                </div>
                <span className="text-sm text-gray-400">›</span>
              </div>
            </Link>
          );
        })}
      </div>
    </div>
  );
}

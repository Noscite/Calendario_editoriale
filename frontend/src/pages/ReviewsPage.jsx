import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Star, Loader2, Search, MessageSquare, CheckCircle2, EyeOff, AlertTriangle } from 'lucide-react';
import { reviewsApi } from '../services/api';

const SENTIMENT_BADGE = {
  positive: { label: 'Positivo', cls: 'bg-emerald-100 text-emerald-700' },
  neutral:  { label: 'Neutro',   cls: 'bg-gray-100 text-gray-700' },
  negative: { label: 'Negativo', cls: 'bg-red-100 text-red-700' },
  mixed:    { label: 'Misto',    cls: 'bg-amber-100 text-amber-700' },
};

const URGENCY_BADGE = {
  medium: { label: 'Media',  cls: 'bg-amber-100 text-amber-700' },
  high:   { label: 'Urgente', cls: 'bg-red-100 text-red-700' },
};

const OPP_BADGE = {
  recovery:    { label: 'Recupero',     cls: 'bg-orange-100 text-orange-700' },
  advocacy:    { label: 'Ambassador',   cls: 'bg-purple-100 text-purple-700' },
  upsell:      { label: 'Upsell',       cls: 'bg-blue-100 text-blue-700' },
  testimonial: { label: 'Testimonial',  cls: 'bg-indigo-100 text-indigo-700' },
};

function StatusPill({ review }) {
  if (review.has_sent_reply) {
    return <span className="inline-flex items-center gap-1 text-xs text-emerald-700"><CheckCircle2 size={14} /> Inviata</span>;
  }
  if (review.has_active_draft) {
    return <span className="inline-flex items-center gap-1 text-xs text-blue-700"><MessageSquare size={14} /> Bozza pronta</span>;
  }
  if (review.status === 'ignored') {
    return <span className="inline-flex items-center gap-1 text-xs text-gray-500"><EyeOff size={14} /> Ignorata</span>;
  }
  return <span className="text-xs text-amber-700">Da rispondere</span>;
}

function Stars({ rating }) {
  return (
    <span className="inline-flex items-center gap-0.5 text-amber-500">
      {[1,2,3,4,5].map(i => <Star key={i} size={14} fill={i <= rating ? 'currentColor' : 'none'} />)}
    </span>
  );
}

export default function ReviewsPage() {
  const navigate = useNavigate();
  const [items, setItems] = useState([]);
  const [meta, setMeta] = useState(null);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ status: '', sentiment: '', urgency: '', marketing_opportunity: '', search: '' });
  const [page, setPage] = useState(1);
  const [quota, setQuota] = useState(null);

  useEffect(() => { loadQuota(); }, []);
  useEffect(() => { loadList(); }, [page, filters]);

  const loadQuota = async () => {
    try {
      const res = await reviewsApi.getQuota();
      setQuota(res.data);
    } catch (err) {
      console.error('quota', err);
    }
  };

  const loadList = async () => {
    setLoading(true);
    try {
      const params = { page, per_page: 20 };
      Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
      const res = await reviewsApi.list(params);
      setItems(res.data.data || []);
      setMeta({
        current_page: res.data.current_page,
        last_page: res.data.last_page,
        total: res.data.total,
      });
    } catch (err) {
      console.error('reviews list', err);
    } finally {
      setLoading(false);
    }
  };

  const setFilter = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
    setPage(1);
  };

  const quotaBar = useMemo(() => {
    if (!quota) return null;
    if (quota.unlimited) {
      return <span className="text-xs text-gray-500">Risposte mensili: illimitate</span>;
    }
    if (!quota.feature_enabled) {
      return <span className="text-xs text-red-600">Funzione non inclusa nel piano attuale</span>;
    }
    const pct = quota.limit ? Math.min(100, Math.round((quota.used / quota.limit) * 100)) : 0;
    return (
      <div className="text-xs">
        <div className="flex items-center justify-between gap-3 mb-1 text-gray-600">
          <span>Risposte mensili: {quota.used}/{quota.limit}</span>
          {pct >= 80 && <span className="text-amber-700">Vicino al limite</span>}
        </div>
        <div className="h-1.5 w-48 bg-gray-200 rounded-full overflow-hidden">
          <div
            className={`h-full ${pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-amber-500' : 'bg-emerald-500'}`}
            style={{ width: `${pct}%` }}
          />
        </div>
      </div>
    );
  }, [quota]);

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Recensioni</h1>
          <p className="text-sm text-gray-500 mt-1">Risposte AI alle recensioni Google del tuo brand.</p>
        </div>
        {quotaBar}
      </div>

      {/* Filtri */}
      <div className="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex flex-wrap gap-3 items-center">
        <div className="flex items-center gap-2 flex-1 min-w-[200px]">
          <Search size={16} className="text-gray-400" />
          <input
            type="text"
            placeholder="Cerca nel commento o nel nome..."
            value={filters.search}
            onChange={e => setFilter('search', e.target.value)}
            className="flex-1 outline-none text-sm"
          />
        </div>
        <select value={filters.status} onChange={e => setFilter('status', e.target.value)} className="border border-gray-200 rounded px-2 py-1.5 text-sm">
          <option value="">Tutti gli stati</option>
          <option value="new">Nuova</option>
          <option value="scored">Valutata</option>
          <option value="drafted">Bozza pronta</option>
          <option value="replied">Inviata</option>
          <option value="ignored">Ignorata</option>
        </select>
        <select value={filters.sentiment} onChange={e => setFilter('sentiment', e.target.value)} className="border border-gray-200 rounded px-2 py-1.5 text-sm">
          <option value="">Tutti i sentiment</option>
          <option value="positive">Positivo</option>
          <option value="neutral">Neutro</option>
          <option value="negative">Negativo</option>
          <option value="mixed">Misto</option>
        </select>
        <select value={filters.urgency} onChange={e => setFilter('urgency', e.target.value)} className="border border-gray-200 rounded px-2 py-1.5 text-sm">
          <option value="">Tutte le urgenze</option>
          <option value="low">Bassa</option>
          <option value="medium">Media</option>
          <option value="high">Alta</option>
        </select>
        <select value={filters.marketing_opportunity} onChange={e => setFilter('marketing_opportunity', e.target.value)} className="border border-gray-200 rounded px-2 py-1.5 text-sm">
          <option value="">Tutte le opportunità</option>
          <option value="recovery">Recupero</option>
          <option value="advocacy">Ambassador</option>
          <option value="upsell">Upsell</option>
          <option value="testimonial">Testimonial</option>
          <option value="none">Nessuna</option>
        </select>
      </div>

      {/* Lista */}
      {loading ? (
        <div className="flex items-center justify-center h-48">
          <Loader2 className="animate-spin text-[#3DAFA8]" size={28} />
        </div>
      ) : items.length === 0 ? (
        <div className="bg-white border border-gray-200 rounded-lg p-10 text-center text-gray-500">
          Nessuna recensione trovata. Le sincronizziamo automaticamente ogni 30 minuti.
        </div>
      ) : (
        <div className="bg-white border border-gray-200 rounded-lg divide-y">
          {items.map(r => {
            const sent = SENTIMENT_BADGE[r.sentiment];
            const urg  = URGENCY_BADGE[r.urgency];
            const opp  = OPP_BADGE[r.marketing_opportunity];
            return (
              <button
                key={r.id}
                onClick={() => navigate(`/reviews/${r.id}`)}
                className="w-full text-left px-4 py-3 hover:bg-gray-50 transition flex flex-col gap-2"
              >
                <div className="flex items-center gap-3">
                  <div className="w-9 h-9 rounded-full bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                    {(r.reviewer_name || '?').charAt(0).toUpperCase()}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-gray-900 truncate">{r.reviewer_name || 'Anonimo'}</span>
                      <Stars rating={r.rating} />
                      {r.brand && <span className="text-xs text-gray-500 truncate">· {r.brand.name}</span>}
                      {r.is_fake_suspect && <span className="inline-flex items-center gap-1 text-xs text-red-700"><AlertTriangle size={12} /> Sospetto fake</span>}
                    </div>
                    <p className="text-sm text-gray-600 line-clamp-2 mt-0.5">{r.comment || <em className="text-gray-400">(solo stelle)</em>}</p>
                  </div>
                  <StatusPill review={r} />
                </div>
                <div className="flex flex-wrap gap-1.5 ml-12">
                  {sent && <span className={`text-xs px-2 py-0.5 rounded ${sent.cls}`}>{sent.label}</span>}
                  {urg && <span className={`text-xs px-2 py-0.5 rounded ${urg.cls}`}>Urgenza {urg.label}</span>}
                  {opp && <span className={`text-xs px-2 py-0.5 rounded ${opp.cls}`}>{opp.label}</span>}
                  <span className="text-xs text-gray-400 ml-auto">{r.review_created_at ? new Date(r.review_created_at).toLocaleDateString('it-IT') : ''}</span>
                </div>
              </button>
            );
          })}
        </div>
      )}

      {/* Paginazione */}
      {meta && meta.last_page > 1 && (
        <div className="flex items-center justify-between mt-4 text-sm">
          <span className="text-gray-500">Pagina {meta.current_page} di {meta.last_page} ({meta.total} totali)</span>
          <div className="flex gap-2">
            <button
              disabled={page <= 1}
              onClick={() => setPage(p => p - 1)}
              className="px-3 py-1.5 border border-gray-200 rounded disabled:opacity-50 hover:bg-gray-50"
            >Indietro</button>
            <button
              disabled={page >= meta.last_page}
              onClick={() => setPage(p => p + 1)}
              className="px-3 py-1.5 border border-gray-200 rounded disabled:opacity-50 hover:bg-gray-50"
            >Avanti</button>
          </div>
        </div>
      )}
    </div>
  );
}

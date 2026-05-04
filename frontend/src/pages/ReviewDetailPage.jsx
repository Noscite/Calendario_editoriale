import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Star, Loader2, ArrowLeft, EyeOff, MessageSquare, Send, RefreshCw, AlertTriangle,
  CheckCircle2, BookOpen, Sparkles,
} from 'lucide-react';
import { reviewsApi } from '../services/api';

const TONE_OPTIONS = [
  { value: 'brand_default', label: 'Tono del brand', desc: 'Usa il tono di voce di base del brand.' },
  { value: 'empathetic',    label: 'Empatico',       desc: 'Ascolto attivo, riconoscimento dei sentimenti del cliente.' },
  { value: 'professional',  label: 'Professionale',  desc: 'Distaccato e professionale, niente familiarità.' },
  { value: 'solution',      label: 'Risolutivo',     desc: 'Focus sulla risoluzione concreta del problema.' },
  { value: 'gratitude',     label: 'Ringraziamento', desc: 'Caloroso, esprime gratitudine genuina.' },
  { value: 'formal',        label: 'Formale',        desc: 'Registro formale, lessico curato.' },
];

const STRATEGY_OPTIONS = [
  { value: '',            label: 'Suggerito da AI' },
  { value: 'recovery',    label: 'Recupero cliente' },
  { value: 'advocacy',    label: 'Ambassador' },
  { value: 'upsell',      label: 'Upsell' },
  { value: 'testimonial', label: 'Testimonial' },
  { value: 'none',        label: 'Standard' },
];

function Stars({ rating }) {
  return (
    <span className="inline-flex items-center gap-0.5 text-amber-500">
      {[1,2,3,4,5].map(i => <Star key={i} size={18} fill={i <= rating ? 'currentColor' : 'none'} />)}
    </span>
  );
}

function Badge({ children, className }) {
  return <span className={`inline-block text-xs px-2 py-1 rounded ${className}`}>{children}</span>;
}

export default function ReviewDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [review, setReview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [tone, setTone] = useState('brand_default');
  const [strategy, setStrategy] = useState('');
  const [generating, setGenerating] = useState(false);
  const [editingBody, setEditingBody] = useState('');
  const [saveTimer, setSaveTimer] = useState(null);
  const [approving, setApproving] = useState(false);
  const [errorMsg, setErrorMsg] = useState(null);

  useEffect(() => { loadReview(); }, [id]);

  const loadReview = async () => {
    setLoading(true);
    try {
      const res = await reviewsApi.get(id);
      setReview(res.data);
      const draft = (res.data.replies || []).find(r => r.status === 'draft');
      setEditingBody(draft?.body ?? '');
    } catch (err) {
      console.error('load review', err);
      setErrorMsg('Errore nel caricamento della recensione');
    } finally {
      setLoading(false);
    }
  };

  const activeDraft = useMemo(
    () => (review?.replies || []).find(r => r.status === 'draft'),
    [review],
  );
  const sentReply = useMemo(
    () => (review?.replies || []).find(r => r.status === 'sent'),
    [review],
  );
  const lastFailed = useMemo(
    () => (review?.replies || []).find(r => r.status === 'failed'),
    [review],
  );

  const handleGenerate = async () => {
    setGenerating(true);
    setErrorMsg(null);
    try {
      await reviewsApi.generateDraft(id, { tone, strategy_override: strategy || null });
      await loadReview();
    } catch (err) {
      console.error('generate', err);
      setErrorMsg(err.response?.data?.message || 'Errore nella generazione della bozza');
    } finally {
      setGenerating(false);
    }
  };

  const handleEditChange = (value) => {
    setEditingBody(value);
    if (saveTimer) clearTimeout(saveTimer);
    if (!activeDraft) return;
    const t = setTimeout(async () => {
      try {
        await reviewsApi.updateDraft(id, activeDraft.id, value);
      } catch (err) {
        console.error('update draft', err);
      }
    }, 1000);
    setSaveTimer(t);
  };

  const handleApprove = async () => {
    if (!activeDraft) return;
    if (!confirm('Approvare e inviare la risposta su Google Business Profile?')) return;
    setApproving(true);
    setErrorMsg(null);
    try {
      // Salva eventuali edit non ancora persistiti
      if (saveTimer) {
        clearTimeout(saveTimer);
        await reviewsApi.updateDraft(id, activeDraft.id, editingBody);
      }
      await reviewsApi.approveDraft(id, activeDraft.id);
      await loadReview();
    } catch (err) {
      console.error('approve', err);
      setErrorMsg(err.response?.data?.message || 'Errore nell\'invio della risposta');
    } finally {
      setApproving(false);
    }
  };

  const handleIgnore = async () => {
    if (!confirm('Ignorare questa recensione?')) return;
    try {
      await reviewsApi.ignoreReview(id);
      navigate('/reviews');
    } catch (err) {
      console.error('ignore', err);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  if (!review) return null;

  const ontology = (review.brand?.review_ontology || []).reduce((acc, t) => { acc[t.id] = t; return acc; }, {});

  return (
    <div className="p-6 max-w-7xl mx-auto">
      <button onClick={() => navigate('/reviews')} className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 mb-4">
        <ArrowLeft size={16} /> Torna alle recensioni
      </button>

      {errorMsg && (
        <div className="mb-4 bg-red-50 border border-red-200 text-red-800 rounded-lg px-4 py-2 text-sm flex items-center gap-2">
          <AlertTriangle size={16} /> {errorMsg}
        </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Colonna SX — Recensione + Scoring */}
        <div className="space-y-4">
          <div className="bg-white border border-gray-200 rounded-lg p-5">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-12 h-12 rounded-full bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] flex items-center justify-center text-white font-semibold">
                {(review.reviewer_name || '?').charAt(0).toUpperCase()}
              </div>
              <div className="flex-1">
                <div className="font-semibold text-gray-900">{review.reviewer_name || 'Anonimo'}</div>
                <div className="text-xs text-gray-500">
                  {review.review_created_at ? new Date(review.review_created_at).toLocaleString('it-IT') : ''}
                  {review.brand && <> · {review.brand.name}</>}
                </div>
              </div>
              <Stars rating={review.rating} />
            </div>
            <p className="text-gray-800 leading-relaxed whitespace-pre-wrap">
              {review.comment || <em className="text-gray-400">(senza commento, solo stelle)</em>}
            </p>
          </div>

          {review.scored_at && (
            <div className="bg-white border border-gray-200 rounded-lg p-5">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2 mb-3"><Sparkles size={16} /> Analisi AI</h3>
              <div className="flex flex-wrap gap-2 mb-3">
                {review.sentiment && <Badge className="bg-gray-100 text-gray-800">Sentiment: {review.sentiment}</Badge>}
                {review.urgency && <Badge className={review.urgency === 'high' ? 'bg-red-100 text-red-800' : review.urgency === 'medium' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}>Urgenza: {review.urgency}</Badge>}
                {review.marketing_opportunity && review.marketing_opportunity !== 'none' && (
                  <Badge className="bg-purple-100 text-purple-800">Opportunità: {review.marketing_opportunity}</Badge>
                )}
                {review.is_fake_suspect && <Badge className="bg-red-100 text-red-800">Sospetto fake</Badge>}
              </div>
              {(review.topics || []).length > 0 && (
                <div className="flex flex-wrap gap-1.5 mb-3">
                  {review.topics.map(tid => (
                    <Badge key={tid} className="bg-blue-50 text-blue-700">
                      {ontology[tid]?.label || tid}
                    </Badge>
                  ))}
                </div>
              )}
              {review.scoring_rationale && (
                <p className="text-xs text-gray-500 italic">"{review.scoring_rationale}"</p>
              )}
            </div>
          )}

          {!sentReply && (
            <button
              onClick={handleIgnore}
              className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm"
            >
              <EyeOff size={16} /> Ignora questa recensione
            </button>
          )}
        </div>

        {/* Colonna DX — Bozza risposta */}
        <div>
          {sentReply ? (
            <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-5">
              <div className="flex items-center gap-2 text-emerald-800 font-medium mb-3">
                <CheckCircle2 size={18} />
                Risposta inviata il {new Date(sentReply.sent_at).toLocaleString('it-IT')}
              </div>
              <div className="bg-white border border-emerald-200 rounded p-4 text-gray-800 whitespace-pre-wrap">
                {sentReply.body}
              </div>
            </div>
          ) : activeDraft ? (
            <div className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-semibold text-gray-900 flex items-center gap-2"><MessageSquare size={16} /> Bozza di risposta</h3>
                {activeDraft.was_edited && (
                  <span className="text-xs text-amber-700">Modificata</span>
                )}
              </div>
              <textarea
                value={editingBody}
                onChange={e => handleEditChange(e.target.value)}
                rows={8}
                className="w-full border border-gray-200 rounded p-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#3DAFA8]"
              />
              {(activeDraft.kb_chunks_used || []).length > 0 && (
                <div className="text-xs text-gray-500 flex items-center gap-2">
                  <BookOpen size={14} /> {activeDraft.kb_chunks_used.length} chunk dalla KB usati
                </div>
              )}
              <div className="flex flex-wrap gap-2 pt-2 border-t border-gray-100">
                <select value={tone} onChange={e => setTone(e.target.value)} className="border border-gray-200 rounded px-2 py-1.5 text-sm">
                  {TONE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
                <button
                  onClick={handleGenerate}
                  disabled={generating}
                  className="inline-flex items-center gap-2 px-3 py-1.5 border border-gray-300 text-gray-700 rounded text-sm hover:bg-gray-50 disabled:opacity-50"
                >
                  {generating ? <Loader2 size={14} className="animate-spin" /> : <RefreshCw size={14} />} Rigenera
                </button>
                <button
                  onClick={handleApprove}
                  disabled={approving}
                  className="inline-flex items-center gap-2 ml-auto px-4 py-1.5 bg-[#3DAFA8] text-white rounded text-sm hover:bg-[#2c8d87] disabled:opacity-50"
                >
                  {approving ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />} Approva e invia
                </button>
              </div>
            </div>
          ) : (
            <div className="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
              <h3 className="font-semibold text-gray-900 flex items-center gap-2"><Sparkles size={16} /> Genera bozza con AI</h3>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Tono di voce</label>
                <select value={tone} onChange={e => setTone(e.target.value)} className="w-full border border-gray-200 rounded px-2 py-2 text-sm">
                  {TONE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
                <p className="text-xs text-gray-500 mt-1">{TONE_OPTIONS.find(o => o.value === tone)?.desc}</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 mb-1">Strategia di marketing</label>
                <select value={strategy} onChange={e => setStrategy(e.target.value)} className="w-full border border-gray-200 rounded px-2 py-2 text-sm">
                  {STRATEGY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
                <p className="text-xs text-gray-500 mt-1">
                  {!strategy && review.marketing_opportunity ? `Suggerito: ${review.marketing_opportunity}` : 'Override manuale della strategia.'}
                </p>
              </div>

              <button
                onClick={handleGenerate}
                disabled={generating}
                className="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg text-sm font-medium hover:bg-[#2c8d87] disabled:opacity-50"
              >
                {generating ? <><Loader2 size={16} className="animate-spin" /> Generazione in corso...</> : <><Sparkles size={16} /> Genera bozza con AI</>}
              </button>

              {lastFailed && (
                <div className="text-xs text-red-700 bg-red-50 border border-red-100 rounded p-2">
                  Ultimo tentativo: {lastFailed.error_message || 'errore generazione'}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

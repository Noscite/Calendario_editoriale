import { useState, useEffect, useCallback, useRef } from 'react';
import {
  TrendingUp, Search, Globe, ShieldCheck, Users, Zap, ChevronDown, ChevronUp,
  CheckCircle, AlertTriangle, AlertCircle, Info, RefreshCw, Link2,
  Copy, ExternalLink, Clock, Plus, Trash2, Download,
} from 'lucide-react';
import api, { auditApi } from '../services/api';

const TEAL = '#3DAFA8';

const Accessibility = ({ className, style }) => (
  <svg className={className} style={style} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="4" r="1.5" /><path d="M9 9h6M12 9v6M9 15l-2 4M15 15l2 4" />
  </svg>
);

const CATEGORIES = [
  { key: 'neuromarketing',   label: 'Neuromarketing',  icon: TrendingUp,    description: 'Efficacia persuasiva — analisi visiva' },
  { key: 'seo_geo',          label: 'SEO',             icon: Search,        description: 'Visibilità Google, title, meta, schema' },
  { key: 'geo',              label: 'GEO',             icon: Globe,         description: 'Visibilità AI — Perplexity, ChatGPT, Gemini' },
  { key: 'gdpr',             label: 'GDPR',            icon: ShieldCheck,   description: 'Conformità — tracker reali' },
  { key: 'social_coherence', label: 'Coerenza Social', icon: Users,         description: 'Allineamento sito ↔ social' },
  { key: 'accessibility',    label: 'Accessibilità',   icon: Accessibility, description: 'WCAG 2.1 AA' },
  { key: 'performance',      label: 'Performance',     icon: Zap,           description: 'Core Web Vitals' },
];

const SECTORS = [
  '', 'Ristorante / Food', 'Studio legale / Notaio', 'E-commerce', 'Agenzia marketing',
  'Medico / Clinica / Dentista', 'Immobiliare', 'Palestra / Fitness',
  'Consulente / Commercialista', 'Artigiano / Officina', 'Hotel / B&B', 'Altro',
];

function getScoreConfig(score) {
  if (score == null) return { color: '#9CA3AF', label: 'N/D', bg: '#F3F4F6' };
  if (score >= 80)   return { color: '#3DAFA8', label: 'Ottimo',        bg: '#E8F7F6' };
  if (score >= 60)   return { color: '#F59E0B', label: 'Buono',         bg: '#FFFBEB' };
  if (score >= 40)   return { color: '#D4724A', label: 'Da migliorare', bg: '#FDF0EB' };
  return              { color: '#EF4444', label: 'Critico',      bg: '#FEF2F2' };
}

function ScoreRing({ score, size = 80 }) {
  const cfg = getScoreConfig(score);
  const r = 34; const c = 2 * Math.PI * r;
  const pct = score != null ? Math.min(score, 100) / 100 : 0;
  return (
    <svg width={size} height={size} viewBox="0 0 80 80">
      <circle cx="40" cy="40" r={r} fill="none" stroke="#E5E7EB" strokeWidth="7" />
      <circle cx="40" cy="40" r={r} fill="none" stroke={cfg.color} strokeWidth="7"
        strokeDasharray={`${c * pct} ${c}`} strokeLinecap="round"
        transform="rotate(-90 40 40)" style={{ transition: 'stroke-dasharray 0.8s ease' }} />
      <text x="40" y="37" textAnchor="middle" fontSize="18" fontWeight="700" fill={cfg.color}>{score ?? '—'}</text>
      <text x="40" y="49" textAnchor="middle" fontSize="8" fill="#9CA3AF">{cfg.label}</text>
    </svg>
  );
}

function AuditResult({ audit, onShareGenerated, onRerun, onDelete }) {
  const [expanded,     setExpanded]   = useState(null);
  const [sharing,      setSharing]    = useState(false);
  const [shareUrl,     setShareUrl]   = useState(audit.share_token ? `${window.location.origin}/audit/share/${audit.share_token}` : null);
  const [copied,       setCopied]     = useState(false);
  const [downloading,  setDownloading] = useState(false);

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const res = await auditApi.downloadPdf(audit.id);
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const a   = document.createElement('a');
      a.href     = url;
      a.download = `audit_${(audit.prospect_name || audit.id).replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      alert('Errore nel download PDF.');
    } finally {
      setDownloading(false);
    }
  };

  const scores = audit.scores ?? {};

  const generateShare = async () => {
    setSharing(true);
    try {
      const res = await api.post(`/audit/prospect/${audit.id}/share`);
      const url = `${window.location.origin}/audit/share/${res.data.share_token}`;
      setShareUrl(url);
      onShareGenerated?.(audit.id, res.data.share_token);
    } catch (e) {
      alert('Errore generazione link');
    } finally {
      setSharing(false);
    }
  };

  const copyLink = () => {
    navigator.clipboard.writeText(shareUrl);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const toggle = (k) => setExpanded(p => p === k ? null : k);

  return (
    <div className="space-y-5">
      {/* Score header */}
      <div className="flex flex-col sm:flex-row items-center gap-5 p-5 bg-white rounded-xl border border-gray-200">
        <ScoreRing score={scores.overall} size={90} />
        <div className="flex-1 min-w-0 text-center sm:text-left">
          <div className="flex items-center gap-2 flex-wrap">
            <p className="text-lg font-bold text-gray-900">{audit.prospect_name}</p>
            <div className="flex items-center gap-1">
              <button onClick={onRerun} title="Rilancia analisi"
                className="p-1.5 rounded-lg text-gray-400 hover:text-teal-600 hover:bg-teal-50 transition-colors">
                <RefreshCw className="w-4 h-4" />
              </button>
              <button onClick={onDelete} title="Elimina audit"
                className="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors">
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>
          <a href={audit.website_url} target="_blank" rel="noopener noreferrer"
            className="text-sm text-teal-600 hover:underline flex items-center gap-1 justify-center sm:justify-start mt-0.5">
            <ExternalLink className="w-3 h-3" />{audit.website_url}
          </a>
          {audit.prospect_sector && <p className="text-xs text-gray-400 mt-1">{audit.prospect_sector}</p>}
          <div className="flex flex-wrap gap-1.5 mt-2 justify-center sm:justify-start">
            {CATEGORIES.map(cat => scores[cat.key] != null && (
              <span key={cat.key} className="text-xs px-2 py-0.5 rounded-full font-medium"
                style={{ background: getScoreConfig(scores[cat.key]).bg, color: getScoreConfig(scores[cat.key]).color }}>
                {cat.label}: {scores[cat.key]}
              </span>
            ))}
          </div>
        </div>
        {/* Share button */}
        <div className="flex flex-col gap-2 items-end min-w-[180px]">
          {shareUrl ? (
            <div className="w-full">
              <p className="text-xs text-gray-400 mb-1">Link condivisibile</p>
              <div className="flex gap-1">
                <input readOnly value={shareUrl} className="flex-1 text-xs px-2 py-1.5 border rounded-l-lg bg-gray-50 min-w-0" />
                <button onClick={copyLink} className="px-2.5 py-1.5 bg-teal-500 text-white rounded-r-lg hover:bg-teal-600 text-xs">
                  {copied ? <CheckCircle className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                </button>
              </div>
              <button onClick={generateShare} className="text-xs text-gray-400 hover:text-gray-600 mt-1">Rigenera link</button>
            </div>
          ) : (
            <button onClick={generateShare} disabled={sharing}
              className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium text-white disabled:opacity-50"
              style={{ backgroundColor: TEAL }}>
              {sharing ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Link2 className="w-4 h-4" />}
              Genera link cliente
            </button>
          )}
          <button
            onClick={downloadPdf}
            disabled={downloading}
            title="Scarica PDF"
            className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors w-full justify-center"
          >
            {downloading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Download className="w-4 h-4" />}
            Scarica PDF
          </button>
        </div>
      </div>

      {/* Category grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
        {CATEGORIES.map(cat => {
          const s = scores[cat.key]; const cfg = getScoreConfig(s); const Icon = cat.icon;
          return (
            <div key={cat.key} className="bg-white rounded-xl border border-gray-200 p-3 space-y-2">
              <div className="flex items-center gap-1.5">
                <Icon className="w-4 h-4" style={{ color: cfg.color }} />
                <span className="text-xs font-semibold text-gray-600">{cat.label}</span>
              </div>
              <div className="text-2xl font-bold" style={{ color: cfg.color }}>{s ?? '—'}</div>
              <div className="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div className="h-full rounded-full" style={{ width: `${s ?? 0}%`, backgroundColor: cfg.color }} />
              </div>
            </div>
          );
        })}
      </div>

      {/* Executive Summary */}
      {audit.executive_summary && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <button onClick={() => toggle('summary')} className="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 text-left">
            <span className="font-semibold text-gray-800">Executive Summary</span>
            {expanded === 'summary' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
          </button>
          {expanded === 'summary' && (
            <div className="p-4 border-t border-gray-100">
              <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{audit.executive_summary}</p>
            </div>
          )}
        </div>
      )}

      {/* NeuroVision */}
      {audit.analyzer_results?.neuromarketing?.vision?.above_fold_verdict && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <button onClick={() => toggle('neuro')} className="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 text-left">
            <span className="font-semibold text-gray-800 flex items-center gap-2">
              <TrendingUp className="w-4 h-4" style={{ color: TEAL }} />
              Neuromarketing Visivo
              <span className="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 font-medium">Vision AI</span>
            </span>
            {expanded === 'neuro' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
          </button>
          {expanded === 'neuro' && (() => {
            const v = audit.analyzer_results.neuromarketing.vision;
            return (
              <div className="p-4 border-t border-gray-100 space-y-4">
                <div className="p-3 rounded-lg bg-teal-50 border border-teal-100">
                  <p className="text-sm text-teal-800 font-medium">{v.above_fold_verdict}</p>
                </div>
                {[
                  ['Gerarchia visiva','hierarchy_score'],['CTA','cta_score'],['Val. Prop.','value_prop_score'],
                  ['Social Proof','social_proof_score'],['Coerenza brand','brand_coherence_score'],['Mobile UX','mobile_score'],
                ].map(([lbl, key]) => v[key] != null && (
                  <div key={key} className="flex items-center gap-3">
                    <span className="text-xs text-gray-500 w-32 flex-shrink-0">{lbl}</span>
                    <div className="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                      <div className="h-full rounded-full" style={{ width: `${v[key]}%`, backgroundColor: getScoreConfig(v[key]).color }} />
                    </div>
                    <span className="text-xs font-medium w-7 text-right" style={{ color: getScoreConfig(v[key]).color }}>{v[key]}</span>
                  </div>
                ))}
                {(v.critical_improvements || []).length > 0 && (
                  <div>
                    <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Miglioramenti critici</p>
                    {v.critical_improvements.map((imp, i) => (
                      <div key={i} className="p-3 rounded-lg bg-orange-50 border border-orange-100 text-sm mb-2">
                        <p className="font-semibold text-orange-700">{imp.element}</p>
                        <p className="text-gray-600 text-xs mt-1"><strong>Ora:</strong> {imp.current}</p>
                        <p className="text-gray-600 text-xs"><strong>Suggerito:</strong> {imp.suggested}</p>
                        {imp.impact && <p className="text-gray-500 text-xs italic mt-1">{imp.impact}</p>}
                      </div>
                    ))}
                  </div>
                )}
                {v.sector_specific_notes && (
                  <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                    <p className="text-xs font-semibold text-gray-500 uppercase mb-1">Note settore</p>
                    <p className="text-sm text-gray-700">{v.sector_specific_notes}</p>
                  </div>
                )}
              </div>
            );
          })()}
        </div>
      )}

      {/* Piano d'azione */}
      {(audit.recommendations || []).length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <button onClick={() => toggle('recs')} className="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 text-left">
            <span className="font-semibold text-gray-800">Piano d'azione ({audit.recommendations.length})</span>
            {expanded === 'recs' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
          </button>
          {expanded === 'recs' && (
            <div className="p-4 border-t border-gray-100 space-y-3">
              {audit.recommendations.map((rec, i) => (
                <div key={i} className="flex gap-3 p-3 rounded-lg bg-gray-50">
                  <span className="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0 text-white"
                    style={{ backgroundColor: TEAL }}>{rec.priority ?? i + 1}</span>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <span className="text-xs font-semibold text-gray-500 uppercase">{rec.area}</span>
                      {rec.effort && <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${rec.effort==='basso'?'bg-green-100 text-green-700':rec.effort==='medio'?'bg-yellow-100 text-yellow-700':'bg-red-100 text-red-700'}`}>{rec.effort}</span>}
                      {rec.impact && <span className="text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-medium">impatto {rec.impact}</span>}
                    </div>
                    <p className="text-sm text-gray-700">{rec.action}</p>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Problemi critici */}
      {(audit.critical_issues || []).length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <button onClick={() => toggle('issues')} className="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 text-left">
            <span className="font-semibold text-gray-800 flex items-center gap-2">
              <AlertCircle className="w-4 h-4 text-red-500" />
              Problemi critici ({audit.critical_issues.length})
            </span>
            {expanded === 'issues' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
          </button>
          {expanded === 'issues' && (
            <div className="p-4 border-t border-gray-100 space-y-3">
              {audit.critical_issues.map((issue, i) => (
                <div key={i} className="flex gap-3">
                  <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />
                  <div>
                    <p className="text-xs font-semibold text-gray-500 uppercase">{issue.area}</p>
                    <p className="text-sm text-gray-700">{issue.message}</p>
                    {issue.impact && <p className="text-xs text-gray-400 italic mt-0.5">{issue.impact}</p>}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default function AuditProspectPage() {
  const [form,         setForm]         = useState({ url: '', name: '', sector: '' });
  const [submitting,   setSubmitting]   = useState(false);
  const [formError,    setFormError]    = useState(null);
  const [audits,       setAudits]       = useState([]);
  const [loading,      setLoading]      = useState(true);
  const [activeAudit,  setActiveAudit]  = useState(null);
  const pollRef        = useRef({});
  const activeAuditIdRef = useRef(null);

  const loadHistory = useCallback(async () => {
    try {
      const res = await api.get('/audit/prospects');
      setAudits(res.data);
    } catch (_) {}
    finally { setLoading(false); }
  }, []);

  useEffect(() => { loadHistory(); }, [loadHistory]);

  const pollAudit = useCallback(async (id) => {
    if (pollRef.current[id]) return;
    pollRef.current[id] = true;

    const interval = setInterval(async () => {
      try {
        const res = await api.get(`/audit/prospect/${id}`);
        const data = res.data;
        const normalizedStatus = data.status?.value ?? data.status;
        const normalized = { ...data, status: normalizedStatus };
        setAudits(prev => prev.map(a => a.id === id ? { ...a, ...normalized } : a));
        if (activeAuditIdRef.current === id) setActiveAudit(normalized);
        if (!['pending', 'running'].includes(normalizedStatus)) {
          clearInterval(interval);
          delete pollRef.current[id];
        }
      } catch (_) {
        clearInterval(interval);
        delete pollRef.current[id];
      }
    }, 6000);
  }, []);

  // Auto-poll any pending/running audits on load
  useEffect(() => {
    audits.forEach(a => {
      if (['pending', 'running'].includes(a.status)) pollAudit(a.id);
    });
  }, [audits.length]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setFormError(null);
    if (!form.url || !form.name) { setFormError('URL e nome azienda sono obbligatori.'); return; }
    setSubmitting(true);
    try {
      const res = await api.post('/audit/prospect', {
        url:    form.url,
        name:   form.name,
        sector: form.sector || null,
      });
      const newAudit = {
        id:              res.data.audit_id,
        prospect_name:   form.name,
        prospect_sector: form.sector || null,
        website_url:     form.url,
        status:          'pending',
        score_overall:   null,
      };
      setAudits(prev => [newAudit, ...prev]);
      activeAuditIdRef.current = res.data.audit_id;
      setActiveAudit(newAudit);
      setForm({ url: '', name: '', sector: '' });
      pollAudit(res.data.audit_id);
    } catch (err) {
      setFormError(err.response?.data?.error ?? 'Errore avvio audit.');
    } finally {
      setSubmitting(false);
    }
  };

  const openAudit = async (id) => {
    try {
      const res = await api.get(`/audit/prospect/${id}`);
      activeAuditIdRef.current = id;
      setActiveAudit(res.data);
    } catch (_) {}
  };

  const handleShareGenerated = (id, token) => {
    setAudits(prev => prev.map(a => a.id === id ? { ...a, share_token: token } : a));
  };

  const handleDelete = async (e, id) => {
    e.stopPropagation();
    if (!confirm('Eliminare questo audit?')) return;
    try {
      await api.delete(`/audit/prospect/${id}`);
      setAudits(prev => prev.filter(a => a.id !== id));
      if (activeAuditIdRef.current === id) {
        activeAuditIdRef.current = null;
        setActiveAudit(null);
      }
    } catch (_) { alert('Errore durante l\'eliminazione.'); }
  };

  const handleRerun = async (e, id) => {
    e.stopPropagation();
    try {
      await api.post(`/audit/prospect/${id}/rerun`);
      const reset = { status: 'pending', score_overall: null, scores: null,
        executive_summary: null, critical_issues: [], recommendations: [] };
      setAudits(prev => prev.map(a => a.id === id ? { ...a, ...reset } : a));
      if (activeAuditIdRef.current === id) setActiveAudit(prev => ({ ...prev, ...reset }));
      pollAudit(id);
    } catch (err) { alert(err.response?.data?.error ?? 'Errore durante il rilancio.'); }
  };

  const statusBadge = (status) => {
    const s = status?.value ?? status;
    if (s === 'completed') return <span className="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Completato</span>;
    if (s === 'running' || s === 'pending') return <span className="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 font-medium flex items-center gap-1"><RefreshCw className="w-3 h-3 animate-spin" />In corso</span>;
    if (s === 'failed') return <span className="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Fallito</span>;
    return null;
  };

  return (
    <div className="max-w-7xl mx-auto">
      <div className="flex gap-6">

        {/* Left: form + history */}
        <div className="w-80 flex-shrink-0 space-y-4">

          {/* Form */}
          <div className="bg-white rounded-xl shadow-sm p-5">
            <div className="flex items-center gap-2 mb-4">
              <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ background: '#E8F7F6' }}>
                <TrendingUp className="w-4 h-4" style={{ color: TEAL }} />
              </div>
              <div>
                <h2 className="font-bold text-gray-900 text-sm">Nuovo Audit</h2>
                <p className="text-xs text-gray-400">Analisi preventiva per un cliente</p>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">URL sito web *</label>
                <input
                  type="url" required
                  value={form.url}
                  onChange={e => setForm(f => ({ ...f, url: e.target.value }))}
                  placeholder="https://esempio.it"
                  className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-teal-400 focus:border-teal-400"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Nome azienda *</label>
                <input
                  type="text" required
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  placeholder="Es. Pizzeria Da Mario"
                  className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-teal-400 focus:border-teal-400"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Settore</label>
                <select
                  value={form.sector}
                  onChange={e => setForm(f => ({ ...f, sector: e.target.value }))}
                  className="w-full px-3 py-2 text-sm border rounded-lg focus:ring-2 focus:ring-teal-400 focus:border-teal-400 bg-white"
                >
                  {SECTORS.map(s => <option key={s} value={s}>{s || '— Seleziona —'}</option>)}
                </select>
              </div>

              {formError && (
                <div className="p-2.5 rounded-lg bg-red-50 border border-red-200 text-xs text-red-700">{formError}</div>
              )}

              <button type="submit" disabled={submitting}
                className="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-sm font-medium text-white disabled:opacity-50"
                style={{ backgroundColor: TEAL }}>
                {submitting ? <><RefreshCw className="w-4 h-4 animate-spin" />Avvio…</> : <><Plus className="w-4 h-4" />Avvia audit</>}
              </button>
            </form>
          </div>

          {/* History */}
          <div className="bg-white rounded-xl shadow-sm p-4">
            <h3 className="text-sm font-semibold text-gray-700 mb-3">Audit eseguiti</h3>
            {loading ? (
              <div className="flex justify-center py-6"><RefreshCw className="w-5 h-5 animate-spin text-gray-300" /></div>
            ) : audits.length === 0 ? (
              <p className="text-xs text-gray-400 text-center py-6">Nessun audit ancora.<br />Inserisci un URL per iniziare.</p>
            ) : (
              <div className="space-y-2">
                {audits.map(a => {
                  const isActive = activeAudit?.id === a.id;
                  const cfg = getScoreConfig(a.score_overall);
                  return (
                    <div key={a.id}
                      className={`group relative w-full text-left p-3 rounded-lg border transition-all cursor-pointer ${isActive ? 'border-teal-400 bg-teal-50' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'}`}
                      onClick={() => openAudit(a.id)}>
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-gray-800 truncate">{a.prospect_name}</p>
                          <p className="text-xs text-gray-400 truncate">{a.website_url}</p>
                        </div>
                        <div className="flex items-center gap-1 flex-shrink-0">
                          {a.score_overall != null ? (
                            <span className="text-sm font-bold" style={{ color: cfg.color }}>{a.score_overall}</span>
                          ) : statusBadge(a.status)}
                          {/* Actions — visible on hover */}
                          {!['pending','running'].includes(a.status?.value ?? a.status) && (
                            <div className="hidden group-hover:flex items-center gap-0.5 ml-1">
                              <button
                                onClick={(e) => handleRerun(e, a.id)}
                                className="p-1 rounded hover:bg-teal-100 text-gray-400 hover:text-teal-600 transition-colors"
                                title="Rilancia audit">
                                <RefreshCw className="w-3.5 h-3.5" />
                              </button>
                              <button
                                onClick={(e) => handleDelete(e, a.id)}
                                className="p-1 rounded hover:bg-red-100 text-gray-400 hover:text-red-500 transition-colors"
                                title="Elimina">
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                      {a.share_token && (
                        <p className="text-xs text-teal-600 mt-1 flex items-center gap-1"><Link2 className="w-3 h-3" />Link attivo</p>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Right: result panel */}
        <div className="flex-1 min-w-0">
          {!activeAudit ? (
            <div className="bg-white rounded-xl shadow-sm flex flex-col items-center justify-center h-96 text-center p-8">
              <div className="w-16 h-16 rounded-2xl flex items-center justify-center mb-4" style={{ background: '#E8F7F6' }}>
                <TrendingUp className="w-8 h-8" style={{ color: TEAL }} />
              </div>
              <h3 className="text-lg font-bold text-gray-800 mb-2">Audit preventivo</h3>
              <p className="text-sm text-gray-500 max-w-xs">
                Inserisci l'URL di un potenziale cliente, avvia l'analisi e ottieni un report professionale da condividere.
              </p>
              <div className="mt-6 grid grid-cols-3 gap-3 text-center w-full max-w-sm">
                {[
                  { icon: Search, label: 'SEO & Performance' },
                  { icon: ShieldCheck, label: 'GDPR & Privacy' },
                  { icon: TrendingUp, label: 'Neuromarketing AI' },
                ].map(({ icon: Icon, label }) => (
                  <div key={label} className="p-3 rounded-lg bg-gray-50">
                    <Icon className="w-5 h-5 mx-auto mb-1 text-gray-400" />
                    <p className="text-xs text-gray-500">{label}</p>
                  </div>
                ))}
              </div>
            </div>
          ) : ['pending', 'running'].includes(activeAudit.status?.value ?? activeAudit.status) ? (
            <div className="bg-white rounded-xl shadow-sm p-8 flex flex-col items-center justify-center h-64 text-center">
              <RefreshCw className="w-10 h-10 animate-spin mb-4" style={{ color: TEAL }} />
              <p className="font-semibold text-gray-800">Analisi in corso per <strong>{activeAudit.prospect_name}</strong></p>
              <p className="text-sm text-gray-500 mt-1">SEO, GDPR, accessibilità, performance, neuromarketing visivo…</p>
              <p className="text-xs text-gray-400 mt-2">Tempo stimato: 2-3 minuti</p>
            </div>
          ) : (activeAudit.status?.value ?? activeAudit.status) === 'failed' ? (
            <div className="bg-white rounded-xl shadow-sm p-8 text-center">
              <AlertCircle className="w-10 h-10 text-red-400 mx-auto mb-3" />
              <p className="font-semibold text-gray-800">Audit fallito</p>
              {activeAudit.error && <p className="text-sm text-red-500 mt-1">{activeAudit.error}</p>}
              <button onClick={(e) => handleRerun(e, activeAudit.id)}
                className="mt-4 flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white mx-auto"
                style={{ backgroundColor: TEAL }}>
                <RefreshCw className="w-4 h-4" />Rilancia analisi
              </button>
            </div>
          ) : (
            <AuditResult
              audit={activeAudit}
              onShareGenerated={handleShareGenerated}
              onRerun={(e) => handleRerun(e ?? { stopPropagation: () => {} }, activeAudit.id)}
              onDelete={(e) => handleDelete(e ?? { stopPropagation: () => {} }, activeAudit.id)}
            />
          )}
        </div>
      </div>
    </div>
  );
}

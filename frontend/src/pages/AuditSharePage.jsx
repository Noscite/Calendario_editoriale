import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import {
  TrendingUp, Search, Globe, ShieldCheck, Users, Zap,
  ChevronDown, ChevronUp, CheckCircle, AlertCircle,
  ExternalLink, RefreshCw,
} from 'lucide-react';
import axios from 'axios';

const TEAL = '#3DAFA8';

const Accessibility = ({ className, style }) => (
  <svg className={className} style={style} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="4" r="1.5" /><path d="M9 9h6M12 9v6M9 15l-2 4M15 15l2 4" />
  </svg>
);

const CATEGORIES = [
  { key: 'neuromarketing',   label: 'Neuromarketing',  icon: TrendingUp },
  { key: 'seo_geo',          label: 'SEO',             icon: Search },
  { key: 'geo',              label: 'GEO',             icon: Globe },
  { key: 'gdpr',             label: 'GDPR',            icon: ShieldCheck },
  { key: 'social_coherence', label: 'Coerenza Social', icon: Users },
  { key: 'accessibility',    label: 'Accessibilità',   icon: Accessibility },
  { key: 'performance',      label: 'Performance',     icon: Zap },
];

function getScoreConfig(score) {
  if (score == null) return { color: '#9CA3AF', label: 'N/D', bg: '#F3F4F6' };
  if (score >= 80)   return { color: '#3DAFA8', label: 'Ottimo',        bg: '#E8F7F6' };
  if (score >= 60)   return { color: '#F59E0B', label: 'Buono',         bg: '#FFFBEB' };
  if (score >= 40)   return { color: '#D4724A', label: 'Da migliorare', bg: '#FDF0EB' };
  return              { color: '#EF4444', label: 'Critico',      bg: '#FEF2F2' };
}

function ScoreRing({ score, size = 100 }) {
  const cfg = getScoreConfig(score);
  const r = 38; const c = 2 * Math.PI * r;
  const pct = score != null ? Math.min(score, 100) / 100 : 0;
  return (
    <svg width={size} height={size} viewBox="0 0 100 100">
      <circle cx="50" cy="50" r={r} fill="none" stroke="#E5E7EB" strokeWidth="8" />
      <circle cx="50" cy="50" r={r} fill="none" stroke={cfg.color} strokeWidth="8"
        strokeDasharray={`${c * pct} ${c}`} strokeLinecap="round"
        transform="rotate(-90 50 50)" style={{ transition: 'stroke-dasharray 1s ease' }} />
      <text x="50" y="46" textAnchor="middle" fontSize="22" fontWeight="700" fill={cfg.color}>{score ?? '—'}</text>
      <text x="50" y="60" textAnchor="middle" fontSize="9" fill="#9CA3AF">{cfg.label}</text>
    </svg>
  );
}

export default function AuditSharePage() {
  const { token }   = useParams();
  const [audit,     setAudit]     = useState(null);
  const [loading,   setLoading]   = useState(true);
  const [notFound,  setNotFound]  = useState(false);
  const [expanded,  setExpanded]  = useState(null);

  useEffect(() => {
    axios.get(`/api/audit/share/${token}`)
      .then(res => setAudit(res.data))
      .catch(() => setNotFound(true))
      .finally(() => setLoading(false));
  }, [token]);

  const toggle = (k) => setExpanded(p => p === k ? null : k);

  if (loading) return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center">
      <RefreshCw className="w-8 h-8 animate-spin text-teal-500" />
    </div>
  );

  if (notFound) return (
    <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center text-center p-8">
      <AlertCircle className="w-14 h-14 text-gray-300 mb-4" />
      <h1 className="text-xl font-bold text-gray-700">Report non trovato</h1>
      <p className="text-sm text-gray-400 mt-2">Il link potrebbe essere scaduto o non valido.</p>
    </div>
  );

  const scores = audit.scores ?? {};

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Top bar */}
      <div className="bg-[#1a1f2e] text-white px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-teal-500 to-slate-700 flex items-center justify-center">
            <TrendingUp className="w-5 h-5 text-white" />
          </div>
          <div>
            <p className="font-bold text-sm">Digital Audit Report</p>
            <p className="text-xs text-gray-400">powered by Noscite</p>
          </div>
        </div>
        <a href={audit.website_url} target="_blank" rel="noopener noreferrer"
          className="text-xs text-teal-400 hover:text-teal-300 flex items-center gap-1">
          <ExternalLink className="w-3 h-3" />{audit.website_url}
        </a>
      </div>

      <div className="max-w-3xl mx-auto px-4 py-8 space-y-6">

        {/* Hero score */}
        <div className="bg-white rounded-2xl shadow-sm p-6 flex flex-col sm:flex-row items-center gap-6">
          <ScoreRing score={scores.overall} size={110} />
          <div className="flex-1 text-center sm:text-left">
            <h1 className="text-2xl font-bold text-gray-900">{audit.prospect_name}</h1>
            {audit.prospect_sector && <p className="text-sm text-gray-400 mt-0.5">{audit.prospect_sector}</p>}
            <p className="text-sm font-medium mt-2" style={{ color: getScoreConfig(scores.overall).color }}>
              {getScoreConfig(scores.overall).label} — Score digitale complessivo
            </p>
            <p className="text-xs text-gray-400 mt-1">
              Analisi eseguita il {audit.completed_at ? new Date(audit.completed_at).toLocaleDateString('it-IT', { day:'2-digit',month:'long',year:'numeric'}) : '—'}
            </p>
          </div>
        </div>

        {/* Category grid */}
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {CATEGORIES.map(cat => {
            const s = scores[cat.key]; const cfg = getScoreConfig(s); const Icon = cat.icon;
            return (
              <div key={cat.key} className="bg-white rounded-xl border border-gray-200 p-4 space-y-2">
                <div className="flex items-center gap-1.5">
                  <Icon className="w-4 h-4" style={{ color: cfg.color }} />
                  <span className="text-xs font-semibold text-gray-600">{cat.label}</span>
                </div>
                <div className="text-3xl font-bold" style={{ color: cfg.color }}>{s ?? '—'}</div>
                <div className="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                  <div className="h-full rounded-full transition-all duration-700"
                    style={{ width: `${s ?? 0}%`, backgroundColor: cfg.color }} />
                </div>
                <p className="text-xs font-medium" style={{ color: cfg.color }}>{cfg.label}</p>
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
              <div className="p-5 border-t border-gray-100">
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
                Analisi Neuromarketing Visiva
                <span className="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 font-medium">Vision AI</span>
              </span>
              {expanded === 'neuro' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
            </button>
            {expanded === 'neuro' && (() => {
              const v = audit.analyzer_results.neuromarketing.vision;
              return (
                <div className="p-5 border-t border-gray-100 space-y-4">
                  <div className="p-3 rounded-lg bg-teal-50 border border-teal-100">
                    <p className="text-sm text-teal-800 font-medium">{v.above_fold_verdict}</p>
                  </div>
                  {[
                    ['Gerarchia visiva','hierarchy_score'],['CTA principale','cta_score'],
                    ['Proposta di valore','value_prop_score'],['Social Proof','social_proof_score'],
                    ['Coerenza brand','brand_coherence_score'],['Mobile UX','mobile_score'],
                  ].map(([lbl, key]) => v[key] != null && (
                    <div key={key} className="flex items-center gap-3">
                      <span className="text-xs text-gray-500 w-36 flex-shrink-0">{lbl}</span>
                      <div className="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                        <div className="h-full rounded-full transition-all duration-700"
                          style={{ width: `${v[key]}%`, backgroundColor: getScoreConfig(v[key]).color }} />
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
                      <p className="text-xs font-semibold text-gray-500 uppercase mb-1">Benchmark settore</p>
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
              <span className="font-semibold text-gray-800">Piano d'azione ({audit.recommendations.length} azioni)</span>
              {expanded === 'recs' ? <ChevronUp className="w-4 h-4 text-gray-400" /> : <ChevronDown className="w-4 h-4 text-gray-400" />}
            </button>
            {expanded === 'recs' && (
              <div className="p-5 border-t border-gray-100 space-y-3">
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
              <div className="p-5 border-t border-gray-100 space-y-3">
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

        {/* CTA finale */}
        <div className="bg-[#1a1f2e] rounded-2xl p-6 text-center text-white">
          <p className="text-sm text-gray-400 mb-1">Report generato da</p>
          <p className="font-bold text-lg">Noscite — Digital Audit AI</p>
          <p className="text-xs text-gray-400 mt-2">Analisi SEO · GDPR · Accessibilità · Performance · Neuromarketing Vision AI</p>
        </div>
      </div>
    </div>
  );
}

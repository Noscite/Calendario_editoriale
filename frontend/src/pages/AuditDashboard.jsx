import { useState, useEffect, useCallback } from 'react';
import {
  TrendingUp, Search, ShieldCheck, Users, Eye, Zap,
  ChevronDown, ChevronUp, CheckCircle, AlertTriangle,
  AlertCircle, Info, RefreshCw, ExternalLink, Clock, Link2, Download,
} from 'lucide-react';
import api, { auditApi } from '../services/api';
import SectorConfirmModal from '../components/SectorConfirmModal';

const TEAL = '#3DAFA8';

// Icon placeholder for Accessibility
const Accessibility = ({ className, style }) => (
  <svg className={className} style={style} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
    <circle cx="12" cy="4" r="1.5" />
    <path d="M9 9h6M12 9v6M9 15l-2 4M15 15l2 4" />
  </svg>
);

const CATEGORIES = [
  { key: 'neuromarketing',   label: 'Neuromarketing',  icon: TrendingUp,    description: 'Efficacia persuasiva — analisi visiva above-fold' },
  { key: 'seo_geo',          label: 'SEO & GEO',       icon: Search,        description: 'Visibilità Google, performance, SSL' },
  { key: 'gdpr',             label: 'GDPR',            icon: ShieldCheck,   description: 'Conformità GDPR — intercettazione tracker reale' },
  { key: 'social_coherence', label: 'Coerenza Social', icon: Users,         description: 'Allineamento tra sito e profili social' },
  { key: 'accessibility',    label: 'Accessibilità',   icon: Accessibility, description: 'WCAG 2.1 AA — 50+ check automatici' },
  { key: 'performance',      label: 'Performance',     icon: Zap,           description: 'Core Web Vitals — Google PageSpeed Insights' },
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
  const r   = 38;
  const c   = 2 * Math.PI * r;
  const pct = score != null ? Math.min(score, 100) / 100 : 0;

  return (
    <svg width={size} height={size} viewBox="0 0 100 100">
      <circle cx="50" cy="50" r={r} fill="none" stroke="#E5E7EB" strokeWidth="8" />
      <circle
        cx="50" cy="50" r={r} fill="none"
        stroke={cfg.color} strokeWidth="8"
        strokeDasharray={`${c * pct} ${c}`}
        strokeLinecap="round"
        transform="rotate(-90 50 50)"
        style={{ transition: 'stroke-dasharray 0.8s ease' }}
      />
      <text x="50" y="46" textAnchor="middle" fontSize="20" fontWeight="700" fill={cfg.color}>
        {score ?? '—'}
      </text>
      <text x="50" y="60" textAnchor="middle" fontSize="9" fill="#9CA3AF">{cfg.label}</text>
    </svg>
  );
}

function SeverityIcon({ severity }) {
  const map = {
    critical: <AlertCircle className="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" />,
    warning:  <AlertTriangle className="w-4 h-4 text-yellow-500 flex-shrink-0 mt-0.5" />,
    info:     <Info className="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" />,
  };
  return map[severity] ?? map.info;
}

export default function AuditDashboard({ brandId }) {
  const [auditData,       setAuditData]       = useState(null);
  const [loading,         setLoading]         = useState(true);
  const [starting,        setStarting]        = useState(false);
  const [detecting,       setDetecting]       = useState(false);
  const [sectorModal,     setSectorModal]     = useState(null); // { detectionResult }
  const [polling,         setPolling]         = useState(false);
  const [expandedSection, setExpandedSection] = useState(null);
  const [error,           setError]           = useState(null);
  const [shareLabel,      setShareLabel]      = useState('Condividi');
  const [downloading,     setDownloading]     = useState(false);

  const fetchLatest = useCallback(async () => {
    try {
      const res = await api.get(`/brands/${brandId}/audit/latest`);
      setAuditData(res.data);
      return res.data;
    } catch (e) {
      setError('Impossibile caricare il report audit.');
    } finally {
      setLoading(false);
    }
  }, [brandId]);

  useEffect(() => { fetchLatest(); }, [fetchLatest]);

  // Poll while running/pending
  useEffect(() => {
    if (!auditData || !['pending', 'running'].includes(auditData.status)) return;
    setPolling(true);
    const id = setInterval(async () => {
      const fresh = await fetchLatest();
      if (fresh && !['pending', 'running'].includes(fresh.status)) {
        clearInterval(id);
        setPolling(false);
      }
    }, 6000);
    return () => { clearInterval(id); setPolling(false); };
  }, [auditData?.status, fetchLatest]);

  const handleStartClick = async () => {
    setDetecting(true);
    setError(null);
    try {
      const res = await auditApi.detectSectorForBrand(brandId);
      setSectorModal({ detectionResult: res.data });
    } catch {
      // Fallback: open modal with no detection
      setSectorModal({ detectionResult: null });
    } finally {
      setDetecting(false);
    }
  };

  const startAudit = async (sectorData = null) => {
    setStarting(true);
    setError(null);
    try {
      await auditApi.start(brandId, sectorData);
      await fetchLatest();
    } catch (e) {
      setError(e.response?.data?.error ?? 'Errore avvio audit.');
    } finally {
      setStarting(false);
    }
  };

  const handleShareAudit = async () => {
    if (!auditData?.id) return;
    try {
      const res = await auditApi.generateShareLink(auditData.id);
      await navigator.clipboard.writeText(res.data.share_url);
      setShareLabel('✓ Copiato!');
      setTimeout(() => setShareLabel('Condividi'), 2500);
    } catch {
      setError('Errore nella generazione del link di condivisione.');
    }
  };

  const handleDownloadPdf = async () => {
    if (!auditData?.id) return;
    setDownloading(true);
    try {
      const res = await auditApi.downloadPdf(auditData.id);
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const a = document.createElement('a');
      a.href = url;
      a.download = `audit_${brandId}_${new Date().toISOString().slice(0, 10)}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      setError('Errore nel download PDF.');
    } finally {
      setDownloading(false);
    }
  };

  const toggleSection = (key) =>
    setExpandedSection(prev => prev === key ? null : key);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-48">
        <RefreshCw className="w-6 h-6 animate-spin text-teal-500" />
      </div>
    );
  }

  const isPending   = auditData?.status === 'pending';
  const isRunning   = auditData?.status === 'running';
  const isCompleted = auditData?.status === 'completed';
  const isFailed    = auditData?.status === 'failed';
  const scores      = auditData?.scores ?? {};

  return (
    <div className="space-y-6">

      {/* Header */}
      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h2 className="text-xl font-bold text-gray-900">Digital Audit</h2>
          {auditData?.completed_at && (
            <p className="text-xs text-gray-400 mt-0.5 flex items-center gap-1">
              <Clock className="w-3 h-3" />
              {new Date(auditData.completed_at).toLocaleString('it-IT')}
            </p>
          )}
        </div>
        <div className="flex items-center gap-2">
          {isCompleted && (
            <>
              <button
                onClick={handleShareAudit}
                className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:border-teal-300 hover:text-teal-700 transition-colors"
              >
                <Link2 className="w-4 h-4" /> {shareLabel}
              </button>
              <button
                onClick={handleDownloadPdf}
                disabled={downloading}
                className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50 disabled:opacity-50 transition-colors"
              >
                {downloading
                  ? <RefreshCw className="w-4 h-4 animate-spin" />
                  : <Download className="w-4 h-4" />}
                PDF
              </button>
            </>
          )}
          <button
            onClick={handleStartClick}
            disabled={starting || detecting || isPending || isRunning}
            className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-all disabled:opacity-50"
            style={{ backgroundColor: TEAL }}
          >
            {(isPending || isRunning) ? (
              <><RefreshCw className="w-4 h-4 animate-spin" /> Analisi in corso…</>
            ) : detecting ? (
              <><RefreshCw className="w-4 h-4 animate-spin" /> Rilevamento settore…</>
            ) : starting ? (
              <><RefreshCw className="w-4 h-4 animate-spin" /> Avvio…</>
            ) : (
              <><TrendingUp className="w-4 h-4" /> {auditData ? 'Riesegui audit' : 'Avvia audit'}</>
            )}
          </button>
        </div>
      </div>

      {error && (
        <div className="p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">{error}</div>
      )}

      {(isPending || isRunning) && (
        <div className="p-4 rounded-xl bg-teal-50 border border-teal-200 text-sm text-teal-800 flex items-center gap-3">
          <RefreshCw className="w-5 h-5 animate-spin flex-shrink-0" />
          <div>
            <p className="font-semibold">Analisi in corso…</p>
            <p className="text-xs text-teal-600 mt-0.5">SEO, GDPR, accessibilità, performance, neuromarketing visivo. Circa 2-3 minuti.</p>
          </div>
        </div>
      )}

      {isFailed && (
        <div className="p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
          <p className="font-semibold">Audit fallito</p>
          {auditData.error && <p className="text-xs mt-1 text-red-500">{auditData.error}</p>}
        </div>
      )}

      {isCompleted && (
        <>
          {/* Score globale */}
          <div className="rounded-xl border border-gray-200 p-5 flex flex-col sm:flex-row items-center gap-6">
            <ScoreRing score={scores.overall} size={110} />
            <div className="flex-1">
              <p className="text-lg font-bold text-gray-900">Score digitale complessivo</p>
              <p className="text-sm text-gray-500 mt-1">Media pesata di {Object.keys(scores).filter(k => !['overall','label','color'].includes(k) && scores[k] != null).length} categorie</p>
              <div className="flex flex-wrap gap-2 mt-3">
                {CATEGORIES.map(cat => scores[cat.key] != null && (
                  <span
                    key={cat.key}
                    className="text-xs px-2 py-1 rounded-full font-medium"
                    style={{
                      backgroundColor: getScoreConfig(scores[cat.key]).bg,
                      color: getScoreConfig(scores[cat.key]).color,
                    }}
                  >
                    {cat.label}: {scores[cat.key]}
                  </span>
                ))}
              </div>
            </div>
          </div>

          {/* Category grid */}
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            {CATEGORIES.map(cat => {
              const s   = scores[cat.key];
              const cfg = getScoreConfig(s);
              const Icon = cat.icon;
              return (
                <div key={cat.key} className="rounded-xl border border-gray-200 p-4 space-y-2">
                  <div className="flex items-center gap-2">
                    <Icon className="w-4 h-4" style={{ color: cfg.color }} />
                    <span className="text-xs font-semibold text-gray-600">{cat.label}</span>
                  </div>
                  <div className="text-2xl font-bold" style={{ color: cfg.color }}>
                    {s ?? '—'}
                  </div>
                  <div className="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                    <div
                      className="h-full rounded-full transition-all duration-700"
                      style={{ width: `${s ?? 0}%`, backgroundColor: cfg.color }}
                    />
                  </div>
                  <p className="text-xs text-gray-400">{cat.description}</p>
                </div>
              );
            })}
          </div>

          {/* Nota pesi settoriali */}
          {auditData?.scores?.sector_key && auditData.scores.sector_key !== 'altro' && (
            <p className="text-xs text-gray-400 text-center -mt-1">
              Score calcolato con pesi ottimizzati per il settore{' '}
              <span className="font-medium text-gray-500">{auditData.scores.sector_key}</span>
            </p>
          )}

          {/* Executive summary */}
          {auditData.executive_summary && (
            <div className="rounded-xl border border-gray-200 overflow-hidden">
              <button
                onClick={() => toggleSection('summary')}
                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
              >
                <span className="font-semibold text-gray-800">Executive Summary</span>
                {expandedSection === 'summary'
                  ? <ChevronUp className="w-4 h-4 text-gray-400" />
                  : <ChevronDown className="w-4 h-4 text-gray-400" />}
              </button>
              {expandedSection === 'summary' && (
                <div className="p-4 border-t border-gray-100">
                  <p className="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{auditData.executive_summary}</p>
                </div>
              )}
            </div>
          )}

          {/* Neuromarketing Visivo */}
          {auditData?.analyzer_results?.neuromarketing?.vision?.above_fold_verdict && (
            <div className="rounded-xl border border-gray-200 overflow-hidden">
              <button
                onClick={() => toggleSection('neuro')}
                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
              >
                <span className="font-semibold text-gray-800 flex items-center gap-2">
                  <TrendingUp className="w-4 h-4" style={{ color: TEAL }} />
                  Analisi Neuromarketing Visiva
                  <span className="text-xs px-2 py-0.5 rounded-full bg-teal-100 text-teal-700 font-medium">Vision AI</span>
                </span>
                {expandedSection === 'neuro'
                  ? <ChevronUp className="w-4 h-4 text-gray-400" />
                  : <ChevronDown className="w-4 h-4 text-gray-400" />}
              </button>
              {expandedSection === 'neuro' && (() => {
                const vision = auditData.analyzer_results.neuromarketing.vision;
                return (
                  <div className="p-4 space-y-4 border-t border-gray-100">
                    <div className="p-3 rounded-lg bg-teal-50 border border-teal-100">
                      <p className="text-sm text-teal-800 font-medium">{vision.above_fold_verdict}</p>
                    </div>

                    {/* Sub-score bars */}
                    {[
                      { label: 'Gerarchia visiva',    key: 'hierarchy_score' },
                      { label: 'CTA principale',      key: 'cta_score' },
                      { label: 'Proposta di valore',  key: 'value_prop_score' },
                      { label: 'Social proof',        key: 'social_proof_score' },
                      { label: 'Coerenza brand',      key: 'brand_coherence_score' },
                      { label: 'Mobile UX',           key: 'mobile_score' },
                    ].map(item => vision[item.key] != null && (
                      <div key={item.key} className="flex items-center gap-3">
                        <span className="text-xs text-gray-500 w-36 flex-shrink-0">{item.label}</span>
                        <div className="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                          <div
                            className="h-full rounded-full transition-all duration-700"
                            style={{
                              width: `${vision[item.key]}%`,
                              backgroundColor: getScoreConfig(vision[item.key]).color,
                            }}
                          />
                        </div>
                        <span
                          className="text-xs font-medium w-8 text-right"
                          style={{ color: getScoreConfig(vision[item.key]).color }}
                        >
                          {vision[item.key]}
                        </span>
                      </div>
                    ))}

                    {/* Punti di forza */}
                    {(vision.strengths || []).length > 0 && (
                      <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Punti di forza</p>
                        {vision.strengths.map((s, i) => (
                          <div key={i} className="flex gap-2 text-sm text-gray-700 mb-1">
                            <CheckCircle className="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" />
                            {s}
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Miglioramenti critici */}
                    {(vision.critical_improvements || []).length > 0 && (
                      <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Miglioramenti critici</p>
                        {vision.critical_improvements.map((imp, i) => (
                          <div key={i} className="p-3 rounded-lg bg-orange-50 border border-orange-100 text-sm mb-2">
                            <p className="font-semibold text-orange-700">{imp.element}</p>
                            <p className="text-gray-600 text-xs mt-1"><strong>Ora:</strong> {imp.current}</p>
                            <p className="text-gray-600 text-xs"><strong>Suggerito:</strong> {imp.suggested}</p>
                            {imp.impact && <p className="text-gray-500 text-xs italic mt-1">{imp.impact}</p>}
                          </div>
                        ))}
                      </div>
                    )}

                    {/* Elementi mancanti per settore */}
                    {(vision.sector_elements_missing || []).length > 0 && (
                      <div>
                        <p className="text-xs font-semibold text-gray-500 uppercase mb-2">Elementi mancanti (settore)</p>
                        <div className="flex flex-wrap gap-2">
                          {vision.sector_elements_missing.map((el, i) => (
                            <span key={i} className="text-xs px-2 py-1 rounded-full bg-red-50 text-red-600 border border-red-100">
                              {el}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* Note settore */}
                    {vision.sector_specific_notes && (
                      <div className="p-3 rounded-lg bg-gray-50 border border-gray-100">
                        <p className="text-xs font-semibold text-gray-500 uppercase mb-1">Note settore</p>
                        <p className="text-sm text-gray-700">{vision.sector_specific_notes}</p>
                      </div>
                    )}
                  </div>
                );
              })()}
            </div>
          )}

          {/* Piano d'azione */}
          {(auditData.recommendations || []).length > 0 && (
            <div className="rounded-xl border border-gray-200 overflow-hidden">
              <button
                onClick={() => toggleSection('recs')}
                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
              >
                <span className="font-semibold text-gray-800">Piano d'azione ({auditData.recommendations.length})</span>
                {expandedSection === 'recs'
                  ? <ChevronUp className="w-4 h-4 text-gray-400" />
                  : <ChevronDown className="w-4 h-4 text-gray-400" />}
              </button>
              {expandedSection === 'recs' && (
                <div className="p-4 border-t border-gray-100 space-y-3">
                  {auditData.recommendations.map((rec, i) => (
                    <div key={i} className="flex gap-3 p-3 rounded-lg bg-gray-50">
                      <span
                        className="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                        style={{ backgroundColor: TEAL, color: '#fff' }}
                      >
                        {rec.priority ?? i + 1}
                      </span>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="text-xs font-semibold text-gray-500 uppercase">{rec.area}</span>
                          {rec.effort && (
                            <span className={`text-xs px-1.5 py-0.5 rounded font-medium ${
                              rec.effort === 'basso' ? 'bg-green-100 text-green-700' :
                              rec.effort === 'medio' ? 'bg-yellow-100 text-yellow-700' :
                              'bg-red-100 text-red-700'
                            }`}>
                              {rec.effort}
                            </span>
                          )}
                          {rec.impact && (
                            <span className="text-xs px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 font-medium">
                              impatto {rec.impact}
                            </span>
                          )}
                        </div>
                        <p className="text-sm text-gray-700 mt-1">{rec.action}</p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Problemi critici */}
          {(auditData.critical_issues || []).length > 0 && (
            <div className="rounded-xl border border-gray-200 overflow-hidden">
              <button
                onClick={() => toggleSection('issues')}
                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition-colors text-left"
              >
                <span className="font-semibold text-gray-800 flex items-center gap-2">
                  <AlertCircle className="w-4 h-4 text-red-500" />
                  Problemi critici ({auditData.critical_issues.length})
                </span>
                {expandedSection === 'issues'
                  ? <ChevronUp className="w-4 h-4 text-gray-400" />
                  : <ChevronDown className="w-4 h-4 text-gray-400" />}
              </button>
              {expandedSection === 'issues' && (
                <div className="p-4 border-t border-gray-100 space-y-3">
                  {auditData.critical_issues.map((issue, i) => (
                    <div key={i} className="flex gap-3">
                      <SeverityIcon severity="critical" />
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
        </>
      )}

      {!auditData && !loading && (
        <div className="text-center py-12 text-gray-400">
          <TrendingUp className="w-10 h-10 mx-auto mb-3 opacity-30" />
          <p className="text-sm">Nessun audit ancora eseguito.</p>
          <p className="text-xs mt-1">Avvia il primo audit per analizzare la presenza digitale del brand.</p>
        </div>
      )}

      {sectorModal && (
        <SectorConfirmModal
          url={auditData?.website_url ?? ''}
          detectionResult={sectorModal.detectionResult}
          onConfirm={(sectorData) => {
            setSectorModal(null);
            startAudit(sectorData);
          }}
          onCancel={() => setSectorModal(null)}
        />
      )}
    </div>
  );
}

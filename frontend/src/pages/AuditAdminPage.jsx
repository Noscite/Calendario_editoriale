import { useState, useEffect, useCallback } from 'react';
import {
  Zap, Building2, Globe, RefreshCw, Download, ChevronDown, ChevronUp,
  AlertCircle, Clock, CheckCircle, XCircle, Plus, X, Search, Link2, Loader2,
} from 'lucide-react';
import { auditApi } from '../services/api';
import SectorConfirmModal from '../components/SectorConfirmModal';

const TEAL = '#3DAFA8';

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
  return              { color: '#EF4444', label: 'Critico',       bg: '#FEF2F2' };
}

function StatusBadge({ status }) {
  const val = status?.value ?? status;
  const map = {
    pending:   { color: '#F59E0B', bg: '#FFFBEB', icon: Clock,         label: 'In coda' },
    running:   { color: TEAL,      bg: '#E8F7F6', icon: RefreshCw,     label: 'In corso' },
    completed: { color: '#10B981', bg: '#ECFDF5', icon: CheckCircle,   label: 'Completato' },
    failed:    { color: '#EF4444', bg: '#FEF2F2', icon: XCircle,       label: 'Fallito' },
  };
  const cfg = map[val] ?? map.pending;
  const Icon = cfg.icon;
  return (
    <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
      style={{ color: cfg.color, background: cfg.bg }}>
      <Icon className="w-3 h-3" />
      {cfg.label}
    </span>
  );
}

function ScorePill({ score }) {
  const cfg = getScoreConfig(score);
  if (score == null) return <span className="text-sm text-gray-400">—</span>;
  return (
    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
      style={{ color: cfg.color, background: cfg.bg }}>
      {score}
    </span>
  );
}

function AuditRow({ audit, onDownload, downloading, onShare, sharing }) {
  const [expanded, setExpanded] = useState(false);
  const statusVal = audit.status?.value ?? audit.status;
  const isCompleted = statusVal === 'completed';
  const isStandalone = !audit.brand_id;

  const name   = audit.display_name   ?? audit.brand?.name ?? audit.standalone_data?.company_name ?? audit.website_url ?? '—';
  const sector = audit.display_sector ?? audit.brand?.sector ?? audit.standalone_data?.sector ?? '—';
  const org    = audit.organization?.name ?? (isStandalone ? '— Prospect —' : '—');

  return (
    <>
      <tr className="border-b border-gray-100 hover:bg-gray-50 transition-colors">
        <td className="px-4 py-3">
          <div className="flex flex-col">
            <span className="font-semibold text-gray-800 text-sm">{name}</span>
            <span className="text-xs text-gray-400">{sector}</span>
          </div>
        </td>
        <td className="px-4 py-3">
          <span className="flex items-center gap-1 text-xs text-gray-500">
            <Building2 className="w-3 h-3 flex-shrink-0" />
            {org}
          </span>
        </td>
        <td className="px-4 py-3">
          <a href={audit.website_url} target="_blank" rel="noopener noreferrer"
            className="flex items-center gap-1 text-xs text-teal-600 hover:underline max-w-[160px] truncate">
            <Globe className="w-3 h-3 flex-shrink-0" />
            {audit.website_url?.replace(/^https?:\/\//, '')}
          </a>
        </td>
        <td className="px-4 py-3"><StatusBadge status={audit.status} /></td>
        <td className="px-4 py-3"><ScorePill score={audit.score_overall} /></td>
        <td className="px-4 py-3 text-xs text-gray-400">
          {audit.created_at ? new Date(audit.created_at).toLocaleDateString('it-IT') : '—'}
        </td>
        <td className="px-4 py-3">
          <div className="flex items-center gap-2">
            {isCompleted && (
              <button
                onClick={() => onShare(audit)}
                disabled={sharing === audit.id}
                title="Copia link condivisibile"
                className="p-1.5 rounded-lg hover:bg-teal-50 text-gray-400 hover:text-teal-600 disabled:opacity-40 transition-colors"
              >
                {sharing === audit.id
                  ? <Loader2 className="w-4 h-4 animate-spin" />
                  : <Link2 className="w-4 h-4" />}
              </button>
            )}
            {isCompleted && (
              <button
                onClick={() => onDownload(audit)}
                disabled={downloading === audit.id}
                title="Scarica PDF"
                className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 disabled:opacity-40 transition-colors"
              >
                {downloading === audit.id
                  ? <RefreshCw className="w-4 h-4 animate-spin" />
                  : <Download className="w-4 h-4" />}
              </button>
            )}
            {isCompleted && (
              <button
                onClick={() => setExpanded(p => !p)}
                title={expanded ? 'Chiudi dettagli' : 'Vedi dettagli'}
                className="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors"
              >
                {expanded ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
              </button>
            )}
          </div>
        </td>
      </tr>
      {expanded && isCompleted && (
        <tr className="bg-gray-50 border-b border-gray-100">
          <td colSpan={7} className="px-4 py-4">
            <div className="space-y-3">
              {/* Score mini-grid */}
              <div className="flex flex-wrap gap-3 items-end">
                {[
                  ['Neuro', audit.score_neuromarketing],
                  ['SEO',   audit.score_seo_geo],
                  ['GDPR',  audit.score_gdpr],
                  ['A11y',  audit.score_accessibility],
                  ['Perf',  audit.score_performance],
                ].map(([label, score]) => (
                  <div key={label} className="text-center">
                    <div className="text-xs text-gray-400 mb-1">{label}</div>
                    <ScorePill score={score} />
                  </div>
                ))}
                {audit.analyzer_results?.scoring_meta?.sector_key &&
                  audit.analyzer_results.scoring_meta.sector_key !== 'altro' && (
                  <span className="text-xs text-gray-400 ml-2">
                    pesi per{' '}
                    <span className="font-medium text-gray-500">
                      {audit.analyzer_results.scoring_meta.sector_key}
                    </span>
                  </span>
                )}
              </div>
              {/* Executive summary */}
              {audit.executive_summary && (
                <div>
                  <div className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Executive Summary</div>
                  <p className="text-xs text-gray-700 leading-relaxed line-clamp-4">
                    {audit.executive_summary}
                  </p>
                </div>
              )}
              {/* Critical issues */}
              {audit.critical_issues?.length > 0 && (
                <div>
                  <div className="text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Problemi critici</div>
                  <ul className="space-y-1">
                    {audit.critical_issues.slice(0, 3).map((issue, i) => (
                      <li key={i} className="flex items-start gap-1.5 text-xs text-gray-600">
                        <AlertCircle className="w-3 h-3 text-red-400 flex-shrink-0 mt-0.5" />
                        <span><span className="font-medium">{issue.area}: </span>{issue.message}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}
              {/* Note prospect */}
              {audit.standalone_data?.notes && (
                <div className="bg-yellow-50 border border-yellow-100 rounded-lg px-3 py-2">
                  <span className="text-xs font-semibold text-yellow-700">Note: </span>
                  <span className="text-xs text-yellow-700">{audit.standalone_data.notes}</span>
                </div>
              )}
            </div>
          </td>
        </tr>
      )}
    </>
  );
}

export default function AuditAdminPage() {
  const [audits, setAudits]       = useState([]);
  const [meta, setMeta]           = useState({ total: 0, current_page: 1, last_page: 1 });
  const [loading, setLoading]     = useState(false);
  const [filter, setFilter]       = useState('all'); // 'all' | 'brand' | 'standalone'
  const [downloading, setDownloading] = useState(null);
  const [sharing, setSharing]     = useState(null);   // audit id in elaborazione
  const [shareToast, setShareToast] = useState(null); // URL copiato (mostra toast)

  // Form nuovo audit prospect
  const [showForm, setShowForm]       = useState(false);
  const [formData, setFormData]       = useState({ url: '', company_name: '', sector: '', notes: '' });
  const [detecting, setDetecting]     = useState(false);
  const [submitting, setSubmitting]   = useState(false);
  const [formError, setFormError]     = useState(null);
  const [formSuccess, setFormSuccess] = useState(false);
  const [sectorModal, setSectorModal] = useState(null); // { url, detectionResult }

  const fetchAudits = useCallback(async (page = 1) => {
    setLoading(true);
    try {
      const res = await auditApi.adminList({ type: filter, page });
      setAudits(res.data.data ?? []);
      setMeta(res.data.meta ?? { total: 0, current_page: 1, last_page: 1 });
    } catch (e) {
      console.error('Errore caricamento audit:', e);
    } finally {
      setLoading(false);
    }
  }, [filter]);

  useEffect(() => { fetchAudits(1); }, [fetchAudits]);

  // Auto-refresh ogni 15s se ci sono audit in corso
  useEffect(() => {
    const hasRunning = audits.some(a => {
      const v = a.status?.value ?? a.status;
      return v === 'pending' || v === 'running';
    });
    if (!hasRunning) return;
    const t = setInterval(() => fetchAudits(meta.current_page), 15000);
    return () => clearInterval(t);
  }, [audits, meta.current_page, fetchAudits]);

  const handleDetectSector = async () => {
    if (!formData.url) return;
    setDetecting(true);
    setFormError(null);
    try {
      const res = await auditApi.detectSector({ url: formData.url });
      setSectorModal({ url: formData.url, detectionResult: res.data });
    } catch (err) {
      // Fallback: open modal with empty detection so user can set sector manually
      setSectorModal({ url: formData.url, detectionResult: null });
    } finally {
      setDetecting(false);
    }
  };

  const handleConfirmSector = async ({ sector, sector_key }) => {
    setSectorModal(null);
    setSubmitting(true);
    setFormError(null);
    setFormSuccess(false);
    try {
      await auditApi.standaloneStart({ ...formData, sector, sector_key });
      setShowForm(false);
      setFormData({ url: '', company_name: '', sector: '', notes: '' });
      setFormSuccess(true);
      setTimeout(() => setFormSuccess(false), 4000);
      fetchAudits(1);
    } catch (err) {
      setFormError(err.response?.data?.error ?? err.response?.data?.message ?? 'Errore nell\'avvio audit.');
    } finally {
      setSubmitting(false);
    }
  };

  const handleShare = async (audit) => {
    setSharing(audit.id);
    try {
      const fn = audit.brand_id
        ? auditApi.generateShareLink
        : auditApi.generateShareLinkAdmin;
      const res = await fn(audit.id);
      const url = res.data.share_url;
      await navigator.clipboard.writeText(url);
      setShareToast(url);
      setTimeout(() => setShareToast(null), 3000);
    } catch {
      alert('Errore nella generazione del link.');
    } finally {
      setSharing(null);
    }
  };

  const handleDownload = async (audit) => {
    const isStandalone = !audit.brand_id;
    const name = audit.display_name ?? audit.brand?.name ?? audit.standalone_data?.company_name ?? 'prospect';
    setDownloading(audit.id);
    try {
      const res = isStandalone
        ? await auditApi.standaloneDownloadPdf(audit.id)
        : await auditApi.standaloneDownloadPdf(audit.id); // fallback same endpoint for now
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const a   = document.createElement('a');
      a.href    = url;
      a.download = `audit_${name.replace(/\s+/g, '_')}_${new Date().toISOString().slice(0, 10)}.pdf`;
      a.click();
      window.URL.revokeObjectURL(url);
    } catch {
      alert('Errore nel download PDF.');
    } finally {
      setDownloading(null);
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-xl font-bold text-gray-900">Audit Digitali</h1>
          <p className="text-sm text-gray-500 mt-0.5">Vista cross-organizzazione — solo superadmin</p>
        </div>
        <button
          onClick={() => fetchAudits(meta.current_page)}
          className="p-2 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors"
          title="Aggiorna"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Form nuovo audit prospect */}
      <div className="bg-white rounded-xl border border-gray-200 p-4">
        {formSuccess && (
          <div className="mb-3 flex items-center gap-2 text-sm text-teal-700 bg-teal-50 border border-teal-100 rounded-lg px-3 py-2">
            <CheckCircle className="w-4 h-4" />
            Audit avviato. Comparirà nella lista tra qualche secondo.
          </div>
        )}
        {!showForm ? (
          <button
            onClick={() => setShowForm(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg text-white text-sm font-semibold transition-opacity hover:opacity-90"
            style={{ backgroundColor: TEAL }}
          >
            <Zap className="w-4 h-4" />
            Nuovo Audit Prospect
          </button>
        ) : (
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-gray-800 text-sm">Audit su URL libero</h3>
              <button onClick={() => { setShowForm(false); setFormError(null); }}
                className="p-1 rounded hover:bg-gray-100 text-gray-400">
                <X className="w-4 h-4" />
              </button>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <input
                type="url"
                value={formData.url}
                onChange={e => setFormData(p => ({ ...p, url: e.target.value }))}
                placeholder="https://esempio.it *"
                className="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400/30"
              />
              <input
                type="text"
                value={formData.company_name}
                onChange={e => setFormData(p => ({ ...p, company_name: e.target.value }))}
                placeholder="Nome azienda"
                className="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400/30"
              />
              <select
                value={formData.sector}
                onChange={e => setFormData(p => ({ ...p, sector: e.target.value }))}
                className="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400/30 text-gray-700"
              >
                {SECTORS.map(s => (
                  <option key={s} value={s}>{s || 'Settore (opzionale)'}</option>
                ))}
              </select>
            </div>
            <textarea
              value={formData.notes}
              onChange={e => setFormData(p => ({ ...p, notes: e.target.value }))}
              placeholder="Note interne (opzionale)"
              rows={2}
              className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-teal-400/30 resize-none"
            />
            {formError && (
              <p className="text-xs text-red-600 flex items-center gap-1">
                <AlertCircle className="w-3 h-3" />{formError}
              </p>
            )}
            <div className="flex items-center gap-2">
              <button
                onClick={handleDetectSector}
                disabled={detecting || submitting || !formData.url}
                className="px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-50 transition-opacity hover:opacity-90 flex items-center gap-2"
                style={{ backgroundColor: TEAL }}
              >
                {detecting
                  ? <><RefreshCw className="w-4 h-4 animate-spin" /> Analisi settore…</>
                  : submitting
                    ? 'Avvio…'
                    : <><Zap className="w-3.5 h-3.5" /> Analizza sito →</>}
              </button>
              <button
                onClick={() => { setShowForm(false); setFormError(null); }}
                className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
              >
                Annulla
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Filtri */}
      <div className="flex items-center gap-2">
        {[
          { key: 'all',        label: 'Tutti' },
          { key: 'brand',      label: 'Brand' },
          { key: 'standalone', label: 'Prospect' },
        ].map(({ key, label }) => (
          <button
            key={key}
            onClick={() => setFilter(key)}
            className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              filter === key
                ? 'text-white'
                : 'text-gray-600 bg-white border border-gray-200 hover:bg-gray-50'
            }`}
            style={filter === key ? { backgroundColor: TEAL } : {}}
          >
            {label}
          </button>
        ))}
        <span className="ml-auto text-xs text-gray-400">{meta.total} audit totali</span>
      </div>

      {/* Tabella */}
      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        {loading && audits.length === 0 ? (
          <div className="flex items-center justify-center py-16 text-gray-400">
            <RefreshCw className="w-5 h-5 animate-spin mr-2" />
            Caricamento...
          </div>
        ) : audits.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 text-gray-400">
            <Search className="w-8 h-8 mb-2 opacity-40" />
            <p className="text-sm">Nessun audit trovato.</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-gray-100 bg-gray-50">
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Azienda</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Org.</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">URL</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Stato</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Score</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Data</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Azioni</th>
                </tr>
              </thead>
              <tbody>
                {audits.map(audit => (
                  <AuditRow
                    key={audit.id}
                    audit={audit}
                    onDownload={handleDownload}
                    downloading={downloading}
                    onShare={handleShare}
                    sharing={sharing}
                  />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Paginazione */}
      {meta.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          {Array.from({ length: meta.last_page }, (_, i) => i + 1).map(page => (
            <button
              key={page}
              onClick={() => fetchAudits(page)}
              className={`w-8 h-8 rounded-lg text-sm font-medium transition-colors ${
                page === meta.current_page
                  ? 'text-white'
                  : 'text-gray-600 hover:bg-gray-100'
              }`}
              style={page === meta.current_page ? { backgroundColor: TEAL } : {}}
            >
              {page}
            </button>
          ))}
        </div>
      )}

      {/* Toast "Link copiato" */}
      {shareToast && (
        <div className="fixed bottom-4 right-4 bg-gray-900 text-white text-sm px-4 py-2.5 rounded-lg shadow-lg flex items-center gap-2 z-50 animate-in fade-in slide-in-from-bottom-2">
          <CheckCircle className="w-4 h-4 text-green-400 flex-shrink-0" />
          Link copiato negli appunti!
        </div>
      )}

      {/* Sector confirmation modal */}
      {sectorModal && (
        <SectorConfirmModal
          url={sectorModal.url}
          detectionResult={sectorModal.detectionResult}
          onConfirm={handleConfirmSector}
          onCancel={() => setSectorModal(null)}
        />
      )}
    </div>
  );
}

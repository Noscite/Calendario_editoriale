import { useEffect, useRef, useState } from 'react';
import {
  Brain, CheckCircle2, Loader2, RefreshCw, AlertTriangle, Sparkles,
} from 'lucide-react';
import PersonaCard from './PersonaCard';
import { projects as projectsApi } from '../services/api';

/**
 * <AISuggestionPersonasCard />
 *
 * Smart, polling-aware. Mostra lo stato della valutazione AI dei buyer
 * personas per un Project, con verdict (reuse/adapt/regenerate/generate_new)
 * + reasoning + lista personas finale.
 *
 * Props:
 *   - projectId:          number (required)
 *   - onConfirm:          (buyerPersonas) => void  — chiamato dopo confirm-personas API ok
 *   - onForceRegenerate:  () => void  — opzionale, callback su force-regenerate (dopo l'API call)
 *   - pollingInterval:    number ms (default 2000)
 *   - pollingTimeout:     number ms (default 30000)
 *
 * API endpoints usati:
 *   - GET  /api/projects/{id}/personas-status
 *   - POST /api/projects/{id}/evaluate-personas         (auto-trigger se status=idle al mount)
 *   - POST /api/projects/{id}/force-regenerate-personas
 *   - POST /api/projects/{id}/confirm-personas
 *
 * Timeout fallback: se polling raggiunge pollingTimeout in stato 'evaluating',
 * la card passa a 'timeout' con UI di fallback (bottone "Genera personas standard").
 */
export default function AISuggestionPersonasCard({
  projectId,
  onConfirm,
  onForceRegenerate,
  pollingInterval = 2000,
  pollingTimeout  = 30000,
}) {
  const [status, setStatus]                  = useState('loading');     // loading | evaluating | ready | failed | timeout
  const [verdict, setVerdict]                = useState(null);          // 'reuse' | 'adapt' | 'regenerate' | 'generate_new' | null
  const [personas, setPersonas]              = useState(null);          // buyer_personas object dal DB
  const [suggestion, setSuggestion]          = useState(null);          // personas_ai_suggestion dal DB
  const [personasSource, setPersonasSource]  = useState(null);          // 'reused_from:42' etc.
  const [error, setError]                    = useState(null);
  const [confirming, setConfirming]          = useState(false);
  const [elapsedSeconds, setElapsedSeconds]  = useState(0);

  const pollRef    = useRef(null);
  const timeoutRef = useRef(null);
  const tickerRef  = useRef(null);
  const startedAt  = useRef(null);

  const stopPolling = () => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
    if (timeoutRef.current) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = null;
    }
    if (tickerRef.current) {
      clearInterval(tickerRef.current);
      tickerRef.current = null;
    }
  };

  const fetchStatus = async () => {
    try {
      const res = await projectsApi.personasStatus(projectId);
      const data = res.data || {};
      const apiStatus = data.status || 'idle';

      setPersonas(data.buyer_personas || null);
      setSuggestion(data.personas_ai_suggestion || null);
      setPersonasSource(data.personas_source || null);
      setVerdict(data.personas_ai_suggestion?.verdict ?? null);

      if (apiStatus === 'ready') {
        setStatus('ready');
        stopPolling();
      } else if (apiStatus === 'failed') {
        setStatus('failed');
        setError(data.tracker?.reason ?? 'Valutazione fallita');
        stopPolling();
      } else if (apiStatus === 'evaluating') {
        setStatus('evaluating');
      } else {
        // idle: nessun job in corso e niente personas → trigger evaluate
        if (!data.buyer_personas) {
          await projectsApi.evaluatePersonas(projectId).catch(() => {});
          setStatus('evaluating');
          startedAt.current = Date.now();
        } else {
          // idle ma ho già personas in DB → consideralo ready (job vecchio cache scaduta)
          setStatus('ready');
          stopPolling();
        }
      }
    } catch (e) {
      const msg =
        e?.response?.data?.message ||
        e?.response?.data?.detail ||
        e?.message ||
        'Errore nel caricamento dello stato.';
      setError(msg);
      // Non fermo il polling: potrebbe essere un blip di rete
    }
  };

  const startPolling = () => {
    stopPolling();
    startedAt.current ??= Date.now();

    // Tick UI ogni secondo per countdown
    tickerRef.current = setInterval(() => {
      setElapsedSeconds(Math.floor((Date.now() - startedAt.current) / 1000));
    }, 1000);

    // Poll API
    pollRef.current = setInterval(() => {
      fetchStatus();
    }, pollingInterval);

    // Timeout finale
    timeoutRef.current = setTimeout(() => {
      stopPolling();
      setStatus((current) => (current === 'evaluating' ? 'timeout' : current));
    }, pollingTimeout);
  };

  // Mount: fetch iniziale + start polling se necessario
  useEffect(() => {
    let cancelled = false;
    setStatus('loading');
    setError(null);
    startedAt.current = Date.now();

    (async () => {
      await fetchStatus();
      if (cancelled) return;
      // Se dopo il primo fetch siamo evaluating, parte il polling
      // (status state è async; controllo via ref/data sync sotto)
    })();

    return () => {
      cancelled = true;
      stopPolling();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [projectId]);

  // Reagisci al passaggio in stato 'evaluating' avviando il polling
  useEffect(() => {
    if (status === 'evaluating' && pollRef.current === null) {
      startPolling();
    }
    if (status !== 'evaluating') {
      // tickerRef gestito da stopPolling se i poll ref sono stati settati;
      // se non c'è polling attivo, ferma comunque il ticker per pulizia.
      if (tickerRef.current && pollRef.current === null) {
        clearInterval(tickerRef.current);
        tickerRef.current = null;
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  const handleConfirm = async () => {
    if (!personas) return;
    setConfirming(true);
    setError(null);
    try {
      await projectsApi.confirmPersonas(projectId);
      onConfirm?.({ ...personas, confirmed: true });
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || 'Errore nella conferma.');
    } finally {
      setConfirming(false);
    }
  };

  const handleForceRegenerate = async () => {
    setError(null);
    setVerdict(null);
    setPersonas(null);
    setSuggestion(null);
    setStatus('evaluating');
    startedAt.current = Date.now();
    setElapsedSeconds(0);
    try {
      await projectsApi.forceRegeneratePersonas(projectId);
      onForceRegenerate?.();
      startPolling();
    } catch (e) {
      setError(e?.response?.data?.message || e?.message || 'Errore nel dispatch della rigenerazione.');
      setStatus('failed');
    }
  };

  const personasList = personas?.personas ?? [];
  const sourceProjectName = (() => {
    if (!suggestion?.top_candidates || !suggestion?.source_project_id) return null;
    const match = suggestion.top_candidates.find(
      (c) => c.project_id === suggestion.source_project_id,
    );
    return match?.name ?? null;
  })();

  // Header style per stato
  const headerStyle = (() => {
    if (status === 'ready' && verdict === 'reuse') {
      return { bg: 'bg-emerald-50',  border: 'border-emerald-200', text: 'text-emerald-800', icon: CheckCircle2 };
    }
    if (status === 'ready' && verdict === 'adapt') {
      return { bg: 'bg-blue-50',     border: 'border-blue-200',    text: 'text-blue-800',    icon: Sparkles };
    }
    if (status === 'ready' && (verdict === 'regenerate' || verdict === 'generate_new' || verdict === null)) {
      return { bg: 'bg-gray-50',     border: 'border-gray-200',    text: 'text-[#2C3E50]',   icon: Brain };
    }
    if (status === 'failed' || status === 'timeout') {
      return { bg: 'bg-amber-50',    border: 'border-amber-200',   text: 'text-amber-900',   icon: AlertTriangle };
    }
    return { bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-700', icon: Loader2 };
  })();
  const HeaderIcon = headerStyle.icon;

  const headerTitle = (() => {
    if (status === 'evaluating' || status === 'loading') return 'Sto valutando le personas…';
    if (status === 'failed')  return 'Valutazione automatica fallita';
    if (status === 'timeout') return 'Valutazione AI troppo lenta';
    if (status === 'ready' && verdict === 'reuse') {
      return sourceProjectName
        ? `Personas riutilizzate da "${sourceProjectName}"`
        : 'Personas riutilizzate da un project precedente';
    }
    if (status === 'ready' && verdict === 'adapt') {
      return sourceProjectName
        ? `Personas adattate da "${sourceProjectName}"`
        : 'Personas adattate da un project precedente';
    }
    if (status === 'ready') return 'Personas generate per questo project';
    return 'Personas';
  })();

  const regenerateLabel =
    verdict === 'reuse' || verdict === 'adapt'
      ? 'Rigenera comunque'
      : 'Rigenera nuovamente';

  // ── Render ────────────────────────────────────────────────────

  if (status === 'loading') {
    return (
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center gap-3 text-gray-500">
        <Loader2 size={18} className="animate-spin" />
        Caricamento stato personas…
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl shadow-sm border border-gray-200">
      {/* Header colorato per stato */}
      <div className={`${headerStyle.bg} ${headerStyle.border} border-b rounded-t-xl px-5 py-4 flex items-start gap-3`}>
        {status === 'evaluating' ? (
          <Loader2 size={22} className="text-[#3DAFA8] animate-spin mt-0.5" />
        ) : (
          <HeaderIcon size={22} className={`${headerStyle.text} mt-0.5`} />
        )}
        <div className="flex-1">
          <h3 className={`font-semibold ${headerStyle.text}`}>{headerTitle}</h3>
          {status === 'evaluating' && (
            <p className="text-sm text-gray-600 mt-0.5">
              Tempo trascorso: {elapsedSeconds}s — di solito ~10-15s
            </p>
          )}
          {status === 'ready' && suggestion?.reasoning && (
            <p className="text-sm text-gray-700 mt-1">{suggestion.reasoning}</p>
          )}
          {status === 'ready' && typeof suggestion?.confidence === 'number' && (
            <p className="text-xs text-gray-500 mt-1">
              Confidenza AI: {Math.round((suggestion.confidence ?? 0) * 100)}%
              {personasSource && ` · ${personasSource}`}
            </p>
          )}
          {(status === 'failed' || status === 'timeout') && (
            <p className="text-sm text-amber-800 mt-1">
              {status === 'timeout'
                ? 'Il job ha superato il tempo massimo di attesa. Procedi con la generazione standard.'
                : (error ?? 'Procedi con la generazione standard.')}
            </p>
          )}
        </div>
      </div>

      {/* Body — lista personas */}
      <div className="p-5 space-y-4">
        {error && status !== 'failed' && status !== 'timeout' && (
          <div className="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2">
            {error}
          </div>
        )}

        {personasList.length === 0 && status !== 'evaluating' && (
          <div className="text-sm text-gray-500 italic text-center py-6">
            Nessuna persona disponibile.
          </div>
        )}

        {personasList.length > 0 && (
          <div className="grid md:grid-cols-2 gap-4">
            {personasList.map((p, idx) => (
              <PersonaCard key={idx} persona={p} readOnly />
            ))}
          </div>
        )}
      </div>

      {/* Footer azioni */}
      {status !== 'evaluating' && status !== 'loading' && (
        <div className="border-t border-gray-100 px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
          <button
            type="button"
            onClick={handleForceRegenerate}
            disabled={confirming}
            className="flex items-center gap-2 px-4 py-2 border border-[#3DAFA8] text-[#3DAFA8] rounded-lg hover:bg-teal-50 disabled:opacity-50 text-sm font-medium transition-colors"
          >
            <RefreshCw size={16} />
            {regenerateLabel}
          </button>

          <button
            type="button"
            onClick={handleConfirm}
            disabled={confirming || personasList.length === 0 || status === 'failed' || status === 'timeout'}
            className="flex items-center gap-2 px-5 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed text-sm font-medium transition-colors"
          >
            {confirming ? <Loader2 size={16} className="animate-spin" /> : <CheckCircle2 size={16} />}
            Conferma e procedi
          </button>
        </div>
      )}
    </div>
  );
}

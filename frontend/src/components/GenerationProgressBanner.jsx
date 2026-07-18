import React from 'react';
import { Loader2, CheckCircle2, AlertTriangle, X } from 'lucide-react';

const PHASE_LABELS = {
  calendar_base:     '📅 Calendario base',
  territorial_sync:  '📍 Sync eventi territoriali',
  territorial_posts: '🎯 Post evento-driven',
};

function formatEta(seconds) {
  if (seconds == null || seconds < 0) return null;
  if (seconds < 60) return `~${seconds}s`;
  const min = Math.round(seconds / 60);
  return min === 1 ? '~1 minuto' : `~${min} minuti`;
}

export default function GenerationProgressBanner({ status, onDismiss }) {
  if (!status) return null;
  if (status.status === 'idle' || status.status === 'unknown') return null;

  const phases = status.phases || {};
  const overall = status.progress_percent || 0;
  const eta = formatEta(status.estimated_remaining_seconds);

  const doneProjectStatuses = ['review', 'approved', 'published'];
  const isCompleted = status.status === 'completed'
    || doneProjectStatuses.includes(status.project_status);
  const isFailed    = status.status === 'failed';
  const isRunning   = status.status === 'generating' && !isCompleted;

  const baseStyle = isCompleted
    ? 'bg-green-50 border-green-200 text-green-900'
    : isFailed
      ? 'bg-red-50 border-red-200 text-red-900'
      : 'bg-blue-50 border-blue-200 text-blue-900';

  const icon = isCompleted
    ? <CheckCircle2 className="w-5 h-5 text-green-600" />
    : isFailed
      ? <AlertTriangle className="w-5 h-5 text-red-600" />
      : <Loader2 className="w-5 h-5 text-blue-600 animate-spin" />;

  const headline = isCompleted
    ? '✅ Calendario generato con successo'
    : isFailed
      ? '⚠️ Generazione fallita'
      : `🔄 Generazione calendario in corso: ${overall}%`;

  return (
    <div className={`sticky top-0 z-30 border-b ${baseStyle} px-6 py-3 shadow-sm`}>
      <div className="flex items-start gap-3">
        <div className="mt-0.5">{icon}</div>
        <div className="flex-1">
          <div className="font-semibold text-sm">
            {headline}
            {eta && isRunning && (
              <span className="ml-2 font-normal opacity-75">— {eta} rimanenti</span>
            )}
          </div>

          {isRunning && (
            <>
              <div className="mt-2 w-full bg-blue-100 rounded-full h-1.5">
                <div
                  className="bg-blue-600 h-1.5 rounded-full transition-all duration-500"
                  style={{ width: `${overall}%` }}
                />
              </div>

              <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                {Object.entries(phases).map(([key, phase]) => (
                  <div key={key} className="flex items-center gap-1">
                    <span className="font-medium">{PHASE_LABELS[key] || key}:</span>
                    <span>
                      {phase.status === 'completed' && '✓'}
                      {phase.status === 'running' && (
                        key === 'territorial_posts'
                          ? `${phase.posts_done || 0}/${phase.posts_total_expected || '?'} post`
                          : 'in corso…'
                      )}
                      {phase.status === 'pending' && 'in attesa'}
                    </span>
                  </div>
                ))}
              </div>
            </>
          )}

          {isFailed && status.failed_reason && (
            <div className="mt-1 text-xs opacity-75">{status.failed_reason}</div>
          )}

          {isCompleted && (
            <div className="mt-1 text-xs opacity-75">
              I post sono ora visibili nel calendario. Buona pubblicazione!
            </div>
          )}
        </div>

        {(isCompleted || isFailed) && onDismiss && (
          <button
            onClick={onDismiss}
            className="text-current opacity-50 hover:opacity-100 transition"
            aria-label="Chiudi"
          >
            <X className="w-4 h-4" />
          </button>
        )}
      </div>
    </div>
  );
}

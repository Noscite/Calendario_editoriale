import { useState, useEffect, useRef, useCallback } from 'react';
import { generation as generationApi } from '../services/api';

/**
 * Polling dello stato di generazione del calendario.
 *
 * @param {number|null} projectId
 * @param {object} options
 * @param {number} options.intervalMs - intervallo polling in ms (default 3000)
 * @param {function} options.onComplete - chiamato quando lo status passa
 *                                        da 'generating' a 'completed'/'failed'
 * @returns {{status: object|null, isPolling: boolean, error: string|null,
 *           restart: function, stop: function}}
 */
export function useGenerationStatus(projectId, { intervalMs = 3000, onComplete } = {}) {
  const [status, setStatus] = useState(null);
  const [isPolling, setIsPolling] = useState(false);
  const [error, setError] = useState(null);
  const intervalRef = useRef(null);
  const prevStatusRef = useRef(null);
  const onCompleteRef = useRef(onComplete);

  // Tieni la callback aggiornata senza rilanciare il useEffect principale.
  useEffect(() => {
    onCompleteRef.current = onComplete;
  }, [onComplete]);

  const fetchStatus = useCallback(async () => {
    if (!projectId) return;
    try {
      const res = await generationApi.status(projectId);
      const data = res.data;
      setStatus(data);
      setError(null);

      const wasGenerating = prevStatusRef.current === 'generating';
      const isNowDone = data.status === 'completed' || data.status === 'failed';
      if (wasGenerating && isNowDone) {
        onCompleteRef.current?.(data);
      }
      prevStatusRef.current = data.status;

      if (data.status !== 'generating' && intervalRef.current) {
        clearInterval(intervalRef.current);
        intervalRef.current = null;
        setIsPolling(false);
      }
    } catch (err) {
      setError(err?.message || 'Errore durante il fetch dello stato');
    }
  }, [projectId]);

  const startPolling = useCallback(() => {
    if (intervalRef.current || !projectId) return;
    setIsPolling(true);
    fetchStatus();
    intervalRef.current = setInterval(fetchStatus, intervalMs);
  }, [fetchStatus, intervalMs, projectId]);

  const stopPolling = useCallback(() => {
    if (intervalRef.current) {
      clearInterval(intervalRef.current);
      intervalRef.current = null;
      setIsPolling(false);
    }
  }, []);

  useEffect(() => {
    if (!projectId) return;
    fetchStatus();
    return () => stopPolling();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [projectId]);

  // Quando status diventa 'generating' avvia polling se non attivo
  useEffect(() => {
    if (status?.status === 'generating' && !intervalRef.current) {
      startPolling();
    }
  }, [status?.status, startPolling]);

  return { status, isPolling, error, restart: startPolling, stop: stopPolling };
}

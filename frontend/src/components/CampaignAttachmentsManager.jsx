import { useState, useEffect, useCallback } from 'react';
import { campaigns as campaignsApi } from '../services/api';

const MAX_ATTACHMENTS = 5;
const MAX_SIZE_MB = 25;

const STATUS_BADGES = {
  pending:     { text: '⏳ In coda',                color: '#f59e0b' },
  processing:  { text: '⏳ Estrazione testo...',    color: '#f59e0b' },
  completed:   { text: '✓ Pronto',                  color: '#10b981' },
  failed:      { text: '✗ Errore estrazione',       color: '#ef4444' },
  unsupported: { text: '⚠️ Formato non leggibile',  color: '#9ca3af' },
};

function mimeIcon(mime) {
  if (!mime) return '📎';
  if (mime.includes('pdf'))   return '📄';
  if (mime.includes('word'))  return '📝';
  if (mime.includes('sheet')) return '📊';
  if (mime.includes('image')) return '🖼️';
  if (mime.includes('text'))  return '📃';
  return '📎';
}

export default function CampaignAttachmentsManager({ campaignId }) {
  const [attachments, setAttachments] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);
  const [dragOver, setDragOver] = useState(false);
  const [loading, setLoading] = useState(true);

  const loadAttachments = useCallback(async () => {
    try {
      const res = await campaignsApi.attachments.list(campaignId);
      setAttachments(res.data?.data || []);
    } catch (err) {
      console.error('Errore caricamento allegati:', err);
    } finally {
      setLoading(false);
    }
  }, [campaignId]);

  useEffect(() => {
    if (campaignId) loadAttachments();
  }, [campaignId, loadAttachments]);

  // Polling se ci sono attachment in pending/processing
  useEffect(() => {
    const hasPending = attachments.some(a => ['pending', 'processing'].includes(a.extraction_status));
    if (!hasPending) return;
    const interval = setInterval(loadAttachments, 3000);
    return () => clearInterval(interval);
  }, [attachments, loadAttachments]);

  const handleUpload = async (file) => {
    if (!file) return;
    setError(null);

    if (file.size > MAX_SIZE_MB * 1024 * 1024) {
      setError(`File troppo grande: massimo ${MAX_SIZE_MB} MB`);
      return;
    }

    if (attachments.length >= MAX_ATTACHMENTS) {
      setError(`Massimo ${MAX_ATTACHMENTS} allegati per campagna. Elimina un allegato esistente prima di caricarne un altro.`);
      return;
    }

    setUploading(true);
    const formData = new FormData();
    formData.append('file', file);

    try {
      await campaignsApi.attachments.upload(campaignId, formData);
      await loadAttachments();
    } catch (err) {
      const apiMessage =
        err.response?.data?.errors?.file?.[0]
        || err.response?.data?.message
        || 'Errore upload';
      setError(apiMessage);
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (attachmentId) => {
    if (!confirm('Eliminare questo allegato?')) return;
    try {
      await campaignsApi.attachments.delete(campaignId, attachmentId);
      await loadAttachments();
    } catch (err) {
      setError('Errore eliminazione');
    }
  };

  const disabledUpload = uploading || attachments.length >= MAX_ATTACHMENTS;

  return (
    <div className="bg-white rounded-xl shadow-sm p-6 mt-6">
      <h3 className="font-semibold text-[#2C3E50] mb-1">
        📎 Documenti Knowledge Base ({attachments.length}/{MAX_ATTACHMENTS})
      </h3>
      <p className="text-sm text-gray-500 mb-4">
        Carica fino a {MAX_ATTACHMENTS} file (max {MAX_SIZE_MB} MB ciascuno).
        Il testo verrà estratto e usato come fonte di conoscenza per la generazione
        AI dei post di questa campagna.
      </p>

      <div
        onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
        onDragLeave={() => setDragOver(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragOver(false);
          if (!disabledUpload) handleUpload(e.dataTransfer.files[0]);
        }}
        style={{
          border: `2px dashed ${dragOver ? '#3DAFA8' : '#cbd5e1'}`,
          borderRadius: '8px',
          padding: '24px',
          textAlign: 'center',
          backgroundColor: dragOver ? '#f0fdfa' : '#f8fafc',
          marginBottom: '16px',
          opacity: disabledUpload ? 0.5 : 1,
          transition: 'background-color .15s, border-color .15s',
        }}
      >
        <p className="text-sm text-gray-700 mb-2">Trascina qui un file oppure</p>
        <input
          type="file"
          onChange={(e) => handleUpload(e.target.files[0])}
          disabled={disabledUpload}
          className="text-sm"
        />
        {uploading && <p className="text-sm text-[#3DAFA8] mt-2">Caricamento in corso…</p>}
      </div>

      {error && (
        <div className="mb-3 px-3 py-2 bg-red-50 text-red-700 text-sm rounded">{error}</div>
      )}

      {loading && attachments.length === 0 ? (
        <div className="text-sm text-gray-400 py-3">Caricamento allegati…</div>
      ) : attachments.length === 0 ? (
        <div className="text-sm text-gray-400 py-3">Nessun allegato.</div>
      ) : (
        <ul className="divide-y divide-gray-100">
          {attachments.map((a) => {
            const badge = STATUS_BADGES[a.extraction_status] || STATUS_BADGES.pending;
            return (
              <li key={a.id} className="py-3 flex items-center gap-3">
                <span className="text-xl">{mimeIcon(a.mime_type)}</span>
                <div className="flex-1 min-w-0">
                  <div className="font-medium text-sm text-[#2C3E50] truncate" title={a.original_filename}>
                    {a.original_filename}
                  </div>
                  <div className="text-xs text-gray-400">
                    {a.size_human} · {a.mime_type}
                  </div>
                  {a.extraction_error && (
                    <div className="text-xs text-red-500 mt-1">{a.extraction_error}</div>
                  )}
                </div>
                <span className="text-xs whitespace-nowrap" style={{ color: badge.color }}>
                  {badge.text}
                </span>
                <a
                  href={a.download_url}
                  target="_blank"
                  rel="noreferrer"
                  className="text-gray-400 hover:text-[#3DAFA8] text-sm"
                  title="Scarica"
                >
                  ⬇️
                </a>
                <button
                  onClick={() => handleDelete(a.id)}
                  className="text-gray-400 hover:text-red-500 text-sm"
                  title="Elimina"
                >
                  🗑️
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}

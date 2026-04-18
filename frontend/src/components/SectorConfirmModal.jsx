import { useState } from 'react';
import { AlertTriangle, ChevronDown } from 'lucide-react';

const SECTOR_OPTIONS = [
  { value: 'psicologia',    label: 'Psicologia / Counseling' },
  { value: 'medico',        label: 'Medicina / Clinica' },
  { value: 'legale',        label: 'Avvocatura / Studi legali' },
  { value: 'farmacia',      label: 'Farmacia' },
  { value: 'finanza',       label: 'Finanza / Assicurazioni' },
  { value: 'alimentare',    label: 'Ristorazione / Alimentare' },
  { value: 'fitness',       label: 'Fitness / Personal Training' },
  { value: 'immobiliare',   label: 'Immobiliare' },
  { value: 'turismo',       label: 'Turismo / Hospitality' },
  { value: 'ecommerce',     label: 'E-commerce / Retail' },
  { value: 'tecnologia',    label: 'Tecnologia / SaaS' },
  { value: 'educazione',    label: 'Educazione / Formazione' },
  { value: 'arte',          label: 'Arte / Creatività' },
  { value: 'nonprofit',     label: 'No profit / Associazioni' },
  { value: 'pa',            label: 'Pubblica Amministrazione' },
  { value: 'altro',         label: 'Altro / Generico' },
];

const CONFIDENCE_STYLES = {
  high:   { label: 'Alta',   bg: 'bg-green-100',  text: 'text-green-800'  },
  medium: { label: 'Media',  bg: 'bg-yellow-100', text: 'text-yellow-800' },
  low:    { label: 'Bassa',  bg: 'bg-gray-100',   text: 'text-gray-600'   },
};

export default function SectorConfirmModal({ url, detectionResult, onConfirm, onCancel }) {
  const detected        = detectionResult?.sector           ?? '';
  const detectedKey     = detectionResult?.sector_key       ?? 'altro';
  const confidence      = detectionResult?.confidence       ?? 'low';
  const isRegulated     = detectionResult?.is_regulated     ?? false;
  const brandSector     = detectionResult?.brand_sector     ?? null;
  const brandSectorKey  = detectionResult?.brand_sector_key ?? null;

  // Priorità: settore già configurato nel brand > rilevato da AI > 'altro'
  const initialKey = brandSectorKey ?? detectedKey;

  const [useCustom, setUseCustom]       = useState(false);
  const [selectedKey, setSelectedKey]   = useState(initialKey);
  const [customSector, setCustomSector] = useState(brandSector ?? detected);

  const confStyle = CONFIDENCE_STYLES[confidence] ?? CONFIDENCE_STYLES.low;

  // Regulated check è dinamico: segue il settore selezionato nel dropdown
  const REGULATED_KEYS = new Set([
    'psicologia', 'medico', 'farmacia', 'legale', 'finanza',
  ]);
  const isSelectedRegulated = !useCustom && REGULATED_KEYS.has(selectedKey);

  const handleConfirm = () => {
    if (useCustom) {
      onConfirm({ sector: customSector.trim() || 'Generico', sector_key: 'altro' });
    } else {
      const option = SECTOR_OPTIONS.find(o => o.value === selectedKey);
      onConfirm({ sector: option?.label ?? selectedKey, sector_key: selectedKey });
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md flex flex-col gap-5 p-6">

        {/* Header */}
        <div>
          <h2 className="text-lg font-semibold text-gray-900">Conferma settore</h2>
          <p className="mt-1 text-sm text-gray-500 truncate">{url}</p>
        </div>

        {/* Brand sector (configured) or AI-detected */}
        <div className="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 flex items-center justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">
              {brandSector ? 'Settore brand' : 'Settore rilevato'}
            </p>
            <p className="font-medium text-gray-900 truncate">
              {brandSector || detected || 'Non rilevato'}
            </p>
            {brandSector && detected && brandSectorKey !== detectedKey && (
              <p className="text-xs text-gray-400 mt-0.5">
                AI suggerisce: <span className="text-gray-600">{detected}</span>
              </p>
            )}
          </div>
          <span className={`text-xs font-medium px-2 py-0.5 rounded-full shrink-0 ${confStyle.bg} ${confStyle.text}`}>
            {brandSector ? 'Configurato' : confStyle.label}
          </span>
        </div>

        {/* Regulated warning */}
        {isSelectedRegulated && (
          <div className="flex items-start gap-2 rounded-xl bg-orange-50 border border-orange-200 px-4 py-3 text-sm text-orange-800">
            <AlertTriangle className="mt-0.5 shrink-0 text-orange-500" size={16} />
            <span>
              Settore <strong>regolamentato</strong> — l'audit applicherà automaticamente i vincoli
              deontologici italiani per evitare raccomandazioni inappropriate.
            </span>
          </div>
        )}

        {/* Sector select / custom toggle */}
        <div className="flex flex-col gap-2">
          <div className="flex items-center justify-between">
            <label className="text-sm font-medium text-gray-700">Settore per l'audit</label>
            <button
              type="button"
              onClick={() => setUseCustom(v => !v)}
              className="text-xs text-blue-600 hover:underline"
            >
              {useCustom ? '← Usa elenco' : 'Inserisci manualmente →'}
            </button>
          </div>

          {useCustom ? (
            <input
              type="text"
              className="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              value={customSector}
              onChange={e => setCustomSector(e.target.value)}
              placeholder="Es. Consulenza energetica"
            />
          ) : (
            <div className="relative">
              <select
                className="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 pr-8 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                value={selectedKey}
                onChange={e => setSelectedKey(e.target.value)}
              >
                {SECTOR_OPTIONS.map(o => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </select>
              <ChevronDown size={14} className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400" />
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="flex gap-3 pt-1">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Annulla
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            className="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
          >
            Avvia audit
          </button>
        </div>
      </div>
    </div>
  );
}

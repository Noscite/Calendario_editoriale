import { Plus, Trash2, Target, AlertCircle, X, Building2 } from 'lucide-react';

/**
 * <ContentPillarsSelector />
 *
 * Selettore content pillars per il Project, con 2 sezioni:
 *  1. "Pillar dal brand" (badge cliccabili, removable) — pre-popolazione da
 *     Brand.default_content_pillars.
 *  2. "Pillar specifici di questo project" (repeater editable).
 *
 * Toggle opt-in "Salva i nuovi pillar nella libreria del brand" — visibile
 * solo se ci sono pillar specifici di project NON presenti nel brand
 * (case-insensitive). Il toggle è solo state-flag: il parent al submit
 * dello step deve chiamare projectsApi.promotePillarsToBrand.
 *
 * Validation locale: 3 ≤ totale ≤ 6, name unique case-insensitive.
 *
 * Props:
 *   - value:                array<{name, description, source: 'brand'|'project'}>
 *   - onChange:             (newArray) => void
 *   - brandDefaultPillars:  array<{name, description}>
 *   - promoteEnabled:       boolean (controlled toggle state)
 *   - onPromoteEnabledChange: (newBool) => void
 *   - error:                string|null
 */

const MIN_PILLARS = 3;
const MAX_PILLARS = 6;
const NAME_MAX = 60;
const DESC_MAX = 200;

function normalizeName(s) {
  return String(s ?? '').trim().toLowerCase();
}

function emptyPillar() {
  return { name: '', description: '', source: 'project' };
}

export default function ContentPillarsSelector({
  value = [],
  onChange,
  brandDefaultPillars = [],
  promoteEnabled = false,
  onPromoteEnabledChange,
  error = null,
}) {
  const items = Array.isArray(value) ? value : [];
  const brandPillars = Array.isArray(brandDefaultPillars) ? brandDefaultPillars : [];

  const brandKept    = items.filter((p) => p.source === 'brand');
  const projectItems = items.filter((p) => p.source === 'project');

  const total = brandKept.length + projectItems.length;

  // Per-row dedup detection (across ALL items)
  const seenCounts = items.reduce((acc, it) => {
    const key = normalizeName(it.name);
    if (key !== '') acc[key] = (acc[key] ?? 0) + 1;
    return acc;
  }, {});
  const isDuplicate = (it) => {
    const key = normalizeName(it.name);
    return key !== '' && (seenCounts[key] ?? 0) > 1;
  };

  // Pillar specifici di project che NON sono nel brand (case-insensitive)
  const brandNamesNorm = brandPillars.map((p) => normalizeName(p.name));
  const newProjectPillars = projectItems.filter(
    (p) => normalizeName(p.name) !== '' && !brandNamesNorm.includes(normalizeName(p.name)),
  );
  const hasNewToPromote = newProjectPillars.length > 0;

  // ── Mutators ──────────────────────────────────────────────────

  const removeBrandKept = (name) => {
    const norm = normalizeName(name);
    onChange?.(items.filter((p) => !(p.source === 'brand' && normalizeName(p.name) === norm)));
  };

  const restoreBrandPillar = (brandPillar) => {
    if (total >= MAX_PILLARS) return;
    onChange?.([
      ...items,
      { name: brandPillar.name, description: brandPillar.description ?? '', source: 'brand' },
    ]);
  };

  const addProjectPillar = () => {
    if (total >= MAX_PILLARS) return;
    onChange?.([...items, emptyPillar()]);
  };

  const updateProjectPillar = (projectIdx, patch) => {
    let i = -1;
    const next = items.map((it) => {
      if (it.source !== 'project') return it;
      i++;
      return i === projectIdx ? { ...it, ...patch } : it;
    });
    onChange?.(next);
  };

  const removeProjectPillar = (projectIdx) => {
    let i = -1;
    onChange?.(
      items.filter((it) => {
        if (it.source !== 'project') return true;
        i++;
        return i !== projectIdx;
      }),
    );
  };

  // Brand pillars NON ancora keptati, riproponibili con click
  const removedBrandPillars = brandPillars.filter(
    (bp) => !brandKept.some((kept) => normalizeName(kept.name) === normalizeName(bp.name)),
  );

  const countTone =
    total < MIN_PILLARS ? 'text-amber-600'
    : total > MAX_PILLARS ? 'text-red-600'
    : 'text-emerald-600';

  // ── Render ────────────────────────────────────────────────────

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h4 className="font-semibold text-[#2C3E50] flex items-center gap-2">
            <Target size={18} className="text-[#3DAFA8]" /> Content pillars del project
          </h4>
          <p className="text-sm text-gray-500 mt-1">
            Configura tra <strong>{MIN_PILLARS}</strong> e <strong>{MAX_PILLARS}</strong> pillar che guideranno la generazione del calendario.
            Parti dai pillar di brand pre-popolati, rimuovi quelli non pertinenti e aggiungine di specifici per questo project.
          </p>
        </div>
        <span className={`text-sm font-medium whitespace-nowrap ${countTone}`}>
          {total} / {MAX_PILLARS}
        </span>
      </div>

      {error && (
        <div className="bg-red-50 border-l-4 border-red-400 text-red-700 px-3 py-2 rounded text-sm">
          {error}
        </div>
      )}

      {/* ── Sezione 1: Pillar dal brand ────────────────────────── */}
      <div className="bg-gray-50 rounded-xl border border-gray-200 p-4 space-y-3">
        <div className="flex items-center gap-2">
          <Building2 size={16} className="text-gray-500" />
          <h5 className="text-sm font-semibold text-[#2C3E50]">Pillar dal brand</h5>
          <span className="text-xs text-gray-400">({brandKept.length} attivi su {brandPillars.length})</span>
        </div>

        {brandPillars.length === 0 ? (
          <p className="text-xs text-gray-500 italic">
            Il brand non ha pillar di default configurati. Aggiungi pillar specifici qui sotto e attiva il toggle per salvarli nella libreria del brand.
          </p>
        ) : (
          <>
            {brandKept.length > 0 && (
              <div className="flex flex-wrap gap-2">
                {brandKept.map((p, i) => (
                  <span
                    key={`kept-${i}-${p.name}`}
                    title={p.description || p.name}
                    className="inline-flex items-center gap-1.5 bg-teal-50 text-[#2C3E50] border border-teal-200 rounded-full px-3 py-1 text-xs"
                  >
                    {p.name}
                    <button
                      type="button"
                      onClick={() => removeBrandKept(p.name)}
                      className="text-gray-400 hover:text-red-500"
                      aria-label={`Rimuovi pillar ${p.name}`}
                    >
                      <X size={12} />
                    </button>
                  </span>
                ))}
              </div>
            )}

            {removedBrandPillars.length > 0 && (
              <div>
                <p className="text-xs text-gray-500 mb-1.5">
                  Disattivati (clicca per ri-aggiungere):
                </p>
                <div className="flex flex-wrap gap-2">
                  {removedBrandPillars.map((p, i) => (
                    <button
                      key={`removed-${i}-${p.name}`}
                      type="button"
                      onClick={() => restoreBrandPillar(p)}
                      disabled={total >= MAX_PILLARS}
                      title={p.description || p.name}
                      className="inline-flex items-center gap-1 bg-white text-gray-500 border border-dashed border-gray-300 rounded-full px-3 py-1 text-xs hover:bg-teal-50 hover:text-[#3DAFA8] hover:border-[#3DAFA8] disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                      <Plus size={12} /> {p.name}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>

      {/* ── Sezione 2: Pillar specifici di project ─────────────── */}
      <div className="space-y-3">
        <div className="flex items-center gap-2">
          <Target size={16} className="text-[#3DAFA8]" />
          <h5 className="text-sm font-semibold text-[#2C3E50]">Pillar specifici di questo project</h5>
        </div>

        {projectItems.length === 0 && (
          <div className="bg-gray-50 border border-dashed border-gray-300 rounded-lg p-5 text-center text-sm text-gray-500">
            Nessun pillar specifico aggiunto. Usa solo i pillar di brand oppure aggiungine di nuovi qui.
          </div>
        )}

        {projectItems.map((it, projectIdx) => {
          const dup = isDuplicate(it);
          return (
            <div
              key={`pp-${projectIdx}`}
              className={[
                'bg-white border rounded-lg p-4',
                dup ? 'border-red-300 bg-red-50/30' : 'border-gray-200',
              ].join(' ')}
            >
              <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                <div className="md:col-span-4">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Nome pillar *</label>
                  <input
                    type="text"
                    value={it.name ?? ''}
                    onChange={(e) => updateProjectPillar(projectIdx, { name: e.target.value.slice(0, NAME_MAX) })}
                    placeholder="Es. Lancio Q4"
                    className={[
                      'w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:border-transparent',
                      dup ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:ring-[#3DAFA8]',
                    ].join(' ')}
                  />
                  {dup && (
                    <p className="text-xs text-red-600 mt-1 flex items-center gap-1">
                      <AlertCircle size={12} /> Nome duplicato
                    </p>
                  )}
                </div>

                <div className="md:col-span-7">
                  <label className="block text-xs font-medium text-gray-600 mb-1">Descrizione *</label>
                  <textarea
                    value={it.description ?? ''}
                    onChange={(e) => updateProjectPillar(projectIdx, { description: e.target.value.slice(0, DESC_MAX) })}
                    rows={2}
                    placeholder="1-2 frasi su cosa contiene questo pillar"
                    className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-none"
                  />
                  <span className="text-[11px] text-gray-400">
                    {(it.description ?? '').length}/{DESC_MAX}
                  </span>
                </div>

                <div className="md:col-span-1 flex md:justify-end pt-1 md:pt-6">
                  <button
                    type="button"
                    onClick={() => removeProjectPillar(projectIdx)}
                    className="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Rimuovi pillar specifico ${projectIdx + 1}`}
                  >
                    <Trash2 size={16} />
                  </button>
                </div>
              </div>
            </div>
          );
        })}

        <button
          type="button"
          onClick={addProjectPillar}
          disabled={total >= MAX_PILLARS}
          className={[
            'flex items-center gap-2 px-4 py-2 border border-dashed rounded-lg text-sm transition-colors',
            total >= MAX_PILLARS
              ? 'border-gray-200 text-gray-300 cursor-not-allowed'
              : 'border-[#3DAFA8] text-[#3DAFA8] hover:bg-teal-50',
          ].join(' ')}
        >
          <Plus size={16} /> Aggiungi pillar specifico
        </button>
      </div>

      {/* ── Toggle promote-to-brand ────────────────────────────── */}
      {hasNewToPromote && (
        <label className="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 cursor-pointer">
          <input
            type="checkbox"
            checked={promoteEnabled}
            onChange={(e) => onPromoteEnabledChange?.(e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
          />
          <div className="text-sm">
            <p className="font-medium text-[#2C3E50]">
              Salva i nuovi pillar nella libreria del brand
            </p>
            <p className="text-xs text-gray-600 mt-0.5">
              I {newProjectPillars.length} pillar specifici aggiunti diventeranno disponibili come default per i prossimi project del brand.
            </p>
          </div>
        </label>
      )}
    </div>
  );
}

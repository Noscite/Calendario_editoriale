import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  FileText, Users, Target, Settings, Loader2, Linkedin, Instagram, Facebook, MapPin,
} from 'lucide-react';
import Wizard from '../components/Wizard';
import BrandCompletenessIndicator from '../components/BrandCompletenessIndicator';
import AISuggestionPersonasCard from '../components/AISuggestionPersonasCard';
import ContentPillarsSelector from '../components/ContentPillarsSelector';
import SpecialDatesEditor from '../components/SpecialDatesEditor';
import TagsInput from '../components/TagsInput';
import {
  brands as brandsApi,
  projects as projectsApi,
} from '../services/api';

const STEPS = [
  { id: 1, title: 'Obiettivi & Contesto', icon: FileText },
  { id: 2, title: 'Audience & Personas',  icon: Users },
  { id: 3, title: 'Pillar & Competitor',  icon: Target },
  { id: 4, title: 'Configurazione',       icon: Settings },
];

const PLATFORMS = [
  { id: 'linkedin',         name: 'LinkedIn',         icon: Linkedin, defaultPpw: 3 },
  { id: 'instagram',        name: 'Instagram',        icon: Instagram, defaultPpw: 4 },
  { id: 'facebook',         name: 'Facebook',         icon: Facebook, defaultPpw: 2 },
  { id: 'google_business',  name: 'Google Business',  icon: MapPin, defaultPpw: 2 },
];

const TARGET_AUDIENCE_MIN = 50;
const MIN_PILLARS = 3;
const MAX_PILLARS = 6;

function clampStep(n) {
  if (!Number.isFinite(n)) return null;
  if (n < 2) return 2;
  if (n > 4) return 4;
  return n;
}

function normalizeName(s) {
  return String(s ?? '').trim().toLowerCase();
}

function normalizePillars(pillars) {
  if (!Array.isArray(pillars)) return [];
  return pillars
    .map((p) => {
      if (typeof p === 'string') {
        return { name: p.trim(), description: '', source: 'project' };
      }
      if (p && typeof p === 'object') {
        return {
          name: (p.name || '').trim(),
          description: (p.description || '').trim(),
          source: p.source === 'brand' ? 'brand' : 'project',
        };
      }
      return null;
    })
    .filter((p) => p && p.name);
}

/**
 * Sintetizza una stringa target_audience leggibile dalle buyer_personas
 * confermate. Usato per auto-popolare la textarea Step 2 quando l'utente
 * conferma le personas senza aver editato manualmente il target_audience.
 *
 * Output: ~150-300 char tipici, max 3 personas inline + "+ N altri".
 * Se input vuoto/non valido: stringa vuota.
 */
function synthesizeTargetAudienceFromPersonas(buyerPersonas) {
  const list = Array.isArray(buyerPersonas?.personas) ? buyerPersonas.personas : [];
  if (list.length === 0) return '';

  const summarize = (p) => {
    const demo = p?.demographics ?? {};
    const role = (demo.role || p?.name || 'Profilo').toString().trim();
    const ageRange = (demo.age_range || '').toString().trim();
    const location = (demo.location || '').toString().trim();
    const painPoints = Array.isArray(p?.pain_points)
      ? p.pain_points.slice(0, 2).map((s) => String(s).trim()).filter(Boolean).join(', ')
      : '';

    const parenthetical = [ageRange, location].filter(Boolean).join(', ');
    const main = parenthetical ? `${role} (${parenthetical})` : role;
    return painPoints ? `${main} — pain: ${painPoints}` : main;
  };

  const top = list.slice(0, 3).map(summarize);
  const remaining = list.length - 3;

  let result = `Audience principale del progetto: ${top.join('; ')}`;
  if (remaining > 0) result += `; +${remaining} altri profili`;
  result += '.';
  return result;
}

/**
 * Pre-popola pillar dal brand quando il project è "vuoto" (nessun
 * content_pillars salvato). Marca tutti i pillar di brand come source='brand'.
 */
function seedFromBrandPillars(brandPillars) {
  if (!Array.isArray(brandPillars)) return [];
  return brandPillars
    .map((bp) => ({
      name: (bp.name || '').trim(),
      description: (bp.description || '').trim(),
      source: 'brand',
    }))
    .filter((p) => p.name);
}

export default function EditProjectWizardV2() {
  const { brandId: routeBrandId, projectId: routeProjectId } = useParams();
  const brandId = Number(routeBrandId);
  const projectId = Number(routeProjectId);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const stepFromQuery = clampStep(parseInt(searchParams.get('step') || '', 10));

  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);
  const [currentStep, setCurrentStep] = useState(stepFromQuery ?? 2);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [validationError, setValidationError] = useState(null);
  const [brandCompleteness, setBrandCompleteness] = useState(null);
  const [brandDefaultPillars, setBrandDefaultPillars] = useState([]);
  const [promotePillarsEnabled, setPromotePillarsEnabled] = useState(false);
  // Wizard PR-2 hotfix: dirty flag per target_audience — se l'utente ha
  // editato manualmente la textarea, l'auto-popolazione da personas è bypassata.
  const [targetAudienceDirty, setTargetAudienceDirty] = useState(false);

  const [formData, setFormData] = useState({
    name: '',
    brief: '',
    objectives: [],
    target_audience: '',
    buyer_personas: null,
    content_pillars: [],
    competitors: [],
    platforms: [],
    posts_per_week: {},
    reference_urls: [],
    special_dates: [],
  });

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  // ── Mount: 2 chiamate parallele (project + brand) ──────────
  useEffect(() => {
    let cancelled = false;
    if (!brandId || !projectId) {
      setLoadError('ID brand o project non valido.');
      setLoading(false);
      return;
    }

    (async () => {
      try {
        const [projectRes, brandRes, complRes] = await Promise.all([
          projectsApi.get(projectId),
          brandsApi.get(brandId),
          brandsApi.completeness(brandId).catch(() => null),
        ]);
        if (cancelled) return;

        const p = projectRes.data || {};
        const b = brandRes.data || {};

        const brandPillars = Array.isArray(b.default_content_pillars) ? b.default_content_pillars : [];
        setBrandDefaultPillars(brandPillars);

        // Se il project non ha content_pillars salvati, pre-popola con i brand pillars
        const persistedPillars = normalizePillars(p.content_pillars);
        const seededPillars =
          persistedPillars.length === 0
            ? seedFromBrandPillars(brandPillars)
            : persistedPillars;

        setFormData({
          name:            p.name || '',
          brief:           p.brief || '',
          objectives:      Array.isArray(p.objectives) ? p.objectives : [],
          target_audience: p.target_audience || '',
          buyer_personas:  p.buyer_personas || null,
          content_pillars: seededPillars,
          competitors:     Array.isArray(p.competitors) ? p.competitors : [],
          platforms:       Array.isArray(p.platforms) ? p.platforms : [],
          posts_per_week:  p.posts_per_week && typeof p.posts_per_week === 'object'
            ? p.posts_per_week
            : {},
          reference_urls:  Array.isArray(p.reference_urls) ? p.reference_urls : [],
          special_dates:   Array.isArray(p.special_dates) ? p.special_dates : [],
        });

        if (complRes?.data) setBrandCompleteness(complRes.data);
      } catch (e) {
        if (cancelled) return;
        const msg =
          e?.response?.data?.message ||
          e?.response?.data?.detail ||
          e?.message ||
          'Errore nel caricamento del project.';
        setLoadError(msg);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [brandId, projectId]);

  // ── Validation per step ───────────────────────────────────
  const validateStep = (step) => {
    if (step === 2) {
      const ta = (formData.target_audience || '').trim();
      if (ta.length < TARGET_AUDIENCE_MIN) {
        return `Il target audience deve avere almeno ${TARGET_AUDIENCE_MIN} caratteri (attuali: ${ta.length}).`;
      }
      const personasConfirmed = formData.buyer_personas?.confirmed === true;
      if (!personasConfirmed) {
        return 'Conferma le personas dalla card AI prima di procedere.';
      }
      return null;
    }
    if (step === 3) {
      const pillars = formData.content_pillars || [];
      if (pillars.length < MIN_PILLARS) {
        return `Servono almeno ${MIN_PILLARS} pillar (attuali: ${pillars.length}).`;
      }
      if (pillars.length > MAX_PILLARS) {
        return `Massimo ${MAX_PILLARS} pillar (attuali: ${pillars.length}).`;
      }
      // Dedup case-insensitive
      const seen = new Map();
      for (const p of pillars) {
        const key = normalizeName(p.name);
        if (!key) return 'Tutti i pillar devono avere un nome non vuoto.';
        if (seen.has(key)) return `Pillar duplicato: "${p.name}".`;
        seen.set(key, true);
      }
      return null;
    }
    if (step === 4) {
      if (!Array.isArray(formData.platforms) || formData.platforms.length < 1) {
        return 'Seleziona almeno una piattaforma.';
      }
      for (const platform of formData.platforms) {
        const ppw = formData.posts_per_week?.[platform];
        const n = parseInt(ppw, 10);
        if (!Number.isFinite(n) || n < 1 || n > 7) {
          return `Frequenza non valida per ${platform}: deve essere tra 1 e 7 post/settimana.`;
        }
      }
      return null;
    }
    return null;
  };

  const buildPayloadForStep = (step) => {
    if (step === 2) {
      return { target_audience: formData.target_audience.trim() };
    }
    if (step === 3) {
      // Persistiamo content_pillars come array di STRINGHE (solo i name).
      // Il sistema legacy (ProjectDetail render, PromptBuilder, matchPillar)
      // si aspetta strings — gli objects {name, description} sono shape di
      // Brand.default_content_pillars, non di Project.content_pillars.
      // La description vive a livello brand (vedi promote-to-brand opt-in).
      const cleanPillarNames = (formData.content_pillars || [])
        .map((p) => (typeof p === 'string' ? p : (p?.name || '')).trim())
        .filter(Boolean);
      return {
        content_pillars: cleanPillarNames,
        competitors: Array.isArray(formData.competitors) ? formData.competitors : [],
      };
    }
    if (step === 4) {
      return {
        platforms: formData.platforms,
        posts_per_week: formData.posts_per_week,
        reference_urls: formData.reference_urls,
        special_dates: formData.special_dates,
      };
    }
    return null;
  };

  // ── Step 2 callback dalla card AI ─────────────────────────
  const handlePersonasConfirm = (buyerPersonas) => {
    setFormData((prev) => {
      const next = { ...prev, buyer_personas: buyerPersonas };
      // Auto-popolazione target_audience: solo se l'utente non ha mai editato
      // manualmente la textarea (dirty=false) E il valore corrente è sotto la
      // soglia di validation (typicamente vuoto al primo entry).
      const currentTaLen = (prev.target_audience || '').trim().length;
      if (!targetAudienceDirty && currentTaLen < TARGET_AUDIENCE_MIN) {
        const synth = synthesizeTargetAudienceFromPersonas(buyerPersonas);
        if (synth) {
          next.target_audience = synth;
        }
      }
      return next;
    });
  };

  // ── Click "Avanti" ────────────────────────────────────────
  const handleNext = async () => {
    setValidationError(null);

    if (currentStep === 4) {
      // Submit finale: salva e exit
      finalizeAndExit();
      return;
    }

    const err = validateStep(currentStep);
    if (err) {
      setValidationError(err);
      return;
    }

    const payload = buildPayloadForStep(currentStep);

    setIsSubmitting(true);
    try {
      if (payload) {
        await projectsApi.patch(projectId, payload);
      }

      // Step 3: promote-to-brand opt-in
      if (currentStep === 3 && promotePillarsEnabled) {
        const newPillars = (formData.content_pillars || [])
          .filter((p) => p.source !== 'brand')
          .filter((p) => {
            const key = normalizeName(p.name);
            return key && !brandDefaultPillars.some((bp) => normalizeName(bp.name) === key);
          })
          .map((p) => ({ name: p.name, description: p.description || '' }));

        if (newPillars.length > 0) {
          try {
            const res = await projectsApi.promotePillarsToBrand(projectId, newPillars);
            const added = res?.data?.added_count ?? 0;
            const dropped = res?.data?.dropped_count ?? 0;
            const msg = dropped > 0
              ? `${added} pillar aggiunti alla libreria del brand (${dropped} pillar più vecchi rimossi)`
              : `${added} pillar aggiunti alla libreria del brand`;
            // Toast leggero via sessionStorage one-shot — la pagina brand legge al rientro
            // (qui mostriamo banner inline non-bloccante)
            console.info('[wizard-pr2]', msg);
          } catch (promoteErr) {
            console.warn('[wizard-pr2] promote-to-brand fallito:', promoteErr?.message);
          }
        }
      }

      setCurrentStep((s) => Math.min(STEPS.length, s + 1));
    } catch (e) {
      const data = e?.response?.data;
      const fieldErrors = data?.errors;
      const msg =
        data?.message ||
        data?.detail ||
        (fieldErrors ? JSON.stringify(fieldErrors) : null) ||
        e?.message ||
        'Errore durante il salvataggio.';
      setValidationError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleBack = () => {
    setValidationError(null);
    if (currentStep > 2) setCurrentStep(currentStep - 1);
    else navigate(`/brand/${brandId}/new-project-v2`);
  };

  const finalizeAndExit = async () => {
    setValidationError(null);

    const err = validateStep(4);
    if (err) {
      setValidationError(err);
      return;
    }

    setIsSubmitting(true);
    try {
      await projectsApi.patch(projectId, buildPayloadForStep(4));

      try {
        sessionStorage.setItem(
          'kalendarium:project-wizard-toast',
          JSON.stringify({
            projectId,
            brandId,
            message: 'Project pronto. Genera il calendario quando vuoi.',
            ts: Date.now(),
          }),
        );
      } catch {
        /* sessionStorage non disponibile */
      }

      navigate(`/brand/${brandId}`);
    } catch (e) {
      const data = e?.response?.data;
      const msg =
        data?.message ||
        data?.detail ||
        e?.message ||
        'Errore durante il salvataggio finale.';
      setValidationError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  // ── Header ────────────────────────────────────────────────
  const wizardHeader = useMemo(() => {
    return (
      <div className="space-y-3">
        <div>
          <h1 className="text-2xl font-bold text-[#2C3E50]">Configurazione del calendario editoriale</h1>
          <p className="text-sm text-gray-500 mt-1">
            Step {currentStep} di {STEPS.length} · salvataggio automatico tra uno step e l'altro.
          </p>
        </div>
        {brandCompleteness && brandCompleteness.score < (brandCompleteness.threshold ?? 70) && (
          <BrandCompletenessIndicator
            score={brandCompleteness.score}
            threshold={brandCompleteness.threshold ?? 70}
            sections={brandCompleteness.sections || {}}
            missing={brandCompleteness.missing || []}
            onClickMissing={() => navigate(`/brand/${brandId}/wizard`)}
          />
        )}
      </div>
    );
  }, [brandCompleteness, currentStep, brandId, navigate]);

  // ── Loading / error gating ────────────────────────────────
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="flex items-center gap-2 text-gray-500">
          <Loader2 size={18} className="animate-spin" /> Caricamento project…
        </div>
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div className="bg-white shadow-sm rounded-xl p-6 max-w-md text-center">
          <p className="text-red-600 font-medium mb-2">Impossibile aprire il wizard</p>
          <p className="text-sm text-gray-500 mb-4">{loadError}</p>
          <button
            onClick={() => navigate(`/brand/${brandId}`)}
            className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] text-sm"
          >
            Torna al brand
          </button>
        </div>
      </div>
    );
  }

  // ── Step bodies ───────────────────────────────────────────
  const renderStep = () => {
    if (currentStep === 2) {
      const taLen = (formData.target_audience || '').trim().length;
      const taValid = taLen >= TARGET_AUDIENCE_MIN;
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Audience & Personas</h2>
            <p className="text-sm text-gray-500">
              Definisci il target audience del project; l'AI valuterà se riutilizzare personas storiche del brand.
            </p>
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-sm font-medium text-[#2C3E50]">Target audience *</label>
              <span className={`text-xs ${taValid ? 'text-gray-500' : 'text-amber-600'}`}>
                {taLen} caratteri {!taValid && `(min ${TARGET_AUDIENCE_MIN})`}
              </span>
            </div>
            <textarea
              value={formData.target_audience}
              onChange={(e) => {
                if (!targetAudienceDirty) setTargetAudienceDirty(true);
                updateField('target_audience', e.target.value);
              }}
              rows={4}
              placeholder="Chi è il target di questo project? Demografia, ruolo, sfide, contesto."
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
            />
            {!targetAudienceDirty && taLen > 0 && (
              <p className="text-xs text-gray-500 mt-1 flex items-center gap-1">
                <span aria-hidden>✨</span>
                Compilato automaticamente dalle personas confermate. Modifica liberamente.
              </p>
            )}
          </div>

          <AISuggestionPersonasCard
            projectId={projectId}
            onConfirm={handlePersonasConfirm}
          />
        </div>
      );
    }

    if (currentStep === 3) {
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Pillar & Competitor</h2>
            <p className="text-sm text-gray-500">
              Configura i pillar di contenuto e i competitor di riferimento per questo project.
            </p>
          </div>

          <ContentPillarsSelector
            value={formData.content_pillars}
            onChange={(arr) => updateField('content_pillars', arr)}
            brandDefaultPillars={brandDefaultPillars}
            promoteEnabled={promotePillarsEnabled}
            onPromoteEnabledChange={setPromotePillarsEnabled}
          />

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">
              Competitor <span className="text-gray-400 font-normal">(opzionale)</span>
            </label>
            <TagsInput
              value={formData.competitors}
              onChange={(arr) => updateField('competitors', arr)}
              placeholder="Aggiungi un competitor e premi Invio"
              ariaLabel="Competitor"
              helper="Premi Invio o Tab dopo ogni nome. L'AI eviterà di citarli direttamente."
            />
          </div>
        </div>
      );
    }

    if (currentStep === 4) {
      const recommendedPpw = formData.buyer_personas?.recommended_posts_per_week || {};
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Configurazione tecnica</h2>
            <p className="text-sm text-gray-500">
              Piattaforme, frequenza, riferimenti esterni e date speciali.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-2">
              Piattaforme * <span className="text-gray-400 font-normal">(seleziona almeno una)</span>
            </label>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
              {PLATFORMS.map((p) => {
                const checked = formData.platforms.includes(p.id);
                const Icon = p.icon;
                return (
                  <label
                    key={p.id}
                    className={[
                      'flex items-center gap-2 px-3 py-2 border rounded-lg cursor-pointer transition-colors text-sm',
                      checked
                        ? 'border-[#3DAFA8] bg-teal-50 text-[#2C3E50]'
                        : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                    ].join(' ')}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => {
                        const set = new Set(formData.platforms);
                        if (set.has(p.id)) set.delete(p.id);
                        else set.add(p.id);
                        const next = Array.from(set);
                        updateField('platforms', next);
                        // Inizializza ppw default per nuove piattaforme
                        const ppw = { ...formData.posts_per_week };
                        for (const pid of next) {
                          if (ppw[pid] == null) {
                            ppw[pid] = recommendedPpw[pid] ?? PLATFORMS.find((x) => x.id === pid)?.defaultPpw ?? 2;
                          }
                        }
                        // Drop ppw per piattaforme deselezionate
                        for (const k of Object.keys(ppw)) {
                          if (!next.includes(k)) delete ppw[k];
                        }
                        updateField('posts_per_week', ppw);
                      }}
                      className="h-4 w-4 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
                    />
                    <Icon size={16} />
                    {p.name}
                  </label>
                );
              })}
            </div>
          </div>

          {formData.platforms.length > 0 && (
            <div>
              <label className="block text-sm font-medium text-[#2C3E50] mb-2">
                Frequenza post per settimana
              </label>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {formData.platforms.map((pid) => {
                  const platform = PLATFORMS.find((x) => x.id === pid);
                  const Icon = platform?.icon;
                  return (
                    <div key={pid} className="flex items-center gap-3 px-3 py-2 bg-gray-50 rounded-lg">
                      {Icon && <Icon size={16} className="text-gray-500" />}
                      <span className="text-sm flex-1">{platform?.name ?? pid}</span>
                      <input
                        type="number"
                        min={1}
                        max={7}
                        value={formData.posts_per_week[pid] ?? ''}
                        onChange={(e) => {
                          const v = parseInt(e.target.value, 10);
                          updateField('posts_per_week', {
                            ...formData.posts_per_week,
                            [pid]: Number.isFinite(v) ? v : '',
                          });
                        }}
                        className="w-20 px-2 py-1 border border-gray-300 rounded text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                      />
                      <span className="text-xs text-gray-500">post/sett</span>
                    </div>
                  );
                })}
              </div>
              {Object.keys(recommendedPpw).length > 0 && (
                <p className="text-xs text-gray-500 mt-2">
                  Suggerimento AI dalle personas: {Object.entries(recommendedPpw)
                    .map(([k, v]) => `${k}: ${v}`)
                    .join(', ')}
                </p>
              )}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">
              URL di riferimento <span className="text-gray-400 font-normal">(opzionale)</span>
            </label>
            <TagsInput
              value={formData.reference_urls}
              onChange={(arr) => updateField('reference_urls', arr)}
              placeholder="https://..."
              ariaLabel="URL di riferimento"
              helper="L'AI analizzerà queste URL per estrarre contesto sul brand."
            />
          </div>

          <SpecialDatesEditor
            value={formData.special_dates}
            onChange={(arr) => updateField('special_dates', arr)}
          />
        </div>
      );
    }

    return null;
  };

  return (
    <Wizard
      steps={STEPS}
      currentStepId={currentStep}
      onStepChange={(s) => {
        setValidationError(null);
        // Permetti retro-navigazione tra step già visitati (Wizard component
        // fa il check su id < currentStepId), ma blocca step 1 (su altra pagina)
        if (s >= 2) setCurrentStep(s);
      }}
      onNext={handleNext}
      onBack={handleBack}
      onSubmit={handleNext}
      header={wizardHeader}
      isSubmitting={isSubmitting}
      canGoNext={!isSubmitting}
      nextLabel="Salva e prosegui"
      errorMessage={validationError}
    >
      {renderStep()}
    </Wizard>
  );
}

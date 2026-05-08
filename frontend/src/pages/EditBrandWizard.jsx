import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  Sparkles, Mic, Library, Target, FileText, Loader2, Plus, Info,
} from 'lucide-react';
import Wizard from '../components/Wizard';
import BrandCompletenessIndicator from '../components/BrandCompletenessIndicator';
import VoiceExamplesEditor from '../components/VoiceExamplesEditor';
import NarrativeAssetsEditor from '../components/NarrativeAssetsEditor';
import DefaultPillarsEditor from '../components/DefaultPillarsEditor';
import BrandDocuments from '../components/BrandDocuments';
import TagsInput from '../components/TagsInput';
import { brands as brandsApi } from '../services/api';

const STEPS = [
  { id: 1, title: 'Identità',         icon: Sparkles },
  { id: 2, title: 'Voce',             icon: Mic },
  { id: 3, title: 'Asset narrativi',  icon: Library },
  { id: 4, title: 'USP & Pillar',     icon: Target },
  { id: 5, title: 'Knowledge base',   icon: FileText },
];

const SECTION_TO_STEP = {
  identity: 1,
  voice: 2,
  narrative_assets: 3,
  usp_pillars: 4,
  kb: 5,
};

const SECTION_ORDER = ['identity', 'voice', 'narrative_assets', 'usp_pillars', 'kb'];

const DESCRIPTION_MIN = 80;
const NAME_MAX = 180;
const TAGLINE_MAX = 180;
const BIO_SHORT_MAX = 240;

function clampStep(n) {
  if (!Number.isFinite(n)) return null;
  if (n < 1) return 1;
  if (n > STEPS.length) return STEPS.length;
  return n;
}

/**
 * Normalizza brand_values in array di stringhe per la TagsInput, qualunque
 * sia la shape persistita (string CSV, array di stringhe, array di oggetti).
 */
function normalizeBrandValuesToTags(value) {
  if (!value) return [];
  if (Array.isArray(value)) {
    return value
      .map((v) => {
        if (typeof v === 'string') return v.trim();
        if (v && typeof v === 'object') return String(v.name ?? v.label ?? v.value ?? '').trim();
        return '';
      })
      .filter(Boolean);
  }
  if (typeof value === 'string') {
    return value.split(',').map((s) => s.trim()).filter(Boolean);
  }
  return [];
}

function normalizeStringArray(value) {
  if (!value) return [];
  if (Array.isArray(value)) return value.map((s) => String(s).trim()).filter(Boolean);
  if (typeof value === 'string') return value.split(',').map((s) => s.trim()).filter(Boolean);
  return [];
}

export default function EditBrandWizard() {
  const { id: routeId } = useParams();
  const brandId = Number(routeId);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const stepFromQuery = clampStep(parseInt(searchParams.get('step') || '', 10));

  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(null);
  const [currentStep, setCurrentStep] = useState(stepFromQuery ?? 1);
  const [completeness, setCompleteness] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [validationError, setValidationError] = useState(null);

  const [formData, setFormData] = useState({
    name: '',
    tagline: '',
    description: '',
    sector: '',
    tone_of_voice: '',
    voice_examples: [],
    style_guide: '',
    founder: { name: '', role: '', bio_short: '' },
    narrative_assets: [],
    unique_selling_points: '',
    brand_values: [],
    forbidden_topics: [],
    default_content_pillars: [],
  });

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const updateFounderField = (key, value) => {
    setFormData((prev) => ({
      ...prev,
      founder: { ...(prev.founder || {}), [key]: value },
    }));
  };

  const refreshCompleteness = async () => {
    try {
      const res = await brandsApi.completeness(brandId);
      setCompleteness(res.data);
      return res.data;
    } catch {
      return null;
    }
  };

  useEffect(() => {
    let cancelled = false;
    if (!brandId) {
      setLoadError('ID brand non valido.');
      setLoading(false);
      return;
    }

    (async () => {
      try {
        const [brandRes, complRes] = await Promise.all([
          brandsApi.get(brandId),
          brandsApi.completeness(brandId),
        ]);
        if (cancelled) return;

        const b = brandRes.data || {};
        setFormData({
          name: b.name || '',
          tagline: b.tagline || '',
          description: b.description || '',
          sector: b.sector || '',
          tone_of_voice: b.tone_of_voice || '',
          voice_examples: Array.isArray(b.voice_examples) ? b.voice_examples : [],
          style_guide: b.style_guide || '',
          founder: {
            name: b.founder?.name || '',
            role: b.founder?.role || '',
            bio_short: b.founder?.bio_short || '',
          },
          narrative_assets: Array.isArray(b.narrative_assets) ? b.narrative_assets : [],
          unique_selling_points: b.unique_selling_points || '',
          brand_values: normalizeBrandValuesToTags(b.brand_values),
          forbidden_topics: normalizeStringArray(b.forbidden_topics),
          default_content_pillars: Array.isArray(b.default_content_pillars)
            ? b.default_content_pillars
            : [],
        });

        const compl = complRes.data || null;
        setCompleteness(compl);

        if (stepFromQuery) {
          setCurrentStep(stepFromQuery);
        } else if (compl?.sections) {
          const firstIncomplete = SECTION_ORDER.find(
            (key) => compl.sections[key] && compl.sections[key].complete === false
          );
          setCurrentStep(firstIncomplete ? SECTION_TO_STEP[firstIncomplete] : 1);
        } else {
          setCurrentStep(1);
        }
      } catch (e) {
        if (cancelled) return;
        const msg =
          e?.response?.data?.message ||
          e?.response?.data?.detail ||
          e?.message ||
          'Errore nel caricamento del brand.';
        setLoadError(msg);
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [brandId]);

  const validateStep = (step) => {
    if (step === 1) {
      const name = (formData.name || '').trim();
      const description = (formData.description || '').trim();
      const sector = (formData.sector || '').trim();
      if (!name) return 'Il nome del brand è obbligatorio.';
      if (!description) return 'La descrizione del brand è obbligatoria.';
      if (description.length < DESCRIPTION_MIN) {
        return `La descrizione deve avere almeno ${DESCRIPTION_MIN} caratteri (attuali: ${description.length}).`;
      }
      if (!sector) return 'Il settore è obbligatorio.';
      return null;
    }
    if (step === 2) {
      const tone = (formData.tone_of_voice || '').trim();
      if (!tone) return 'Il tono di voce è obbligatorio.';
      return null;
    }
    if (step === 4) {
      const usp = (formData.unique_selling_points || '').trim();
      if (!usp) return 'Inserisci almeno una USP / differenziatore del brand.';
      return null;
    }
    return null;
  };

  const buildPayloadForStep = (step) => {
    if (step === 1) {
      return {
        name: formData.name.trim(),
        tagline: (formData.tagline || '').trim() || null,
        description: formData.description.trim(),
        sector: formData.sector.trim(),
      };
    }
    if (step === 2) {
      return {
        tone_of_voice: formData.tone_of_voice.trim(),
        style_guide: (formData.style_guide || '').trim() || null,
      };
    }
    if (step === 3) {
      const f = formData.founder || {};
      const founderClean = {
        name: (f.name || '').trim(),
        role: (f.role || '').trim(),
        bio_short: (f.bio_short || '').trim(),
      };
      const founderHasContent = Object.values(founderClean).some((v) => v !== '');
      return {
        founder: founderHasContent ? founderClean : null,
        narrative_assets: Array.isArray(formData.narrative_assets)
          ? formData.narrative_assets
              .map((a) => ({
                type: a.type,
                name: (a.name || '').trim(),
                details: (a.details || '').trim(),
              }))
              .filter((a) => a.name)
          : [],
      };
    }
    if (step === 4) {
      return {
        unique_selling_points: formData.unique_selling_points.trim(),
        brand_values: formData.brand_values || [],
        forbidden_topics: formData.forbidden_topics || [],
        default_content_pillars: Array.isArray(formData.default_content_pillars)
          ? formData.default_content_pillars
              .map((p) => ({
                name: (p.name || '').trim(),
                description: (p.description || '').trim(),
              }))
              .filter((p) => p.name)
          : [],
      };
    }
    return null;
  };

  const handleNext = async () => {
    setValidationError(null);

    if (currentStep === 5) {
      finalizeAndExit();
      return;
    }

    const err = validateStep(currentStep);
    if (err) {
      setValidationError(err);
      return;
    }

    const payload = buildPayloadForStep(currentStep);
    if (!payload) {
      setCurrentStep(currentStep + 1);
      return;
    }

    setIsSubmitting(true);
    try {
      await brandsApi.update(brandId, payload);
      await refreshCompleteness();
      setCurrentStep((s) => Math.min(STEPS.length, s + 1));
    } catch (e) {
      const msg =
        e?.response?.data?.message ||
        e?.response?.data?.detail ||
        e?.message ||
        'Errore durante il salvataggio.';
      setValidationError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleBack = () => {
    setValidationError(null);
    if (currentStep > 1) setCurrentStep(currentStep - 1);
  };

  const finalizeAndExit = () => {
    const score = completeness?.score ?? 0;
    try {
      sessionStorage.setItem(
        'kalendarium:brand-wizard-toast',
        JSON.stringify({
          brandId,
          score,
          message: `Brand pronto, completeness ${score}%`,
          ts: Date.now(),
        })
      );
    } catch {
      // sessionStorage non disponibile: silenzioso, la navigazione avviene comunque.
    }
    navigate(`/brand/${brandId}`);
  };

  const handleSkipFromStep5 = () => {
    finalizeAndExit();
  };

  const jumpToStepForSection = (sectionId) => {
    const target = SECTION_TO_STEP[sectionId];
    if (target) setCurrentStep(target);
  };

  const wizardHeader = useMemo(() => {
    if (!completeness) {
      return (
        <div className="text-sm text-gray-500 flex items-center gap-2">
          <Loader2 size={14} className="animate-spin" /> Calcolo completeness…
        </div>
      );
    }
    return (
      <BrandCompletenessIndicator
        score={completeness.score}
        threshold={completeness.threshold ?? 70}
        sections={completeness.sections || {}}
        missing={completeness.missing || []}
        onClickMissing={jumpToStepForSection}
      />
    );
  }, [completeness]);

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="flex items-center gap-2 text-gray-500">
          <Loader2 size={18} className="animate-spin" /> Caricamento brand…
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
            onClick={() => navigate('/brands')}
            className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] text-sm"
          >
            Torna ai brand
          </button>
        </div>
      </div>
    );
  }

  const renderStep = () => {
    if (currentStep === 1) {
      const descLen = (formData.description || '').trim().length;
      const descValid = descLen >= DESCRIPTION_MIN;
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Identità del brand</h2>
            <p className="text-sm text-gray-500">
              Aggiorna nome, tagline, descrizione e settore.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">Nome del brand *</label>
            <input
              type="text"
              value={formData.name}
              onChange={(e) => updateField('name', e.target.value.slice(0, NAME_MAX))}
              maxLength={NAME_MAX}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">
              Tagline <span className="text-gray-400 font-normal">(opzionale)</span>
            </label>
            <input
              type="text"
              value={formData.tagline}
              onChange={(e) => updateField('tagline', e.target.value.slice(0, TAGLINE_MAX))}
              maxLength={TAGLINE_MAX}
              placeholder="Una frase che rappresenta il brand"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
            <p className="text-xs text-gray-500 mt-1">Una frase che rappresenta il brand.</p>
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-sm font-medium text-[#2C3E50]">Descrizione *</label>
              <span className={`text-xs ${descValid ? 'text-gray-500' : 'text-amber-600'}`}>
                {descLen} caratteri {!descValid && `(min ${DESCRIPTION_MIN})`}
              </span>
            </div>
            <textarea
              value={formData.description}
              onChange={(e) => updateField('description', e.target.value)}
              rows={5}
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
            />
            <p className="text-xs text-gray-500 mt-1">
              Minimo {DESCRIPTION_MIN} caratteri — missione, target e differenziatori.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">Settore *</label>
            <input
              type="text"
              value={formData.sector}
              onChange={(e) => updateField('sector', e.target.value)}
              placeholder="Es. Formazione AI, Ristorazione, Studio legale"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
            <p className="text-xs text-gray-500 mt-1">
              Es. Formazione AI, Ristorazione, Studio legale.
            </p>
          </div>
        </div>
      );
    }

    if (currentStep === 2) {
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Voce del brand</h2>
            <p className="text-sm text-gray-500">
              Definisci tono, esempi reali di post e regole di stile.
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">Tono di voce *</label>
            <input
              type="text"
              value={formData.tone_of_voice}
              onChange={(e) => updateField('tone_of_voice', e.target.value)}
              placeholder="Es. Diretto, autorevole ma accessibile, leggermente ironico"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
          </div>

          <VoiceExamplesEditor
            brandId={brandId}
            initialValue={formData.voice_examples}
            onSaved={(updated) => {
              updateField('voice_examples', updated);
              refreshCompleteness();
            }}
          />

          <div>
            <label className="block text-sm font-medium text-[#2C3E50] mb-1">Style guide</label>
            <textarea
              value={formData.style_guide}
              onChange={(e) => updateField('style_guide', e.target.value)}
              rows={4}
              placeholder="Regole su lessico, lunghezze, emoji, hashtag, persona narrante…"
              className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
            />
            <p className="text-xs text-gray-500 mt-1">Regole di stile e comunicazione.</p>
          </div>
        </div>
      );
    }

    if (currentStep === 3) {
      return (
        <div className="space-y-8">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Asset narrativi</h2>
            <p className="text-sm text-gray-500">
              Founder (se rilevante) e gli asset reali che l'AI può citare.
            </p>
          </div>

          <div className="bg-gray-50 border border-gray-200 rounded-xl p-5 space-y-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                <h4 className="font-semibold text-[#2C3E50]">Founder (opzionale)</h4>
                <p className="text-xs text-gray-500 mt-1 flex items-start gap-1">
                  <Info size={12} className="mt-0.5 text-gray-400" />
                  I brand corporate possono saltare questa sezione.
                </p>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                <input
                  type="text"
                  value={formData.founder?.name || ''}
                  onChange={(e) => updateFounderField('name', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 mb-1">Ruolo</label>
                <input
                  type="text"
                  value={formData.founder?.role || ''}
                  onChange={(e) => updateFounderField('role', e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="block text-xs font-medium text-gray-600">Bio breve</label>
                <span className="text-[11px] text-gray-400">
                  {(formData.founder?.bio_short || '').length}/{BIO_SHORT_MAX}
                </span>
              </div>
              <textarea
                value={formData.founder?.bio_short || ''}
                onChange={(e) =>
                  updateFounderField('bio_short', e.target.value.slice(0, BIO_SHORT_MAX))
                }
                rows={3}
                placeholder="Background del founder, voce, autorevolezza…"
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
              />
            </div>
          </div>

          <NarrativeAssetsEditor
            value={formData.narrative_assets || []}
            onChange={(arr) => updateField('narrative_assets', arr)}
          />
        </div>
      );
    }

    if (currentStep === 4) {
      return (
        <div className="space-y-8">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">USP, valori & pillar</h2>
            <p className="text-sm text-gray-500">
              I differenziatori del brand, i valori che lo guidano e i pillar di contenuto di default.
            </p>
          </div>

          <div className="space-y-5">
            <div>
              <label className="block text-sm font-medium text-[#2C3E50] mb-1">
                Unique Selling Points *
              </label>
              <textarea
                value={formData.unique_selling_points}
                onChange={(e) => updateField('unique_selling_points', e.target.value)}
                rows={4}
                placeholder="Cosa rende unico questo brand rispetto ai competitor."
                className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-[#2C3E50] mb-1">
                Valori del brand <span className="text-gray-400 font-normal">(min 2)</span>
              </label>
              <TagsInput
                value={formData.brand_values}
                onChange={(arr) => updateField('brand_values', arr)}
                placeholder="Aggiungi un valore e premi Invio (es. trasparenza)"
                helper="Premi Invio dopo ogni valore. Servono almeno 2 valori per completare la sezione."
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-[#2C3E50] mb-1">
                Forbidden topics <span className="text-gray-400 font-normal">(opzionale)</span>
              </label>
              <TagsInput
                value={formData.forbidden_topics}
                onChange={(arr) => updateField('forbidden_topics', arr)}
                placeholder="Argomenti che il brand non deve toccare"
                helper="Premi Invio dopo ogni voce."
              />
            </div>
          </div>

          <DefaultPillarsEditor
            value={formData.default_content_pillars || []}
            onChange={(arr) => updateField('default_content_pillars', arr)}
          />
        </div>
      );
    }

    if (currentStep === 5) {
      return (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Knowledge base</h2>
            <p className="text-sm text-gray-500">
              Carica documenti aziendali (linee guida, presentazioni, case study) per arricchire i contenuti generati. Puoi aggiungere documenti anche in seguito.
            </p>
          </div>

          <BrandDocuments brandId={brandId} />

          <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-[#2C3E50]">
            <p className="font-medium mb-1">Hai finito qui?</p>
            <p className="text-gray-600">
              Puoi salvare e completare il wizard senza caricare nulla. La knowledge base resta arricchibile in qualunque momento dalla pagina del brand.
            </p>
          </div>
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
        setCurrentStep(s);
      }}
      onNext={handleNext}
      onBack={handleBack}
      onSubmit={handleNext}
      onSkip={currentStep === 5 ? handleSkipFromStep5 : null}
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

import { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FileText, Users, Target, Settings, Loader2 } from 'lucide-react';
import Wizard from '../components/Wizard';
import BrandCompletenessIndicator from '../components/BrandCompletenessIndicator';
import {
  brands as brandsApi,
  projects as projectsApi,
  editorialPresets as editorialPresetsApi,
} from '../services/api';

const STEPS = [
  { id: 1, title: 'Obiettivi & Contesto', icon: FileText },
  { id: 2, title: 'Audience & Personas',  icon: Users },
  { id: 3, title: 'Pillar & Competitor',  icon: Target },
  { id: 4, title: 'Configurazione',       icon: Settings },
];

const OBJECTIVES = [
  { value: 'lead_gen',         label: 'Lead generation' },
  { value: 'brand_awareness',  label: 'Brand awareness' },
  { value: 'traffic',          label: 'Traffico al sito' },
  { value: 'sales',            label: 'Vendite dirette' },
  { value: 'engagement',       label: 'Engagement community' },
  { value: 'community',        label: 'Costruzione community' },
];

const NAME_MAX = 180;
const BRIEF_MIN = 100;
const SPAN_MIN_DAYS = 30;
const SPAN_MAX_DAYS = 180;

function todayISO() {
  return new Date().toISOString().split('T')[0];
}

function defaultEndDate() {
  return new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
}

function daysBetween(startISO, endISO) {
  const a = new Date(startISO);
  const b = new Date(endISO);
  if (Number.isNaN(a.getTime()) || Number.isNaN(b.getTime())) return null;
  return Math.round((b - a) / (1000 * 60 * 60 * 24));
}

export default function CreateProjectWizardV2() {
  const { brandId: routeBrandId } = useParams();
  const brandId = Number(routeBrandId);
  const navigate = useNavigate();

  const [currentStep, setCurrentStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [validationError, setValidationError] = useState(null);
  const [brandCompleteness, setBrandCompleteness] = useState(null);
  const [presetOptions, setPresetOptions] = useState([]);

  const [formData, setFormData] = useState({
    name: '',
    brief: '',
    objectives: [],
    editorial_preset: 'standard',
    start_date: todayISO(),
    end_date: defaultEndDate(),
  });

  const updateField = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  // Mount: fetch brand completeness (warning soft <70%, non blocca)
  useEffect(() => {
    if (!brandId) return;
    let cancelled = false;
    brandsApi
      .completeness(brandId)
      .then((res) => {
        if (!cancelled) setBrandCompleteness(res.data);
      })
      .catch(() => {
        if (!cancelled) setBrandCompleteness(null);
      });
    return () => {
      cancelled = true;
    };
  }, [brandId]);

  // Mount: fetch opzioni preset editoriale per il select
  useEffect(() => {
    let cancelled = false;
    editorialPresetsApi
      .options()
      .then((res) => {
        if (!cancelled) setPresetOptions(res?.data?.data ?? []);
      })
      .catch(() => {
        if (!cancelled) setPresetOptions([]);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  const toggleObjective = (val) => {
    const set = new Set(formData.objectives);
    if (set.has(val)) set.delete(val);
    else set.add(val);
    updateField('objectives', Array.from(set));
  };

  const validateStep1 = () => {
    const name = (formData.name || '').trim();
    const brief = (formData.brief || '').trim();

    if (!name) return 'Il nome del project è obbligatorio.';
    if (name.length > NAME_MAX) return `Il nome non può superare ${NAME_MAX} caratteri.`;
    if (!brief) return 'Il brief è obbligatorio.';
    if (brief.length < BRIEF_MIN) {
      return `Il brief deve avere almeno ${BRIEF_MIN} caratteri (attuali: ${brief.length}).`;
    }
    if (!Array.isArray(formData.objectives) || formData.objectives.length < 1) {
      return 'Seleziona almeno un obiettivo.';
    }
    if (!formData.start_date || !formData.end_date) {
      return 'Le date di inizio e fine sono obbligatorie.';
    }
    if (formData.start_date >= formData.end_date) {
      return 'La data di inizio deve essere precedente alla data di fine.';
    }
    const span = daysBetween(formData.start_date, formData.end_date);
    if (span !== null && (span < SPAN_MIN_DAYS || span > SPAN_MAX_DAYS)) {
      return `La durata del project deve essere tra ${SPAN_MIN_DAYS} e ${SPAN_MAX_DAYS} giorni (attuale: ${span}).`;
    }
    return null;
  };

  const handleNext = async () => {
    setValidationError(null);

    const err = validateStep1();
    if (err) {
      setValidationError(err);
      return;
    }

    setIsSubmitting(true);
    try {
      const res = await projectsApi.create({
        brand_id: brandId,
        name: formData.name.trim(),
        brief: formData.brief.trim(),
        objectives: formData.objectives,
        editorial_preset: formData.editorial_preset,
        start_date: formData.start_date,
        end_date: formData.end_date,
      });
      const newProjectId = res?.data?.id;
      if (!newProjectId) {
        throw new Error('Risposta inattesa dal server: id mancante.');
      }

      // Fire-and-forget: dispatch del job AI personas in background
      projectsApi.evaluatePersonas(newProjectId).catch(() => {
        /* il polling lato Step 2 ricostruirà lo stato dal DB */
      });

      navigate(`/brand/${brandId}/edit-project-v2/${newProjectId}?step=2`, { replace: true });
    } catch (e) {
      const data = e?.response?.data;
      const fieldErrors = data?.errors;
      const msg =
        data?.message ||
        data?.detail ||
        (fieldErrors ? JSON.stringify(fieldErrors) : null) ||
        e?.message ||
        'Errore durante la creazione del project.';
      setValidationError(msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleBack = () => {
    setValidationError(null);
    if (currentStep > 1) setCurrentStep(currentStep - 1);
  };

  const briefLen = (formData.brief || '').trim().length;
  const briefValid = briefLen >= BRIEF_MIN;
  const span = daysBetween(formData.start_date, formData.end_date);

  const renderStep = () => (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Obiettivi & Contesto</h2>
        <p className="text-sm text-gray-500">
          Quattro informazioni base per impostare il project. Più sono precise, migliore sarà l'output dell'AI.
        </p>
      </div>

      <div>
        <label className="block text-sm font-medium text-[#2C3E50] mb-1">
          Nome del project *
        </label>
        <input
          type="text"
          value={formData.name}
          onChange={(e) => updateField('name', e.target.value.slice(0, NAME_MAX))}
          placeholder="Es. Lancio Q1 2026"
          maxLength={NAME_MAX}
          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
        />
      </div>

      <div>
        <div className="flex items-center justify-between mb-1">
          <label className="block text-sm font-medium text-[#2C3E50]">Brief *</label>
          <span className={`text-xs ${briefValid ? 'text-gray-500' : 'text-amber-600'}`}>
            {briefLen} caratteri {!briefValid && `(min ${BRIEF_MIN})`}
          </span>
        </div>
        <textarea
          value={formData.brief}
          onChange={(e) => updateField('brief', e.target.value)}
          rows={6}
          placeholder="Descrivi cosa vuoi ottenere con questo calendario editoriale, il contesto strategico, eventuali messaggi chiave da veicolare."
          className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
        />
        <p className="text-xs text-gray-500 mt-1">
          Minimo {BRIEF_MIN} caratteri. Sarà la base per la generazione delle personas e dei post.
        </p>
      </div>

      <div>
        <label className="block text-sm font-medium text-[#2C3E50] mb-2">
          Obiettivi del project * <span className="text-gray-400 font-normal">(seleziona uno o più)</span>
        </label>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
          {OBJECTIVES.map((obj) => {
            const checked = formData.objectives.includes(obj.value);
            return (
              <label
                key={obj.value}
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
                  onChange={() => toggleObjective(obj.value)}
                  className="h-4 w-4 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
                />
                {obj.label}
              </label>
            );
          })}
        </div>
      </div>

      {presetOptions.length > 0 && (
        <div>
          <label className="block text-sm font-medium text-[#2C3E50] mb-1">
            Preset editoriale
          </label>
          <select
            value={formData.editorial_preset}
            onChange={(e) => updateField('editorial_preset', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent bg-white"
          >
            {presetOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </select>
          <p className="text-xs text-gray-500 mt-1">
            "B2B Authority" applica una cadenza settimanale Lun→Ven con un tipo di post dedicato per ogni giorno.
          </p>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-[#2C3E50] mb-1">Data inizio *</label>
          <input
            type="date"
            value={formData.start_date}
            onChange={(e) => updateField('start_date', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
          />
        </div>
        <div>
          <label className="block text-sm font-medium text-[#2C3E50] mb-1">Data fine *</label>
          <input
            type="date"
            value={formData.end_date}
            onChange={(e) => updateField('end_date', e.target.value)}
            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
          />
        </div>
      </div>

      {span !== null && span > 0 && (
        <p className="text-xs text-gray-500">
          Durata: {span} giorni (consigliato {SPAN_MIN_DAYS}-{SPAN_MAX_DAYS}).
        </p>
      )}
    </div>
  );

  const wizardHeader = (
    <div className="space-y-3">
      <div>
        <h1 className="text-2xl font-bold text-[#2C3E50]">Crea il tuo calendario editoriale</h1>
        <p className="text-sm text-gray-500 mt-1">
          4 step in ~15 minuti, salvataggio automatico tra uno step e l'altro.
        </p>
      </div>
      {brandCompleteness && brandCompleteness.score < (brandCompleteness.threshold ?? 70) && (
        <div className="space-y-2">
          <BrandCompletenessIndicator
            score={brandCompleteness.score}
            threshold={brandCompleteness.threshold ?? 70}
            sections={brandCompleteness.sections || {}}
            missing={brandCompleteness.missing || []}
            onClickMissing={() => navigate(`/brand/${brandId}/wizard`)}
          />
          <p className="text-xs text-amber-700">
            Il brand è sotto la soglia di completezza ({brandCompleteness.threshold ?? 70}%).
            Puoi procedere ma per generare contenuti AI di qualità è consigliato completare prima il setup brand.
          </p>
        </div>
      )}
    </div>
  );

  if (!brandId) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-sm text-gray-500 flex items-center gap-2">
          <Loader2 size={16} className="animate-spin" /> ID brand non valido.
        </div>
      </div>
    );
  }

  return (
    <Wizard
      steps={STEPS}
      currentStepId={currentStep}
      onStepChange={setCurrentStep}
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

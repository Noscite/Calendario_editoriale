import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Sparkles, Mic, Library, Target, FileText } from 'lucide-react';
import Wizard from '../components/Wizard';
import { brands as brandsApi } from '../services/api';

const STEPS = [
  { id: 1, title: 'Identità',         icon: Sparkles },
  { id: 2, title: 'Voce',             icon: Mic },
  { id: 3, title: 'Asset narrativi',  icon: Library },
  { id: 4, title: 'USP & Pillar',     icon: Target },
  { id: 5, title: 'Knowledge base',   icon: FileText },
];

const DESCRIPTION_MIN = 80;
const NAME_MAX = 180;
const TAGLINE_MAX = 180;

export default function CreateBrandWizard() {
  const navigate = useNavigate();

  const [currentStep, setCurrentStep] = useState(1);
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

  const validateStep1 = () => {
    const name = (formData.name || '').trim();
    const description = (formData.description || '').trim();
    const sector = (formData.sector || '').trim();

    if (!name) return 'Il nome del brand è obbligatorio.';
    if (name.length > NAME_MAX) return `Il nome non può superare ${NAME_MAX} caratteri.`;
    if (!description) return 'La descrizione del brand è obbligatoria.';
    if (description.length < DESCRIPTION_MIN) {
      return `La descrizione deve avere almeno ${DESCRIPTION_MIN} caratteri (attuali: ${description.length}).`;
    }
    if (!sector) return 'Il settore è obbligatorio.';
    return null;
  };

  const handleNext = async () => {
    setValidationError(null);

    if (currentStep === 1) {
      const err = validateStep1();
      if (err) {
        setValidationError(err);
        return;
      }

      setIsSubmitting(true);
      try {
        const res = await brandsApi.create({
          name: formData.name.trim(),
          tagline: (formData.tagline || '').trim() || null,
          description: formData.description.trim(),
          sector: formData.sector.trim(),
        });
        const newId = res?.data?.id;
        if (!newId) {
          throw new Error('Risposta inattesa dal server: id mancante.');
        }
        navigate(`/brand/${newId}/wizard?step=2`, { replace: true });
      } catch (e) {
        const msg =
          e?.response?.data?.message ||
          e?.response?.data?.detail ||
          e?.message ||
          'Errore durante la creazione del brand.';
        setValidationError(msg);
      } finally {
        setIsSubmitting(false);
      }
    }
  };

  const handleBack = () => {
    setValidationError(null);
    if (currentStep > 1) setCurrentStep(currentStep - 1);
  };

  const renderStep = () => {
    if (currentStep !== 1) {
      return (
        <div className="text-sm text-gray-500">
          Salva il primo step per accedere ai successivi.
        </div>
      );
    }

    const descLen = (formData.description || '').trim().length;
    const descValid = descLen >= DESCRIPTION_MIN;

    return (
      <div className="space-y-6">
        <div>
          <h2 className="text-xl font-semibold text-[#2C3E50] mb-1">Identità del brand</h2>
          <p className="text-sm text-gray-500">
            Quattro informazioni base. Più sono precise, migliore sarà l'output dell'AI.
          </p>
        </div>

        <div>
          <label className="block text-sm font-medium text-[#2C3E50] mb-1">
            Nome del brand *
          </label>
          <input
            type="text"
            value={formData.name}
            onChange={(e) => updateField('name', e.target.value.slice(0, NAME_MAX))}
            placeholder="Es. Kalendarium"
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
            placeholder="Una frase che rappresenta il brand"
            maxLength={TAGLINE_MAX}
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
            placeholder="Cosa fa il brand, per chi, con quale promessa di valore."
            className="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent resize-y"
          />
          <p className="text-xs text-gray-500 mt-1">
            Minimo {DESCRIPTION_MIN} caratteri — dettaglia missione, target e differenziatori chiave.
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
  };

  const wizardHeader = (
    <div>
      <h1 className="text-2xl font-bold text-[#2C3E50]">Crea il tuo brand</h1>
      <p className="text-sm text-gray-500 mt-1">
        5 step in ~25 minuti, salvataggio automatico tra uno step e l'altro.
      </p>
    </div>
  );

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

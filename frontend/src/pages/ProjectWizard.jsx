import { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useDataStore } from '../store/dataStore';
import BrandDocuments from '../components/BrandDocuments';
import { 
  ArrowLeft, ArrowRight, Check, Calendar, FileText, File, Link, 
  Target, Sparkles, Loader2, Plus, X, Globe, Linkedin, Instagram,
  Users, Brain, RefreshCw, Clock, AlertCircle, Edit3
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

const STEPS = [
  { id: 1, title: 'Brief', icon: FileText },
  { id: 2, title: 'Piattaforme', icon: Calendar },
  { id: 3, title: 'Contenuti', icon: Target },
  { id: 4, title: 'Riferimenti', icon: Link },
  { id: 5, title: 'Documenti', icon: File },
  { id: 6, title: 'Target', icon: Users },
  { id: 7, title: 'Genera', icon: Sparkles },
];

const platformIcons = {
  linkedin: Linkedin,
  instagram: Instagram,
  facebook: Globe,
  google_business: Globe
};

const platformColors = {
  linkedin: 'bg-blue-600',
  instagram: 'bg-gradient-to-r from-purple-500 to-pink-500',
  facebook: 'bg-blue-500',
  google_business: 'bg-green-600'
};

export default function ProjectWizard() {
  const { brandId } = useParams();
  const navigate = useNavigate();
  const { createProject } = useDataStore();
  const [currentStep, setCurrentStep] = useState(1);
  const [isGenerating, setIsGenerating] = useState(false);
  const [createdProjectId, setCreatedProjectId] = useState(null);
  
  // Buyer Personas state
  const [personasData, setPersonasData] = useState(null);
  const [personasLoading, setPersonasLoading] = useState(false);
  const [personasError, setPersonasError] = useState(null);
  const [showFeedbackInput, setShowFeedbackInput] = useState(false);
  const [personasFeedback, setPersonasFeedback] = useState('');
  
  const [formData, setFormData] = useState({
    name: '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    platforms: ['linkedin'],
    posts_per_week: { linkedin: 3, instagram: 4, facebook: 2, google_business: 2 },
    brief: '',
    target_audience: '',
    content_pillars: [],
    themes: [],
    reference_urls: [],
    competitors: [],
    special_dates: [],
  });
  
  const [newPillar, setNewPillar] = useState('');
  const [newUrl, setNewUrl] = useState('');
  const [newCompetitor, setNewCompetitor] = useState('');

  const token = localStorage.getItem('token');

  const updateForm = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }));
  };

  const togglePlatform = (platform) => {
    const platforms = formData.platforms.includes(platform)
      ? formData.platforms.filter(p => p !== platform)
      : [...formData.platforms, platform];
    updateForm('platforms', platforms);
  };

  const addPillar = () => {
    if (newPillar.trim()) {
      updateForm('content_pillars', [...formData.content_pillars, newPillar.trim()]);
      setNewPillar('');
    }
  };

  const addUrl = () => {
    if (newUrl.trim()) {
      updateForm('reference_urls', [...formData.reference_urls, newUrl.trim()]);
      setNewUrl('');
    }
  };

  const addCompetitor = () => {
    if (newCompetitor.trim()) {
      updateForm('competitors', [...formData.competitors, newCompetitor.trim()]);
      setNewCompetitor('');
    }
  };

  // Crea progetto e genera personas
  const generatePersonas = async () => {
    setPersonasLoading(true);
    setPersonasError(null);
    
    try {
      let projectId = createdProjectId;
      
      // Se non esiste ancora il progetto, crealo
      if (!projectId) {
        const result = await createProject({ ...formData, brand_id: parseInt(brandId) });
        if (!result.success) {
          throw new Error('Errore nella creazione del progetto');
        }
        projectId = result.data.id;
        setCreatedProjectId(projectId);
      }
      
      // Genera le personas
      const res = await fetch(`${API_URL}/generate/personas/${projectId}`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore generazione personas');
      }
      
      const data = await res.json();
      setPersonasData(data.personas);
      
      // Aggiorna posts_per_week con i valori suggeriti dall'AI
      if (data.personas?.recommended_posts_per_week) {
        setFormData(prev => ({
          ...prev,
          posts_per_week: { ...prev.posts_per_week, ...data.personas.recommended_posts_per_week }
        }));
      }
      
    } catch (err) {
      setPersonasError(err.message);
    } finally {
      setPersonasLoading(false);
    }
  };

  // Rigenera personas con feedback
  const regeneratePersonas = async () => {
    if (!personasFeedback.trim() || !createdProjectId) return;
    
    setPersonasLoading(true);
    setPersonasError(null);
    
    try {
      const res = await fetch(`${API_URL}/generate/personas/${createdProjectId}/regenerate`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ feedback: personasFeedback })
      });
      
      if (!res.ok) throw new Error('Errore rigenerazione');
      
      const data = await res.json();
      setPersonasData(data.personas);
      
      // Aggiorna posts_per_week con i valori suggeriti dall'AI
      if (data.personas?.recommended_posts_per_week) {
        setFormData(prev => ({
          ...prev,
          posts_per_week: { ...prev.posts_per_week, ...data.personas.recommended_posts_per_week }
        }));
      }
      setPersonasFeedback('');
      setShowFeedbackInput(false);
      
    } catch (err) {
      setPersonasError(err.message);
    } finally {
      setPersonasLoading(false);
    }
  };

  // Conferma personas e genera calendario
  const handleSubmit = async () => {
    if (!createdProjectId) {
      alert('Prima genera le buyer personas');
      return;
    }
    
    setIsGenerating(true);
    
    try {
      // Conferma le personas
      await fetch(`${API_URL}/generate/personas/${createdProjectId}/confirm`, {
        method: 'PUT',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ personas: personasData })
      });
      
      // Avvia generazione calendario
      const { generation } = await import('../services/api');
      await generation.generateCalendar(createdProjectId);
      
      navigate(`/project/${createdProjectId}`);
      
    } catch (e) {
      console.error('Error:', e);
      // Anche se c'è errore, vai alla pagina progetto (generazione in background)
      navigate(`/project/${createdProjectId}`);
    }
  };

  const canProceed = () => {
    switch (currentStep) {
      case 1: return formData.name && formData.brief;
      case 2: return formData.platforms.length > 0;
      case 3: return true;
      case 4: return true;
      case 5: return true; // Documenti - opzionale
      case 6: return personasData !== null; // Deve aver generato le personas
      default: return true;
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-4xl mx-auto px-4 py-4">
          <button onClick={() => navigate(-1)} className="flex items-center gap-2 text-gray-600 hover:text-[#3DAFA8]">
            <ArrowLeft size={20} /> Torna al brand
          </button>
        </div>
      </header>

      {/* Progress Steps */}
      <div className="bg-white border-b">
        <div className="max-w-4xl mx-auto px-4 py-6">
          <div className="flex justify-between">
            {STEPS.map((step, idx) => (
              <div key={step.id} className="flex items-center">
                <div className={`flex items-center justify-center w-10 h-10 rounded-full ${
                  currentStep >= step.id ? 'bg-[#3DAFA8] text-white' : 'bg-gray-200 text-gray-500'
                }`}>
                  {currentStep > step.id ? <Check size={20} /> : <step.icon size={20} />}
                </div>
                <span className={`ml-2 text-sm hidden sm:block ${
                  currentStep >= step.id ? 'text-[#3DAFA8] font-medium' : 'text-gray-500'
                }`}>
                  {step.title}
                </span>
                {idx < STEPS.length - 1 && (
                  <div className={`w-8 sm:w-16 h-1 mx-2 ${
                    currentStep > step.id ? 'bg-[#3DAFA8]' : 'bg-gray-200'
                  }`} />
                )}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Content */}
      <main className="max-w-4xl mx-auto px-4 py-8">
        <div className="bg-white rounded-xl shadow-sm p-8">
          
          {/* Step 1: Brief */}
          {currentStep === 1 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Descrivi il tuo progetto</h2>
                <p className="text-gray-500">Più dettagli fornisci, migliore sarà il risultato</p>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Nome del progetto *</label>
                <input
                  type="text"
                  value={formData.name}
                  onChange={(e) => updateForm('name', e.target.value)}
                  placeholder="es. Piano Editoriale Q1 2025"
                  className="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Brief / Obiettivi *</label>
                <textarea
                  value={formData.brief}
                  onChange={(e) => updateForm('brief', e.target.value)}
                  placeholder="Descrivi cosa vuoi ottenere con questo piano editoriale. Quali sono i messaggi chiave? Quali prodotti/servizi vuoi promuovere? Ci sono campagne specifiche o lanci previsti?"
                  rows={6}
                  className="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Target Audience (opzionale)</label>
                <textarea
                  value={formData.target_audience}
                  onChange={(e) => updateForm('target_audience', e.target.value)}
                  placeholder="Chi è il tuo pubblico? L'AI analizzerà automaticamente il tuo brand per generare buyer personas dettagliate."
                  rows={3}
                  className="w-full px-4 py-3 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                />
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Data inizio</label>
                  <input
                    type="date"
                    value={formData.start_date}
                    onChange={(e) => updateForm('start_date', e.target.value)}
                    className="w-full px-4 py-3 border rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">Data fine</label>
                  <input
                    type="date"
                    value={formData.end_date}
                    onChange={(e) => updateForm('end_date', e.target.value)}
                    className="w-full px-4 py-3 border rounded-lg"
                  />
                </div>
              </div>
            </div>
          )}

          {/* Step 2: Piattaforme */}
          {currentStep === 2 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Scegli le piattaforme</h2>
                <p className="text-gray-500">Seleziona dove vuoi pubblicare e quanti post a settimana</p>
              </div>
              
              <div className="grid grid-cols-2 gap-4">
                {[
                  { id: 'linkedin', name: 'LinkedIn', icon: Linkedin, color: 'bg-blue-600' },
                  { id: 'instagram', name: 'Instagram', icon: Instagram, color: 'bg-gradient-to-r from-purple-500 to-pink-500' },
                  { id: 'facebook', name: 'Facebook', icon: Globe, color: 'bg-blue-500' },
                  { id: 'google_business', name: 'Google Business', icon: Globe, color: 'bg-green-600' },
                ].map((platform) => (
                  <div
                    key={platform.id}
                    onClick={() => togglePlatform(platform.id)}
                    className={`p-4 border-2 rounded-xl cursor-pointer transition-all ${
                      formData.platforms.includes(platform.id)
                        ? 'border-[#3DAFA8] bg-[#3DAFA8]/5'
                        : 'border-gray-200 hover:border-gray-300'
                    }`}
                  >
                    <div className="flex items-center gap-3 mb-3">
                      <div className={`${platform.color} p-2 rounded-lg text-white`}>
                        <platform.icon size={20} />
                      </div>
                      <span className="font-medium">{platform.name}</span>
                      {formData.platforms.includes(platform.id) && (
                        <Check className="ml-auto text-[#3DAFA8]" size={20} />
                      )}
                    </div>
                    
                    {formData.platforms.includes(platform.id) && (
                      <div>
                        <label className="text-xs text-gray-500">Post/settimana</label>
                        <input
                          type="number"
                          min="1"
                          max="14"
                          value={formData.posts_per_week[platform.id] || 3}
                          onChange={(e) => updateForm('posts_per_week', {
                            ...formData.posts_per_week,
                            [platform.id]: parseInt(e.target.value)
                          })}
                          onClick={(e) => e.stopPropagation()}
                          className="w-full mt-1 px-3 py-2 border rounded-lg text-sm"
                        />
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Step 3: Contenuti/Pillar */}
          {currentStep === 3 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Pillar e Temi</h2>
                <p className="text-gray-500">Definisci le macro-categorie di contenuto</p>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Content Pillars</label>
                <div className="flex gap-2 mb-3">
                  <input
                    type="text"
                    value={newPillar}
                    onChange={(e) => setNewPillar(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && addPillar()}
                    placeholder="es. Umanesimo Digitale, AI per PMI..."
                    className="flex-1 px-4 py-2 border rounded-lg"
                  />
                  <button onClick={addPillar} className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg">
                    <Plus size={20} />
                  </button>
                </div>
                <div className="flex flex-wrap gap-2">
                  {formData.content_pillars.map((pillar, idx) => (
                    <span key={idx} className="flex items-center gap-1 bg-[#3DAFA8]/10 text-[#3DAFA8] px-3 py-1 rounded-full">
                      {pillar}
                      <X
                        size={16}
                        className="cursor-pointer hover:text-red-500"
                        onClick={() => updateForm('content_pillars', formData.content_pillars.filter((_, i) => i !== idx))}
                      />
                    </span>
                  ))}
                </div>
                {formData.content_pillars.length === 0 && (
                  <p className="text-sm text-gray-400 mt-2">Suggerimenti: Thought Leadership, Behind the Scenes, Educational, Case Study, Engagement</p>
                )}
              </div>
              
              <div className="bg-gray-50 p-4 rounded-lg">
                <h4 className="font-medium mb-2">Suggerimenti rapidi</h4>
                <div className="flex flex-wrap gap-2">
                  {['Thought Leadership', 'Educational', 'Case Study', 'Behind the Scenes', 'Tips & Tricks', 'News & Trend'].map(sug => (
                    <button
                      key={sug}
                      onClick={() => !formData.content_pillars.includes(sug) && updateForm('content_pillars', [...formData.content_pillars, sug])}
                      className="text-sm px-3 py-1 border border-gray-300 rounded-full hover:border-[#3DAFA8] hover:text-[#3DAFA8]"
                    >
                      + {sug}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Step 4: Riferimenti */}
          {currentStep === 4 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Riferimenti e Competitor</h2>
                <p className="text-gray-500">Aggiungi URL da cui trarre ispirazione</p>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">URL di riferimento</label>
                <p className="text-xs text-gray-500 mb-2">Sito web, pagine specifiche, articoli di riferimento</p>
                <div className="flex gap-2 mb-3">
                  <input
                    type="url"
                    value={newUrl}
                    onChange={(e) => setNewUrl(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && addUrl()}
                    placeholder="https://..."
                    className="flex-1 px-4 py-2 border rounded-lg"
                  />
                  <button onClick={addUrl} className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg">
                    <Plus size={20} />
                  </button>
                </div>
                <div className="space-y-2">
                  {formData.reference_urls.map((url, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg">
                      <Link size={16} className="text-gray-400" />
                      <span className="flex-1 text-sm truncate">{url}</span>
                      <X
                        size={16}
                        className="cursor-pointer text-gray-400 hover:text-red-500"
                        onClick={() => updateForm('reference_urls', formData.reference_urls.filter((_, i) => i !== idx))}
                      />
                    </div>
                  ))}
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Competitor da analizzare</label>
                <p className="text-xs text-gray-500 mb-2">Profili social o siti di competitor</p>
                <div className="flex gap-2 mb-3">
                  <input
                    type="url"
                    value={newCompetitor}
                    onChange={(e) => setNewCompetitor(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && addCompetitor()}
                    placeholder="https://linkedin.com/company/..."
                    className="flex-1 px-4 py-2 border rounded-lg"
                  />
                  <button onClick={addCompetitor} className="px-4 py-2 bg-[#E89548] text-white rounded-lg">
                    <Plus size={20} />
                  </button>
                </div>
                <div className="space-y-2">
                  {formData.competitors.map((url, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-orange-50 px-3 py-2 rounded-lg">
                      <Globe size={16} className="text-[#E89548]" />
                      <span className="flex-1 text-sm truncate">{url}</span>
                      <X
                        size={16}
                        className="cursor-pointer text-gray-400 hover:text-red-500"
                        onClick={() => updateForm('competitors', formData.competitors.filter((_, i) => i !== idx))}
                      />
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* Step 5: Documenti */}
          {currentStep === 5 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Documenti Aziendali</h2>
                <p className="text-gray-500">Carica documenti per arricchire la generazione dei contenuti con informazioni specifiche del tuo brand</p>
              </div>
              
              <BrandDocuments brandId={brandId} />
              
              <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p className="text-sm text-blue-700">
                  <strong>💡 Suggerimento:</strong> Carica documenti come presentazioni aziendali, guide di stile, 
                  cataloghi prodotti o qualsiasi materiale che descriva il tuo brand. L'AI li userà per generare 
                  contenuti più accurati e in linea con la tua comunicazione.
                </p>
              </div>
            </div>
          )}
          {/* Step 6: Buyer Personas */}
          {currentStep === 6 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Analisi Target</h2>
                <p className="text-gray-500">L'AI analizzerà il tuo brand per generare buyer personas e ottimizzare gli orari di pubblicazione</p>
              </div>
              
              {/* Se non ci sono personas, mostra bottone per generarle */}
              {!personasData && (
                <div className="text-center py-8">
                  <Users className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                  <h3 className="text-xl font-semibold mb-2">Buyer Personas</h3>
                  <p className="text-gray-500 mb-6 max-w-md mx-auto">
                    Genera automaticamente i profili del tuo target per ottimizzare 
                    contenuti e orari di pubblicazione.
                  </p>
                  <button
                    onClick={generatePersonas}
                    disabled={personasLoading}
                    className="px-6 py-3 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] 
                               disabled:opacity-50 flex items-center gap-2 mx-auto"
                  >
                    {personasLoading ? (
                      <>
                        <Loader2 className="w-5 h-5 animate-spin" />
                        Analisi in corso...
                      </>
                    ) : (
                      <>
                        <Brain className="w-5 h-5" />
                        Genera Buyer Personas
                      </>
                    )}
                  </button>
                  {personasError && (
                    <div className="mt-4 p-3 bg-red-50 text-red-700 rounded-lg flex items-center gap-2 justify-center">
                      <AlertCircle className="w-4 h-4" />
                      {personasError}
                    </div>
                  )}
                </div>
              )}
              
              {/* Se ci sono personas, mostrale */}
              {personasData && (
                <div className="space-y-6">
                  {/* Header con azioni */}
                  <div className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-[#3DAFA8]/20 rounded-lg flex items-center justify-center">
                        <Users className="w-5 h-5 text-[#3DAFA8]" />
                      </div>
                      <div>
                        <h3 className="font-semibold">Buyer Personas Identificate</h3>
                        <p className="text-sm text-gray-500">
                          {personasData.personas?.length || 0} profili • Scheduling ottimizzato
                        </p>
                      </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => setShowFeedbackInput(!showFeedbackInput)}
                        className="px-3 py-2 text-gray-600 hover:bg-white rounded-lg 
                                   flex items-center gap-2 text-sm"
                      >
                        <Edit3 className="w-4 h-4" />
                        Modifica
                      </button>
                      <button
                        onClick={generatePersonas}
                        disabled={personasLoading}
                        className="px-3 py-2 text-gray-600 hover:bg-white rounded-lg 
                                   flex items-center gap-2 text-sm"
                      >
                        <RefreshCw className={`w-4 h-4 ${personasLoading ? 'animate-spin' : ''}`} />
                        Rigenera
                      </button>
                    </div>
                  </div>

                  {/* Feedback input */}
                  {showFeedbackInput && (
                    <div className="p-4 bg-amber-50 rounded-lg border border-amber-200">
                      <label className="block text-sm font-medium text-amber-800 mb-2">
                        Come vuoi modificare le personas?
                      </label>
                      <textarea
                        value={personasFeedback}
                        onChange={(e) => setPersonasFeedback(e.target.value)}
                        placeholder="Es: Target più giovane, aggiungi professionisti tech, focus su piccole imprese..."
                        className="w-full p-3 border rounded-lg text-sm"
                        rows={2}
                      />
                      <div className="flex justify-end gap-2 mt-3">
                        <button
                          onClick={() => { setShowFeedbackInput(false); setPersonasFeedback(''); }}
                          className="px-3 py-1.5 text-gray-600 hover:bg-amber-100 rounded text-sm"
                        >
                          Annulla
                        </button>
                        <button
                          onClick={regeneratePersonas}
                          disabled={!personasFeedback.trim() || personasLoading}
                          className="px-4 py-1.5 bg-amber-600 text-white rounded text-sm 
                                     hover:bg-amber-700 disabled:opacity-50"
                        >
                          Rigenera con modifiche
                        </button>
                      </div>
                    </div>
                  )}

                  {personasError && (
                    <div className="p-3 bg-red-50 text-red-700 rounded-lg flex items-center gap-2">
                      <AlertCircle className="w-4 h-4" />
                      {personasError}
                    </div>
                  )}

                  {/* Personas Cards */}
                  <div className="grid md:grid-cols-2 gap-4">
                    {personasData.personas?.map((persona, idx) => (
                      <PersonaCard key={idx} persona={persona} />
                    ))}
                  </div>

                  {/* Scheduling Strategy */}
                  {personasData.scheduling_strategy && (
                    <div className="p-4 bg-gray-50 rounded-lg">
                      <h4 className="font-semibold mb-4 flex items-center gap-2">
                        <Clock className="w-5 h-5 text-[#3DAFA8]" />
                        Scheduling Ottimizzato
                      </h4>
                      <div className="grid md:grid-cols-3 gap-4">
                        {Object.entries(personasData.scheduling_strategy).map(([platform, strategy]) => (
                          <PlatformSchedule key={platform} platform={platform} strategy={strategy} />
                        ))}
                      </div>
                    </div>
                  )}

                  {/* Analysis Notes */}
                  {personasData.analysis_notes && (
                    <div className="p-4 bg-blue-50 rounded-lg text-sm text-blue-800">
                      <strong>Note analisi:</strong> {personasData.analysis_notes}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Step 7: Review & Generate */}
          {currentStep === 7 && (
            <div className="space-y-6">
              <div>
                <h2 className="text-2xl font-bold text-[#2C3E50] mb-2">Riepilogo e Generazione</h2>
                <p className="text-gray-500">Verifica i dati e avvia la generazione AI</p>
              </div>
              
              <div className="grid grid-cols-2 gap-6">
                <div className="space-y-4">
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Progetto</h4>
                    <p className="font-semibold">{formData.name}</p>
                  </div>
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Periodo</h4>
                    <p>{formData.start_date} → {formData.end_date}</p>
                  </div>
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Piattaforme</h4>
                    <div className="flex flex-wrap gap-2 mt-1">
                      {formData.platforms.map(p => (
                        <span key={p} className="bg-[#3DAFA8] text-white text-xs px-2 py-1 rounded">
                          {p} ({formData.posts_per_week[p]}/sett)
                        </span>
                      ))}
                    </div>
                  </div>
                </div>
                
                <div className="space-y-4">
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Buyer Personas</h4>
                    <div className="flex flex-wrap gap-1 mt-1">
                      {personasData?.personas?.map((p, i) => (
                        <span key={i} className="bg-purple-100 text-purple-700 text-xs px-2 py-1 rounded">{p.name}</span>
                      ))}
                    </div>
                  </div>
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Pillar</h4>
                    <div className="flex flex-wrap gap-1 mt-1">
                      {formData.content_pillars.map((p, i) => (
                        <span key={i} className="bg-[#E89548]/20 text-[#E89548] text-xs px-2 py-1 rounded">{p}</span>
                      ))}
                      {formData.content_pillars.length === 0 && <span className="text-gray-400 text-sm">Auto-generati</span>}
                    </div>
                  </div>
                  <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="font-medium text-sm text-gray-500 mb-1">Riferimenti</h4>
                    <p className="text-sm">{formData.reference_urls.length} URL, {formData.competitors.length} competitor</p>
                  </div>
                </div>
              </div>
              
              <div className="bg-[#3DAFA8]/10 p-6 rounded-xl text-center">
                <Sparkles className="mx-auto text-[#3DAFA8] mb-3" size={40} />
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-2">Pronto per generare!</h3>
                <p className="text-gray-600 mb-4">
                  L'AI creerà circa {Math.ceil((new Date(formData.end_date) - new Date(formData.start_date)) / (7 * 24 * 60 * 60 * 1000)) * formData.platforms.reduce((sum, p) => sum + (formData.posts_per_week[p] || 3), 0)} post
                  {personasData && <span className="block text-sm mt-1">con orari ottimizzati per le tue buyer personas</span>}
                </p>
              </div>
            </div>
          )}

          {/* Navigation Buttons */}
          <div className="flex justify-between mt-8 pt-6 border-t">
            <button
              onClick={() => setCurrentStep(prev => prev - 1)}
              disabled={currentStep === 1}
              className="flex items-center gap-2 px-6 py-3 border rounded-lg disabled:opacity-50"
            >
              <ArrowLeft size={20} /> Indietro
            </button>
            
            {currentStep < 7 ? (
              <button
                onClick={() => setCurrentStep(prev => prev + 1)}
                disabled={!canProceed()}
                className="flex items-center gap-2 px-6 py-3 bg-[#3DAFA8] text-white rounded-lg disabled:opacity-50 hover:bg-[#2C3E50]"
              >
                Avanti <ArrowRight size={20} />
              </button>
            ) : (
              <button
                onClick={handleSubmit}
                disabled={isGenerating}
                className="flex items-center gap-2 px-8 py-3 bg-[#E89548] text-white rounded-lg disabled:opacity-50 hover:bg-[#d4823c]"
              >
                {isGenerating ? (
                  <>
                    <Loader2 className="animate-spin" size={20} /> Generazione in corso...
                  </>
                ) : (
                  <>
                    <Sparkles size={20} /> Genera Calendario
                  </>
                )}
              </button>
            )}
          </div>
        </div>
      </main>
    </div>
  );
}


// Componente Persona Card
function PersonaCard({ persona }) {
  const { name, demographics, digital_behavior, pain_points, interests, weight } = persona;
  
  return (
    <div className="bg-white border rounded-xl p-5 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] 
                          rounded-full flex items-center justify-center text-white font-bold text-lg">
            {name?.charAt(0) || '?'}
          </div>
          <div>
            <h4 className="font-semibold">{name}</h4>
            <p className="text-sm text-gray-500">{demographics?.role}</p>
          </div>
        </div>
        {weight && (
          <span className="px-2 py-1 bg-[#3DAFA8]/10 text-[#3DAFA8] rounded text-xs font-medium">
            {Math.round(weight * 100)}%
          </span>
        )}
      </div>

      {/* Demographics */}
      <div className="mb-3 text-sm text-gray-600">
        <span className="inline-flex items-center gap-1 mr-3">
          📍 {demographics?.location}
        </span>
        <span className="inline-flex items-center gap-1">
          🎂 {demographics?.age_range}
        </span>
      </div>

      {/* Pain Points */}
      {pain_points && pain_points.length > 0 && (
        <div className="mb-2">
          <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Pain Points</p>
          <p className="text-sm text-gray-600">{pain_points.slice(0, 3).join(' • ')}</p>
        </div>
      )}

      {/* Interests */}
      {interests && interests.length > 0 && (
        <div>
          <p className="text-xs text-gray-400 uppercase tracking-wide mb-1">Interessi</p>
          <div className="flex flex-wrap gap-1">
            {interests.slice(0, 4).map((interest, i) => (
              <span key={i} className="px-2 py-0.5 bg-blue-50 text-blue-700 rounded text-xs">
                {interest}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}


// Componente Platform Schedule
function PlatformSchedule({ platform, strategy }) {
  const Icon = platformIcons[platform] || Globe;
  const colorClass = platformColors[platform] || 'bg-gray-500';
  
  const days = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
  
  return (
    <div className="p-3 bg-white rounded-lg border">
      <div className="flex items-center gap-2 mb-2">
        <div className={`w-6 h-6 ${colorClass} rounded flex items-center justify-center`}>
          <Icon className="w-3 h-3 text-white" />
        </div>
        <span className="font-medium text-sm capitalize">{platform}</span>
      </div>
      
      {strategy.optimal_slots?.slice(0, 3).map((slot, idx) => (
        <div key={idx} className="flex items-center justify-between text-xs py-1">
          <span className="text-gray-600">{days[slot.day]}</span>
          <span className="font-mono text-gray-800">{slot.time}</span>
        </div>
      ))}
    </div>
  );
}

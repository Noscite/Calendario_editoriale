import React, { useState } from 'react';
import { 
  Users, RefreshCw, Check, Edit3, Clock, 
  Linkedin, Instagram, Facebook, Mail,
  Target, Brain, TrendingUp, AlertCircle
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

const platformIcons = {
  linkedin: Linkedin,
  instagram: Instagram,
  facebook: Facebook,
  newsletter: Mail
};

const platformColors = {
  linkedin: 'bg-blue-600',
  instagram: 'bg-gradient-to-r from-purple-500 to-pink-500',
  facebook: 'bg-blue-500',
  newsletter: 'bg-amber-500'
};

export default function BuyerPersonasStep({ 
  projectId, 
  personas, 
  onPersonasChange,
  onConfirm,
  loading 
}) {
  const [regenerating, setRegenerating] = useState(false);
  const [feedback, setFeedback] = useState('');
  const [showFeedbackInput, setShowFeedbackInput] = useState(false);
  const [error, setError] = useState(null);

  const token = localStorage.getItem('token');

  const generatePersonas = async () => {
    setRegenerating(true);
    setError(null);
    try {
      const res = await fetch(`${API_URL}/generate/personas/${projectId}`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      
      if (!res.ok) throw new Error('Errore generazione personas');
      
      const data = await res.json();
      onPersonasChange(data.personas);
    } catch (err) {
      setError(err.message);
    } finally {
      setRegenerating(false);
    }
  };

  const regenerateWithFeedback = async () => {
    if (!feedback.trim()) return;
    
    setRegenerating(true);
    setError(null);
    try {
      const res = await fetch(`${API_URL}/generate/personas/${projectId}/regenerate`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ feedback })
      });
      
      if (!res.ok) throw new Error('Errore rigenerazione');
      
      const data = await res.json();
      onPersonasChange(data.personas);
      setFeedback('');
      setShowFeedbackInput(false);
    } catch (err) {
      setError(err.message);
    } finally {
      setRegenerating(false);
    }
  };

  const confirmPersonas = async () => {
    try {
      const res = await fetch(`${API_URL}/generate/personas/${projectId}/confirm`, {
        method: 'PUT',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ personas })
      });
      
      if (!res.ok) throw new Error('Errore conferma');
      
      onConfirm();
    } catch (err) {
      setError(err.message);
    }
  };

  // Se non ci sono personas, mostra bottone per generarle
  if (!personas || !personas.personas) {
    return (
      <div className="bg-white rounded-xl shadow-sm border p-8 text-center">
        <Users className="w-16 h-16 text-gray-300 mx-auto mb-4" />
        <h3 className="text-xl font-semibold mb-2">Buyer Personas</h3>
        <p className="text-gray-500 mb-6">
          Genera automaticamente i profili del tuo target per ottimizzare 
          contenuti e orari di pubblicazione.
        </p>
        <button
          onClick={generatePersonas}
          disabled={regenerating}
          className="px-6 py-3 bg-teal-600 text-white rounded-lg hover:bg-teal-700 
                     disabled:opacity-50 flex items-center gap-2 mx-auto"
        >
          {regenerating ? (
            <>
              <RefreshCw className="w-5 h-5 animate-spin" />
              Analisi in corso...
            </>
          ) : (
            <>
              <Brain className="w-5 h-5" />
              Genera Buyer Personas
            </>
          )}
        </button>
        {error && (
          <p className="mt-4 text-red-500 text-sm">{error}</p>
        )}
      </div>
    );
  }

  const { personas: personasList, scheduling_strategy, analysis_notes } = personas;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white rounded-xl shadow-sm border p-6">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-teal-100 rounded-lg flex items-center justify-center">
              <Users className="w-5 h-5 text-teal-600" />
            </div>
            <div>
              <h3 className="font-semibold">Buyer Personas Identificate</h3>
              <p className="text-sm text-gray-500">
                {personasList?.length || 0} profili • Scheduling ottimizzato
              </p>
            </div>
          </div>
          
          <div className="flex items-center gap-2">
            <button
              onClick={() => setShowFeedbackInput(!showFeedbackInput)}
              className="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg 
                         flex items-center gap-2 text-sm"
            >
              <Edit3 className="w-4 h-4" />
              Modifica
            </button>
            <button
              onClick={generatePersonas}
              disabled={regenerating}
              className="px-3 py-2 text-gray-600 hover:bg-gray-100 rounded-lg 
                         flex items-center gap-2 text-sm"
            >
              <RefreshCw className={`w-4 h-4 ${regenerating ? 'animate-spin' : ''}`} />
              Rigenera
            </button>
          </div>
        </div>

        {/* Feedback input */}
        {showFeedbackInput && (
          <div className="mt-4 p-4 bg-amber-50 rounded-lg border border-amber-200">
            <label className="block text-sm font-medium text-amber-800 mb-2">
              Come vuoi modificare le personas?
            </label>
            <textarea
              value={feedback}
              onChange={(e) => setFeedback(e.target.value)}
              placeholder="Es: Target più giovane, aggiungi professionisti tech, focus su piccole imprese..."
              className="w-full p-3 border rounded-lg text-sm"
              rows={2}
            />
            <div className="flex justify-end gap-2 mt-3">
              <button
                onClick={() => { setShowFeedbackInput(false); setFeedback(''); }}
                className="px-3 py-1.5 text-gray-600 hover:bg-gray-100 rounded text-sm"
              >
                Annulla
              </button>
              <button
                onClick={regenerateWithFeedback}
                disabled={!feedback.trim() || regenerating}
                className="px-4 py-1.5 bg-amber-600 text-white rounded text-sm 
                           hover:bg-amber-700 disabled:opacity-50"
              >
                Rigenera con modifiche
              </button>
            </div>
          </div>
        )}

        {error && (
          <div className="mt-4 p-3 bg-red-50 text-red-700 rounded-lg flex items-center gap-2">
            <AlertCircle className="w-4 h-4" />
            {error}
          </div>
        )}
      </div>

      {/* Personas Cards */}
      <div className="grid md:grid-cols-2 gap-4">
        {personasList?.map((persona, idx) => (
          <PersonaCard key={idx} persona={persona} />
        ))}
      </div>

      {/* Scheduling Strategy */}
      {scheduling_strategy && (
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <h4 className="font-semibold mb-4 flex items-center gap-2">
            <Clock className="w-5 h-5 text-teal-600" />
            Scheduling Ottimizzato
          </h4>
          <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            {Object.entries(scheduling_strategy).map(([platform, strategy]) => (
              <PlatformSchedule key={platform} platform={platform} strategy={strategy} />
            ))}
          </div>
        </div>
      )}

      {/* Analysis Notes */}
      {analysis_notes && (
        <div className="bg-gray-50 rounded-lg p-4 text-sm text-gray-600">
          <strong>Note analisi:</strong> {analysis_notes}
        </div>
      )}

      {/* Confirm Button */}
      <div className="flex justify-end">
        <button
          onClick={confirmPersonas}
          disabled={loading}
          className="px-6 py-3 bg-teal-600 text-white rounded-lg hover:bg-teal-700 
                     flex items-center gap-2 font-medium"
        >
          <Check className="w-5 h-5" />
          Conferma e Genera Calendario
        </button>
      </div>
    </div>
  );
}


function PersonaCard({ persona }) {
  const { name, demographics, digital_behavior, pain_points, interests, buying_triggers, weight } = persona;
  
  return (
    <div className="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-shadow">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-gradient-to-br from-teal-400 to-teal-600 
                          rounded-full flex items-center justify-center text-white font-bold text-lg">
            {name?.charAt(0) || '?'}
          </div>
          <div>
            <h4 className="font-semibold">{name}</h4>
            <p className="text-sm text-gray-500">{demographics?.role}</p>
          </div>
        </div>
        {weight && (
          <span className="px-2 py-1 bg-teal-100 text-teal-700 rounded text-xs font-medium">
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

      {/* Digital Behavior Pills */}
      <div className="flex flex-wrap gap-1 mb-3">
        {Object.entries(digital_behavior || {}).slice(0, 3).map(([platform, data]) => {
          const Icon = platformIcons[platform] || Target;
          return (
            <span 
              key={platform}
              className="inline-flex items-center gap-1 px-2 py-1 bg-gray-100 
                         rounded text-xs text-gray-700"
            >
              <Icon className="w-3 h-3" />
              {data.best_times?.[0] || ''}
            </span>
          );
        })}
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


function PlatformSchedule({ platform, strategy }) {
  const Icon = platformIcons[platform] || Target;
  const colorClass = platformColors[platform] || 'bg-gray-500';
  
  const days = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
  
  return (
    <div className="p-4 bg-gray-50 rounded-lg">
      <div className="flex items-center gap-2 mb-3">
        <div className={`w-8 h-8 ${colorClass} rounded-lg flex items-center justify-center`}>
          <Icon className="w-4 h-4 text-white" />
        </div>
        <span className="font-medium capitalize">{platform}</span>
      </div>
      
      {strategy.optimal_slots?.slice(0, 3).map((slot, idx) => (
        <div key={idx} className="flex items-center justify-between text-sm py-1">
          <span className="text-gray-600">{days[slot.day]}</span>
          <span className="font-mono text-gray-800">{slot.time}</span>
        </div>
      ))}
      
      {strategy.avoid && strategy.avoid.length > 0 && (
        <p className="mt-2 text-xs text-gray-400">
          Evitare: {strategy.avoid.slice(0, 2).join(', ')}
        </p>
      )}
    </div>
  );
}

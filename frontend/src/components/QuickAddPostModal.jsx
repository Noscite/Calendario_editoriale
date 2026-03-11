import { useState } from 'react';
import { X, Loader2, Sparkles, PenLine, Linkedin, Instagram, Facebook, Globe, MapPin } from 'lucide-react';
import { posts as postsApi } from '../services/api';

const PLATFORMS = [
  { id: 'linkedin', name: 'LinkedIn', icon: Linkedin, color: 'bg-[#0077b5]' },
  { id: 'instagram', name: 'Instagram', icon: Instagram, color: 'bg-gradient-to-r from-[#f09433] via-[#dc2743] to-[#bc1888]' },
  { id: 'facebook', name: 'Facebook', icon: Facebook, color: 'bg-[#1877f2]' },
  { id: 'google_business', name: 'Google Business', icon: MapPin, color: 'bg-[#34a853]' },
];

export default function QuickAddPostModal({ 
  isOpen, 
  onClose, 
  selectedDate, 
  projectId, 
  projectPlatforms = [],
  projectPillars = [],
  onPostCreated 
}) {
  const [mode, setMode] = useState('manual'); // 'manual' or 'ai'
  const [isLoading, setIsLoading] = useState(false);
  const [message, setMessage] = useState(null);
  
  const [formData, setFormData] = useState({
    platform: projectPlatforms[0] || 'linkedin',
    scheduled_time: '09:00',
    content: '',
    hashtags: '',
    pillar: projectPillars[0] || '',
    cta: '',
    visual_suggestion: '',
    brief: '' // Per AI mode
  });

  if (!isOpen) return null;

  const dateStr = selectedDate ? selectedDate.toISOString().split('T')[0] : '';
  const dateDisplay = selectedDate ? selectedDate.toLocaleDateString('it-IT', { 
    weekday: 'long', 
    day: 'numeric', 
    month: 'long' 
  }) : '';

  const availablePlatforms = PLATFORMS.filter(p => projectPlatforms.includes(p.id));

  const handleSubmit = async () => {
    setIsLoading(true);
    setMessage(null);

    try {
      if (mode === 'manual') {
        // Creazione manuale
        const res = await postsApi.createManual({
          project_id: parseInt(projectId),
          platform: formData.platform,
          scheduled_date: dateStr,
          scheduled_time: formData.scheduled_time,
          content: formData.content,
          hashtags: formData.hashtags,
          pillar: formData.pillar,
          cta: formData.cta,
          visual_suggestion: formData.visual_suggestion
        });
        
        onPostCreated(res.data);
        setMessage({ type: 'success', text: 'Post creato con successo!' });
        
      } else {
        // Generazione AI singolo post
        const res = await postsApi.generateAI({
          project_id: parseInt(projectId),
          platforms: [formData.platform],
          start_date: dateStr,
          end_date: dateStr,
          num_posts: 1,
          ai_decide_num_posts: false,
          ai_decide_platforms: false,
          brief: formData.brief || `Post per ${dateDisplay}`,
          pillar: formData.pillar
        });
        
        const newPosts = res.data?.posts || res.data || [];
        if (Array.isArray(newPosts)) {
          newPosts.forEach(post => onPostCreated(post));
        } else if (newPosts.id) {
          onPostCreated(newPosts);
        }
        
        setMessage({ type: 'success', text: 'Post AI generato!' });
      }
      
      setTimeout(() => {
        onClose();
        resetForm();
      }, 1000);
      
    } catch (error) {
      console.error('Error creating post:', error);
      setMessage({ type: 'error', text: error.response?.data?.detail || 'Errore nella creazione' });
    } finally {
      setIsLoading(false);
    }
  };

  const resetForm = () => {
    setFormData({
      platform: projectPlatforms[0] || 'linkedin',
      scheduled_time: '09:00',
      content: '',
      hashtags: '',
      pillar: projectPillars[0] || '',
      cta: '',
      visual_suggestion: '',
      brief: ''
    });
    setMessage(null);
    setMode('manual');
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl">
        {/* Header */}
        <div className="p-5 border-b bg-gradient-to-r from-[#3DAFA8] to-[#2C3E50] text-white rounded-t-xl">
          <div className="flex items-center justify-between">
            <div>
              <h2 className="text-lg font-bold">Nuovo Post</h2>
              <p className="text-sm text-white/80 capitalize">{dateDisplay}</p>
            </div>
            <button onClick={handleClose} className="p-1.5 hover:bg-white/20 rounded-lg transition">
              <X size={22} />
            </button>
          </div>
          
          {/* Mode Tabs */}
          <div className="flex gap-2 mt-4">
            <button
              onClick={() => setMode('manual')}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                mode === 'manual' ? 'bg-white text-[#3DAFA8]' : 'bg-white/20 hover:bg-white/30'
              }`}
            >
              <PenLine size={16} /> Manuale
            </button>
            <button
              onClick={() => setMode('ai')}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                mode === 'ai' ? 'bg-white text-[#3DAFA8]' : 'bg-white/20 hover:bg-white/30'
              }`}
            >
              <Sparkles size={16} /> Genera con AI
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="p-5 space-y-4">
          {message && (
            <div className={`p-3 rounded-lg text-sm ${
              message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
            }`}>
              {message.text}
            </div>
          )}

          {/* Platform Selection */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Piattaforma</label>
            <div className="flex flex-wrap gap-2">
              {availablePlatforms.map(platform => {
                const Icon = platform.icon;
                const isSelected = formData.platform === platform.id;
                return (
                  <button
                    key={platform.id}
                    type="button"
                    onClick={() => setFormData(prev => ({ ...prev, platform: platform.id }))}
                    className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isSelected 
                        ? `${platform.color} text-white shadow-md` 
                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    }`}
                  >
                    <Icon size={16} /> {platform.name}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Time */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Orario</label>
              <input
                type="time"
                value={formData.scheduled_time}
                onChange={(e) => setFormData(prev => ({ ...prev, scheduled_time: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Pillar</label>
              <select
                value={formData.pillar}
                onChange={(e) => setFormData(prev => ({ ...prev, pillar: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              >
                <option value="">Seleziona...</option>
                {projectPillars.map(pillar => (
                  <option key={pillar} value={pillar}>{pillar}</option>
                ))}
              </select>
            </div>
          </div>

          {mode === 'manual' ? (
            <>
              {/* Manual: Content */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Contenuto</label>
                <textarea
                  value={formData.content}
                  onChange={(e) => setFormData(prev => ({ ...prev, content: e.target.value }))}
                  placeholder="Scrivi il contenuto del post..."
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent h-28 resize-none"
                />
              </div>

              {/* Hashtags */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Hashtag</label>
                <input
                  type="text"
                  value={formData.hashtags}
                  onChange={(e) => setFormData(prev => ({ ...prev, hashtags: e.target.value }))}
                  placeholder="#marketing, #socialmedia"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                />
              </div>

              {/* CTA */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Call to Action</label>
                <input
                  type="text"
                  value={formData.cta}
                  onChange={(e) => setFormData(prev => ({ ...prev, cta: e.target.value }))}
                  placeholder="Scopri di più sul nostro sito"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                />
              </div>
            </>
          ) : (
            <>
              {/* AI: Brief */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Brief per l'AI <span className="text-gray-400">(opzionale)</span>
                </label>
                <textarea
                  value={formData.brief}
                  onChange={(e) => setFormData(prev => ({ ...prev, brief: e.target.value }))}
                  placeholder="Descrivi cosa vuoi comunicare in questo post... L'AI genererà contenuto, hashtag, CTA e suggerimento visual."
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent h-28 resize-none"
                />
              </div>
            </>
          )}

          {/* Visual Suggestion (both modes) */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Suggerimento Visual <span className="text-gray-400">(per generazione immagine)</span>
            </label>
            <input
              type="text"
              value={formData.visual_suggestion}
              onChange={(e) => setFormData(prev => ({ ...prev, visual_suggestion: e.target.value }))}
              placeholder="Es: Grafica con icone tech su sfondo blu"
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
          </div>
        </div>

        {/* Footer */}
        <div className="p-5 border-t bg-gray-50 rounded-b-xl flex justify-end gap-3">
          <button
            onClick={handleClose}
            className="px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-lg transition"
          >
            Annulla
          </button>
          <button
            onClick={handleSubmit}
            disabled={isLoading || (mode === 'manual' && !formData.content.trim())}
            className="flex items-center gap-2 px-5 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 disabled:cursor-not-allowed transition"
          >
            {isLoading ? (
              <><Loader2 className="animate-spin" size={18} /> Creazione...</>
            ) : mode === 'ai' ? (
              <><Sparkles size={18} /> Genera Post</>
            ) : (
              <><PenLine size={18} /> Crea Post</>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

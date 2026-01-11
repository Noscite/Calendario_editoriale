import { useState, useEffect } from 'react';
import { format, parseISO } from 'date-fns';
import { it } from 'date-fns/locale';

const platformColors = {
  linkedin: 'bg-[#0077b5]',
  instagram: 'bg-gradient-to-r from-[#f09433] via-[#dc2743] to-[#bc1888]',
  facebook: 'bg-[#1877f2]',
  google: 'bg-[#34a853]',
  blog: 'bg-[#9b59b6]',
};

const platformIcons = {
  linkedin: '💼',
  instagram: '📸',
  facebook: '👥',
  google: '📍',
  blog: '📝',
};

const API_URL = import.meta.env.VITE_API_URL || '';

export default function PostEditModal({ post, isOpen, onClose, onSave }) {
  const [editedPost, setEditedPost] = useState(null);
  const [isSaving, setIsSaving] = useState(false);
  const [isRegenerating, setIsRegenerating] = useState(false);
  const [isGeneratingImage, setIsGeneratingImage] = useState(false);
  const [isUploadingImage, setIsUploadingImage] = useState(false);
  const [isUploadingVideo, setIsUploadingVideo] = useState(false);
  const [imageFormat, setImageFormat] = useState('1080x1080');
  const [isCarousel, setIsCarousel] = useState(false);
  const [numSlides, setNumSlides] = useState(3);
  const [carouselImages, setCarouselImages] = useState([]);
  const [isScheduling, setIsScheduling] = useState(false);
  const [showScheduleOptions, setShowScheduleOptions] = useState(false);
  const [activeTab, setActiveTab] = useState('content');
  const [message, setMessage] = useState(null);

  useEffect(() => {
    if (post) {
      setEditedPost({
        ...post,
        hashtagsText: Array.isArray(post.hashtags) 
          ? post.hashtags.map(h => h.startsWith('#') ? h.slice(1) : h).join(', ') 
          : ''
      });
      // Carica immagini carosello se presenti
      if (post.carousel_images && post.carousel_images.length > 0) {
        setCarouselImages(post.carousel_images);
        setIsCarousel(true);
        setNumSlides(post.carousel_images.length);
      } else {
        setCarouselImages([]);
        setIsCarousel(false);
      }
      // Carica formato immagine se presente
      if (post.image_format) {
        setImageFormat(post.image_format);
      }
      setActiveTab('content');
      setMessage(null);
    }
  }, [post]);

  if (!isOpen || !editedPost) return null;

  const handleChange = (field, value) => {
    setEditedPost(prev => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    setIsSaving(true);
    setMessage(null);
    try {
      const hashtagsArray = editedPost.hashtagsText
        .split(',')
        .map(h => h.trim().replace(/^#/, ''))
        .filter(h => h.length > 0);

      const response = await fetch(`${API_URL}/api/posts/${editedPost.id}`, {
        method: 'PATCH',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          content: editedPost.content,
          hashtags: hashtagsArray,
          scheduled_time: editedPost.scheduled_time,
          scheduled_date: editedPost.scheduled_date,
          visual_suggestion: editedPost.visual_suggestion,
          cta: editedPost.cta,
          pillar: editedPost.pillar
        })
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.detail || 'Errore nel salvataggio');
      }
      
      const updated = await response.json();
      setMessage({ type: 'success', text: '✅ Post salvato con successo!' });
      
      setTimeout(() => {
        if (onSave) onSave(updated);
        onClose();
      }, 1000);
    } catch (error) {
      setMessage({ type: 'error', text: '❌ ' + error.message });
    } finally {
      setIsSaving(false);
    }
  };

  const handleRegenerate = async () => {
    setIsRegenerating(true);
    setMessage(null);
    try {
      const response = await fetch(`${API_URL}/api/posts/${editedPost.id}/regenerate`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        }
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(typeof err.detail === 'string' ? err.detail : JSON.stringify(err.detail) || 'Errore nella rigenerazione');
      }
      
      const regenerated = await response.json();
      setEditedPost({
        ...regenerated,
        hashtagsText: Array.isArray(regenerated.hashtags) 
          ? regenerated.hashtags.map(h => h.startsWith('#') ? h.slice(1) : h).join(', ') 
          : ''
      });
      setMessage({ type: 'success', text: '🔄 Post rigenerato con AI!' });
    } catch (error) {
      setMessage({ type: 'error', text: '❌ ' + error.message });
    } finally {
      setIsRegenerating(false);
    }
  };

  const handleGenerateImage = async () => {
    setIsGeneratingImage(true);
    setMessage(null);
    try {
      const response = await fetch(`${API_URL}/api/posts/${editedPost.id}/generate-carousel`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          visual_suggestion: editedPost.visual_suggestion,
          image_format: imageFormat,
          is_carousel: isCarousel,
          num_slides: isCarousel ? numSlides : 1
        })
      });

      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.detail || 'Errore nella generazione immagine');
      }
      
      const result = await response.json();
      // Aggiorna stato con le immagini generate
      const mainImage = result.images?.[0] || result.image_url;
      setEditedPost(prev => ({ 
        ...prev, 
        image_url: mainImage,
        carousel_images: result.images || [],
        image_format: result.image_format || imageFormat,
        is_carousel: result.is_carousel || false
      }));
      // Aggiorna stato carosello locale
      if (result.images && result.images.length > 1) {
        setCarouselImages(result.images);
        setMessage({ type: 'success', text: `🎠 Carosello generato: ${result.images.length} immagini!` });
      } else {
        setCarouselImages([]);
        setMessage({ type: 'success', text: '🎨 Immagine generata con successo!' });
      }
    } catch (error) {
      setMessage({ type: 'error', text: '❌ ' + error.message });
    } finally {
      setIsGeneratingImage(false);
    }
  };

  const handleImageUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    setIsUploadingImage(true);
    try {
      const token = localStorage.getItem('token');
      const formData = new FormData();
      formData.append('file', file);
      
      const response = await fetch(`/api/posts/${editedPost.id}/upload-image`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });
      
      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.detail || 'Errore upload');
      }
      
      const result = await response.json();
      setEditedPost(prev => ({ ...prev, image_url: result.image_url }));
    } catch (error) {
      console.error('Upload error:', error);
      alert('Errore durante il caricamento: ' + error.message);
    }
    setIsUploadingImage(false);
    e.target.value = ''; // Reset input
  };

  const handleVideoUpload = async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    if (file.size > 100 * 1024 * 1024) {
      setMessage({ type: 'error', text: '❌ Video troppo grande. Max 100MB.' });
      return;
    }
    
    setIsUploadingVideo(true);
    setMessage({ type: 'info', text: '📤 Caricamento video in corso...' });
    
    try {
      const token = localStorage.getItem('token');
      const formData = new FormData();
      formData.append('file', file);
      
      const response = await fetch(`${API_URL}/api/posts/${editedPost.id}/upload-media`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });
      
      if (!response.ok) {
        const error = await response.json();
        throw new Error(error.detail || 'Errore upload');
      }
      
      const result = await response.json();
      setEditedPost(prev => ({ 
        ...prev, 
        image_url: result.media_url,
        media_type: result.media_type 
      }));
      setMessage({ type: 'success', text: '✅ Video caricato!' });
      setTimeout(() => setMessage(null), 2000);
    } catch (error) {
      console.error('Upload error:', error);
      setMessage({ type: 'error', text: '❌ Errore upload: ' + error.message });
    }
    setIsUploadingVideo(false);
    e.target.value = '';
  };

  const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    setMessage({ type: 'success', text: '📋 Copiato negli appunti!' });
    setTimeout(() => setMessage(null), 2000);
  };

  const copyAll = () => {
    const hashtags = editedPost.hashtagsText
      ? editedPost.hashtagsText.split(',').map(h => `#${h.trim()}`).join(' ')
      : '';
    const fullContent = `${editedPost.content || ''}\n\n${hashtags}`.trim();
    navigator.clipboard.writeText(fullContent);
    setMessage({ type: 'success', text: '📋 Contenuto e hashtag copiati!' });
    setTimeout(() => setMessage(null), 2000);
  };
  
  const handleSchedule = async () => {
    setIsScheduling(true);
    setMessage(null);
    try {
      // Prima salva le modifiche
      const hashtagsArray = editedPost.hashtagsText
        .split(',')
        .map(h => h.trim().replace(/^#/, ''))
        .filter(h => h.length > 0);
      
      await fetch(`${API_URL}/api/posts/${editedPost.id}`, {
        method: 'PATCH',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          content: editedPost.content,
          hashtags: hashtagsArray,
          scheduled_time: editedPost.scheduled_time,
          scheduled_date: editedPost.scheduled_date,
          visual_suggestion: editedPost.visual_suggestion,
          cta: editedPost.cta,
          pillar: editedPost.pillar,
          publication_status: 'scheduled'
        })
      });
      
      // Poi schedula la pubblicazione
      // Normalizza l'orario: se ha solo HH:MM aggiungi :00
      const timeStr = editedPost.scheduled_time || '09:00';
      const normalizedTime = timeStr.length === 5 ? timeStr + ':00' : timeStr.substring(0, 8);
      const scheduledDateTime = `${editedPost.scheduled_date}T${normalizedTime}`;
      console.log('DEBUG SCHEDULE:', { scheduledDateTime, platform: editedPost.platform, postId: editedPost.id });
      const response = await fetch(`${API_URL}/api/posts/${editedPost.id}/schedule`, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify({
          scheduled_for: scheduledDateTime,
          platforms: [editedPost.platform]
        })
      });
      
      if (!response.ok) {
        const err = await response.json();
        throw new Error(err.detail || 'Errore nella pianificazione');
      }
      
      setMessage({ type: 'success', text: '📅 Post pianificato per la pubblicazione!' });
      setShowScheduleOptions(false);
      
      setTimeout(() => {
        if (onSave) onSave({ ...editedPost, publication_status: 'scheduled' });
        onClose();
      }, 1500);
    } catch (error) {
      setMessage({ type: 'error', text: '❌ ' + error.message });
    } finally {
      setIsScheduling(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/70 z-50 flex items-center justify-center p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col shadow-2xl" onClick={e => e.stopPropagation()}>
        
        {/* Header */}
        <div className="bg-gradient-to-r from-slate-700 to-slate-800 text-white p-5">
          <div className="flex justify-between items-start">
            <div>
              <h2 className="text-xl font-bold">
                ✏️ Modifica Contenuto - {format(parseISO(editedPost.scheduled_date), "EEEE d MMMM yyyy", { locale: it })}
              </h2>
              <div className="flex items-center gap-3 mt-2">
                <span className={`${platformColors[editedPost.platform]} text-white px-3 py-1 rounded text-sm font-semibold capitalize`}>
                  {platformIcons[editedPost.platform]} {editedPost.platform}
                </span>
                <span className="bg-white/20 px-3 py-1 rounded text-sm">{editedPost.scheduled_time}</span>
                <span className="bg-orange-500 px-3 py-1 rounded text-sm font-medium">{editedPost.pillar}</span>
                {editedPost.publication_status === 'scheduled' && (
                  <span className="bg-blue-500 px-3 py-1 rounded text-sm font-medium flex items-center gap-1">
                    📅 Pianificato
                  </span>
                )}
                {editedPost.publication_status === 'published' && (
                  <span className="bg-green-500 px-3 py-1 rounded text-sm font-medium flex items-center gap-1">
                    ✅ Pubblicato
                  </span>
                )}
                {editedPost.publication_status === 'failed' && (
                  <span className="bg-red-500 px-3 py-1 rounded text-sm font-medium flex items-center gap-1">
                    ❌ Fallito
                  </span>
                )}
              </div>
            </div>
            <button onClick={onClose} className="text-white/70 hover:text-white text-3xl leading-none">×</button>
          </div>
        </div>

        {/* Tabs */}
        <div className="flex border-b bg-gray-50">
          {['content', 'visual', 'settings'].map(tab => (
            <button
              key={tab}
              onClick={() => setActiveTab(tab)}
              className={`flex-1 py-3 px-4 font-medium transition-colors ${
                activeTab === tab 
                  ? 'bg-white text-teal-600 border-b-2 border-teal-500' 
                  : 'text-gray-500 hover:text-gray-700'
              }`}
            >
              {tab === 'content' && '📝 Contenuto'}
              {tab === 'visual' && '🎨 Visual & Immagine'}
              {tab === 'settings' && '⚙️ Impostazioni'}
            </button>
          ))}
        </div>

        {/* Message */}
        {message && (
          <div className={`mx-5 mt-4 p-3 rounded-lg font-medium ${
            message.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
          }`}>
            {message.text}
          </div>
        )}

        {/* Content */}
        <div className="flex-1 overflow-y-auto p-5">
          {activeTab === 'content' && (
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Contenuto del Post</label>
                <textarea
                  value={editedPost.content || ''}
                  onChange={(e) => handleChange('content', e.target.value)}
                  className="w-full h-52 p-4 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none resize-none text-gray-800 leading-relaxed"
                  placeholder="Scrivi il contenuto del post..."
                />
                <div className="flex justify-between items-center mt-2 text-sm text-gray-500">
                  <span>{editedPost.content?.length || 0} caratteri</span>
                  <div className="flex gap-3">
                    <button onClick={() => copyToClipboard(editedPost.content)} className="text-teal-600 hover:text-teal-700 font-medium">
                      📋 Copia contenuto
                    </button>
                    <button onClick={copyAll} className="text-purple-600 hover:text-purple-700 font-medium bg-purple-50 px-3 py-1 rounded-lg">
                      📦 Copia tutto
                    </button>
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Hashtag (separati da virgola)</label>
                <input
                  type="text"
                  value={editedPost.hashtagsText || ''}
                  onChange={(e) => handleChange('hashtagsText', e.target.value)}
                  className="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none"
                  placeholder="AI, Innovazione, Noscite, UmanesimoDigitale"
                />
                <button 
                  onClick={() => copyToClipboard(editedPost.hashtagsText.split(',').map(h => `#${h.trim()}`).join(' '))}
                  className="mt-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
                >
                  🏷️ Copia come #hashtag
                </button>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Call to Action</label>
                <input
                  type="text"
                  value={editedPost.call_to_action || ''}
                  onChange={(e) => handleChange('call_to_action', e.target.value)}
                  className="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none"
                  placeholder="Scopri di più sul nostro sito..."
                />
              </div>
            </div>
          )}

          {activeTab === 'visual' && (
            <div className="space-y-4">
              {/* Selettore Tipo Contenuto */}
              <div className="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-xl p-4">
                <label className="block text-sm font-semibold text-gray-700 mb-3">📱 Tipo di Contenuto</label>
                <div className="flex gap-2 flex-wrap">
                  {[
                    { value: 'post', label: '📱 Post' },
                    { value: 'story', label: '📖 Storia' },
                    { value: 'reel', label: '🎬 Reel' }
                  ].map(type => (
                    <button
                      key={type.value}
                      type="button"
                      onClick={() => setEditedPost(prev => ({ ...prev, content_type: type.value }))}
                      className={`px-4 py-2 rounded-lg font-medium transition-all ${
                        (editedPost.content_type || 'post') === type.value
                          ? 'bg-gradient-to-r from-teal-500 to-cyan-500 text-white shadow-lg'
                          : 'bg-white text-gray-600 hover:bg-gray-100 border'
                      }`}
                    >
                      {type.label}
                    </button>
                  ))}
                </div>
                {(editedPost.content_type === 'story' || editedPost.content_type === 'reel') && (
                  <p className="text-sm text-amber-600 mt-3 bg-amber-50 p-2 rounded-lg">
                    ⚠️ {editedPost.content_type === 'story' 
                      ? 'Le storie richiedono formato verticale (9:16) e durano 24 ore' 
                      : 'I reel richiedono un video verticale (9:16), max 90 secondi'}
                  </p>
                )}
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Suggerimento Visual / Prompt per AI</label>
                <textarea
                  value={editedPost.visual_suggestion || ''}
                  onChange={(e) => handleChange('visual_suggestion', e.target.value)}
                  className="w-full h-32 p-4 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none resize-none"
                  placeholder="Descrivi il visual per questo post... Es: 'Grafica minimalista con icona di AI su sfondo teal, stile Noscite'"
                />
              </div>

              {editedPost.image_url && (
                <div className="bg-gray-50 rounded-xl p-4">
                  <label className="block text-sm font-semibold text-gray-700 mb-3">
                    {editedPost.media_type === 'video' ? '🎬 Video Caricato' : '🖼️ Immagine Generata'}
                  </label>
                  {editedPost.media_type === 'video' ? (
                    <video 
                      src={editedPost.image_url} 
                      controls 
                      className="w-full max-w-lg rounded-xl shadow-lg mx-auto"
                    />
                  ) : (
                    <img 
                      src={editedPost.image_url} 
                      alt="Generated" 
                      className="w-full max-w-lg rounded-xl shadow-lg mx-auto" 
                    />
                  )}
                  <div className="mt-3 text-center">
                    <a 
                      href={editedPost.image_url} 
                      target="_blank" 
                      rel="noopener noreferrer"
                      className="text-teal-600 hover:text-teal-700 text-sm font-medium"
                    >
                      🔗 Apri {editedPost.media_type === 'video' ? 'video' : 'immagine'} in nuova tab
                    </a>
                  </div>
                </div>
              )}

              {/* Formato Immagine */}
              <div className="bg-gray-50 rounded-xl p-4">
                <label className="block text-sm font-semibold text-gray-700 mb-3">📐 Formato Immagine</label>
                <div className="flex gap-2 flex-wrap">
                  {[
                    { value: '1080x1080', label: '⬜ Quadrato', desc: 'Feed' },
                    { value: '1080x1920', label: '📱 Verticale', desc: 'Story/Reel' },
                    { value: '1920x1080', label: '🖥️ Orizzontale', desc: 'Cover' }
                  ].map(fmt => (
                    <button
                      key={fmt.value}
                      type="button"
                      onClick={() => setImageFormat(fmt.value)}
                      className={`px-4 py-2 rounded-lg font-medium transition-all flex flex-col items-center ${
                        imageFormat === fmt.value
                          ? 'bg-gradient-to-r from-purple-500 to-pink-500 text-white shadow-lg'
                          : 'bg-white text-gray-600 hover:bg-gray-100 border'
                      }`}
                    >
                      <span>{fmt.label}</span>
                      <span className="text-xs opacity-75">{fmt.desc}</span>
                    </button>
                  ))}
                </div>
              </div>

              {/* Carosello */}
              <div className="bg-gray-50 rounded-xl p-4">
                <div className="flex items-center justify-between mb-3">
                  <label className="text-sm font-semibold text-gray-700">🎠 Carosello (più immagini)</label>
                  <button
                    type="button"
                    onClick={() => setIsCarousel(!isCarousel)}
                    className={`relative w-14 h-7 rounded-full transition-colors ${
                      isCarousel ? 'bg-purple-500' : 'bg-gray-300'
                    }`}
                  >
                    <span className={`absolute top-1 w-5 h-5 bg-white rounded-full transition-transform shadow ${
                      isCarousel ? 'translate-x-8' : 'translate-x-1'
                    }`} />
                  </button>
                </div>
                {isCarousel && (
                  <div className="mt-3">
                    <label className="block text-sm text-gray-600 mb-2">Numero di slide: {numSlides}</label>
                    <input
                      type="range"
                      min="2"
                      max="5"
                      value={numSlides}
                      onChange={(e) => setNumSlides(parseInt(e.target.value))}
                      className="w-full accent-purple-500"
                    />
                    <div className="flex justify-between text-xs text-gray-400 mt-1">
                      <span>2</span>
                      <span>3</span>
                      <span>4</span>
                      <span>5</span>
                    </div>
                  </div>
                )}
              </div>

              {/* Mostra immagini carosello se presenti */}
              {carouselImages.length > 0 && (
                <div className="bg-gray-50 rounded-xl p-4">
                  <label className="block text-sm font-semibold text-gray-700 mb-3">🖼️ Immagini Carosello</label>
                  <div className="grid grid-cols-3 gap-2">
                    {carouselImages.map((img, idx) => (
                      <img key={idx} src={img} alt={`Slide ${idx+1}`} className="rounded-lg shadow-sm" />
                    ))}
                  </div>
                </div>
              )}

              <button
                onClick={handleGenerateImage}
                disabled={isGeneratingImage || !editedPost.visual_suggestion}
                className={`w-full py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all ${
                  isGeneratingImage || !editedPost.visual_suggestion
                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    : 'bg-gradient-to-r from-purple-500 to-pink-500 text-white hover:from-purple-600 hover:to-pink-600 shadow-lg hover:shadow-xl'
                }`}
              >
                {isGeneratingImage ? (
                  <>
                    <span className="animate-spin">⏳</span> Generazione {isCarousel ? `${numSlides} immagini` : 'immagine'} in corso...
                  </>
                ) : (
                  <>{isCarousel ? `🎠 Genera Carosello (${numSlides} immagini)` : '🎨 Genera Immagine con DALL-E'}</>
                )}
              </button>
              
              {/* Upload immagine custom */}
              <div className="relative">
                <input
                  type="file"
                  id="image-upload"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  onChange={handleImageUpload}
                  className="hidden"
                />
                <label
                  htmlFor="image-upload"
                  className={`w-full py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                    isUploadingImage
                      ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      : 'bg-gradient-to-r from-teal-500 to-cyan-500 text-white hover:from-teal-600 hover:to-cyan-600 shadow-lg hover:shadow-xl'
                  }`}
                >
                  {isUploadingImage ? (
                    <>
                      <span className="animate-spin">⏳</span> Caricamento in corso...
                    </>
                  ) : (
                    <>🖼️ Carica Immagine</>
                  )}
                </label>
              </div>
              
              {/* Upload Video */}
              <div className="relative">
                <input
                  type="file"
                  id="video-upload"
                  accept="video/mp4,video/quicktime,video/webm,video/mov"
                  onChange={handleVideoUpload}
                  className="hidden"
                />
                <label
                  htmlFor="video-upload"
                  className={`w-full py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all cursor-pointer ${
                    isUploadingVideo
                      ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                      : 'bg-gradient-to-r from-blue-500 to-indigo-500 text-white hover:from-blue-600 hover:to-indigo-600 shadow-lg hover:shadow-xl'
                  }`}
                >
                  {isUploadingVideo ? (
                    <>
                      <span className="animate-spin">⏳</span> Caricamento video...
                    </>
                  ) : (
                    <>🎬 Carica Video (per Reel/Storie)</>
                  )}
                </label>
              </div>

              {!editedPost.visual_suggestion && (
                <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                  <p className="text-red-600 font-medium">❌ Nessun suggerimento visual disponibile</p>
                  <p className="text-sm text-gray-500 mt-1">Inserisci un prompt nel campo sopra per generare un'immagine</p>
                </div>
              )}
            </div>
          )}

          {activeTab === 'settings' && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-2">Data Pubblicazione</label>
                  <input
                    type="date"
                    value={editedPost.scheduled_date || ''}
                    onChange={(e) => handleChange('scheduled_date', e.target.value)}
                    className="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-gray-700 mb-2">Orario</label>
                  <input
                    type="time"
                    value={editedPost.scheduled_time?.slice(0,5) || '09:00'}
                    onChange={(e) => handleChange('scheduled_time', e.target.value)}
                    className="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Pillar / Tema</label>
                <input
                  type="text"
                  value={editedPost.pillar || ''}
                  onChange={(e) => handleChange('pillar', e.target.value)}
                  className="w-full p-3 border-2 border-gray-200 rounded-xl focus:border-teal-500 focus:outline-none"
                  placeholder="Umanesimo Digitale"
                />
              </div>

              <div className="bg-gray-50 rounded-xl p-4">
                <h4 className="font-semibold text-gray-700 mb-3">📊 Info Post</h4>
                <div className="text-sm text-gray-600 space-y-2">
                  <p><strong>ID:</strong> {editedPost.id}</p>
                  <p><strong>Piattaforma:</strong> <span className="capitalize">{editedPost.platform}</span></p>
                  <p><strong>Formato:</strong> {editedPost.format || 'standard'}</p>
                  <p><strong>Progetto ID:</strong> {editedPost.project_id}</p>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="border-t bg-gray-50 p-5 flex gap-3 flex-wrap">
          <button
            onClick={handleRegenerate}
            disabled={isRegenerating}
            className={`px-5 py-2.5 rounded-xl font-semibold flex items-center gap-2 transition-all ${
              isRegenerating 
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed' 
                : 'bg-gradient-to-r from-amber-500 to-orange-500 text-white hover:from-amber-600 hover:to-orange-600 shadow-md hover:shadow-lg'
            }`}
          >
            {isRegenerating ? (
              <><span className="animate-spin">⏳</span> Rigenerazione...</>
            ) : (
              <>🔄 Rigenera con AI</>
            )}
          </button>
          
          <div className="flex-1"></div>
          
          {/* Bottone Pianifica */}
          <button
            onClick={handleSchedule}
            disabled={isScheduling}
            className={`px-5 py-2.5 rounded-xl font-semibold flex items-center gap-2 transition-all ${
              isScheduling 
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed' 
                : 'bg-gradient-to-r from-blue-500 to-blue-600 text-white hover:from-blue-600 hover:to-blue-700 shadow-md hover:shadow-lg'
            }`}
          >
            {isScheduling ? (
              <><span className="animate-spin">⏳</span> Pianificazione...</>
            ) : (
              <>📅 Pianifica</>
            )}
          </button>
          
          <button
            onClick={onClose}
            className="px-5 py-2.5 border-2 border-gray-300 text-gray-600 rounded-xl font-semibold hover:bg-gray-100 transition-all"
          >
            Annulla
          </button>
          
          <button
            onClick={handleSave}
            disabled={isSaving}
            className={`px-6 py-2.5 rounded-xl font-semibold flex items-center gap-2 transition-all ${
              isSaving 
                ? 'bg-gray-300 text-gray-500 cursor-not-allowed' 
                : 'bg-gradient-to-r from-teal-500 to-teal-600 text-white hover:from-teal-600 hover:to-teal-700 shadow-md hover:shadow-lg'
            }`}
          >
            {isSaving ? (
              <><span className="animate-spin">⏳</span> Salvataggio...</>
            ) : (
              <>💾 Salva Modifiche</>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, Calendar, Download, Filter, Loader2, 
  Plus, Check, Linkedin, Instagram, Globe, FileText,
  ChevronLeft, ChevronRight, Edit3, Trash2, X, RefreshCw,
  Settings, Link, Target, Users, Sparkles, Save
} from 'lucide-react';
import { posts as postsApi, projects as projectsApi, generation } from '../services/api';
import PostEditModal from '../components/PostEditModal';
import BrandDocuments from '../components/BrandDocuments';

import GenerationProgress from '../components/GenerationProgress';
const PLATFORMS = [
  { id: 'linkedin', name: 'LinkedIn', icon: Linkedin, color: 'bg-blue-600' },
  { id: 'instagram', name: 'Instagram', icon: Instagram, color: 'bg-gradient-to-r from-purple-500 to-pink-500' },
  { id: 'facebook', name: 'Facebook', icon: Globe, color: 'bg-blue-500' },
  { id: 'google_business', name: 'Google Business', icon: Globe, color: 'bg-green-600' },
  // { id: 'blog', name: 'Blog', icon: FileText, color: 'bg-teal-500' }, // Temporaneamente disabilitato
];

const DAYS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
const MONTHS = ['Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 
                'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];

export default function ProjectDetail() {
  const { projectId: id } = useParams();
  const navigate = useNavigate();
  
  // Core state
  const [project, setProject] = useState(null);
  const [postsList, setPostsList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [currentDate, setCurrentDate] = useState(new Date());
  const [selectedPlatform, setSelectedPlatform] = useState('all');
  
  // View mode: 'calendar' or 'settings'
  const [viewMode, setViewMode] = useState('calendar');
  
  // Edit modal
  const [editingPost, setEditingPost] = useState(null);
  
  // Selection mode for batch operations
  const [selectionMode, setSelectionMode] = useState(false);
  const [selectedPostIds, setSelectedPostIds] = useState([]);
  
  // Add post modal
  const [showAddModal, setShowAddModal] = useState(false);
  const [addMode, setAddMode] = useState('manual'); // 'manual' or 'ai'
  const [isAddingPosts, setIsAddingPosts] = useState(false);
  const [addMessage, setAddMessage] = useState(null);
  
  // Manual post form
  const [newPost, setNewPost] = useState({
    platform: 'linkedin',
    scheduled_date: new Date().toISOString().split('T')[0],
    scheduled_time: '09:00',
    content: '',
    hashtags: '',
    pillar: '',
    cta: '',
    visual_suggestion: ''
  });
  
  // AI campaign form
  const [aiCampaign, setAiCampaign] = useState({
    platforms: [],  // Array di piattaforme selezionate
    ai_decide_platforms: true,  // L'AI decide le piattaforme
    start_date: new Date().toISOString().split('T')[0],
    end_date: new Date(Date.now() + 30*24*60*60*1000).toISOString().split('T')[0],
    num_posts: 10,
    ai_decide_num_posts: true,  // L'AI decide il numero
    brief: '',
    pillar: ''
  });
  
  // Batch replace modal
  const [showReplaceModal, setShowReplaceModal] = useState(false);
  const [replaceBrief, setReplaceBrief] = useState('');
  const [isReplacing, setIsReplacing] = useState(false);
  
  // Settings form
  const [settingsForm, setSettingsForm] = useState(null);
  const [isSavingSettings, setIsSavingSettings] = useState(false);
  const [settingsMessage, setSettingsMessage] = useState(null);
  const [newPillar, setNewPillar] = useState('');
  const [newUrl, setNewUrl] = useState('');
  const [newCompetitor, setNewCompetitor] = useState('');
  const [isRegenerating, setIsRegenerating] = useState(false);
  const [isRegeneratingPersonas, setIsRegeneratingPersonas] = useState(false);
  const [regeneratingPersonaIndex, setRegeneratingPersonaIndex] = useState(null);
  const [deletingPersonaIndex, setDeletingPersonaIndex] = useState(null);
  const [isAddingPersona, setIsAddingPersona] = useState(false);
  
  // Social connections
  const [socialConnections, setSocialConnections] = useState([]);
  const [loadingConnections, setLoadingConnections] = useState(false);

  // Load data
  useEffect(() => {
    loadData();
  }, [id]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [projectRes, postsRes] = await Promise.all([
        projectsApi.get(id),
        postsApi.list(id)
      ]);
      setProject(projectRes.data);
      setPostsList(postsRes.data || []);
      
      // Se il progetto è in generazione, attiva il progress
      if (projectRes.data.status === 'generating') {
        setIsRegenerating(true);
      }
      
      // Initialize settings form
      setSettingsForm({
        name: projectRes.data.name || '',
        start_date: projectRes.data.start_date || '',
        end_date: projectRes.data.end_date || '',
        platforms: projectRes.data.platforms || [],
        posts_per_week: projectRes.data.posts_per_week || {},
        brief: projectRes.data.brief || '',
        target_audience: projectRes.data.target_audience || '',
        content_pillars: projectRes.data.content_pillars || [],
        reference_urls: projectRes.data.reference_urls || [],
        competitors: projectRes.data.competitors || [],
        custom_prompt: projectRes.data.custom_prompt || ''
      });
      
      // Carica connessioni social
      if (projectRes.data.brand_id) {
        loadSocialConnections(projectRes.data.brand_id);
      }
    } catch (error) {
      console.error('Error loading project:', error);
    }
    setLoading(false);
  };


  const loadSocialConnections = async (brandId) => {
    if (!brandId) return;
    setLoadingConnections(true);
    try {
      const token = localStorage.getItem('token');
      const response = await fetch(`/api/social/connections/${brandId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (response.ok) {
        const data = await response.json();
        setSocialConnections(data);
      }
    } catch (error) {
      console.error('Error loading social connections:', error);
    }
    setLoadingConnections(false);
  };

  const connectSocial = (platform) => {
    if (!project?.brand_id) return;
    const token = localStorage.getItem('token');
    window.location.href = `/api/social/authorize/${platform}?brand_id=${project.brand_id}&token=${token}`;
  };

  const disconnectSocial = async (connectionId) => {
    if (!window.confirm('Vuoi disconnettere questo account social?')) return;
    try {
      const token = localStorage.getItem('token');
      await fetch(`/api/social/disconnect/${connectionId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      loadSocialConnections(project.brand_id);
    } catch (error) {
      console.error('Error disconnecting:', error);
    }
  };

  const isConnected = (platformId) => {
    return socialConnections.some(c => c.platform === platformId && c.is_active);
  };

  const getConnection = (platformId) => {
    return socialConnections.find(c => c.platform === platformId && c.is_active);
  };
  const handleRegeneratePersonas = async () => {
    if (!window.confirm("Vuoi rigenerare le buyer personas? Le attuali verranno sostituite.")) return;
    setIsRegeneratingPersonas(true);
    try {
      const response = await generation.generatePersonas(id);
      if (response.data.personas) {
        await generation.confirmPersonas(id, response.data.personas);
        await loadData();
      }
    } catch (err) {
      console.error("Error regenerating personas:", err);
      alert("Errore nella rigenerazione delle personas");
    } finally {
      setIsRegeneratingPersonas(false);
    };
  };


  const handleDeletePersona = async (index, personaName) => {
    if (!window.confirm(`Eliminare "${personaName}"?`)) return;
    setDeletingPersonaIndex(index);
    try {
      await generation.deletePersona(id, index);
      await loadData();
    } catch (err) {
      console.error("Error deleting persona:", err);
      alert("Errore nell'eliminazione");
    } finally {
      setDeletingPersonaIndex(null);
    }
  };

  const handleRegeneratePersona = async (index, personaName) => {
    if (!window.confirm(`Rigenerare "${personaName}"?`)) return;
    setRegeneratingPersonaIndex(index);
    try {
      await generation.regeneratePersona(id, index);
      await loadData();
    } catch (err) {
      console.error("Error regenerating persona:", err);
      alert("Errore nella rigenerazione");
    } finally {
      setRegeneratingPersonaIndex(null);
    }
  };

  const handleAddPersona = async () => {
    setIsAddingPersona(true);
    try {
      await generation.addPersona(id);
      await loadData();
    } catch (err) {
      console.error("Error adding persona:", err);
      alert("Errore nell'aggiunta");
    } finally {
      setIsAddingPersona(false);
    }
  };

  // Calendar helpers
  const getDaysInMonth = (date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const days = [];
    
    // Previous month days
    const firstDayOfWeek = (firstDay.getDay() + 6) % 7;
    for (let i = firstDayOfWeek - 1; i >= 0; i--) {
      const d = new Date(year, month, -i);
      days.push({ date: d, currentMonth: false });
    }
    
    // Current month days
    for (let i = 1; i <= lastDay.getDate(); i++) {
      days.push({ date: new Date(year, month, i), currentMonth: true });
    }
    
    // Next month days
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
      days.push({ date: new Date(year, month + 1, i), currentMonth: false });
    }
    
    return days;
  };

  const getPostsForDate = (date) => {
    const dateStr = date.toISOString().split('T')[0];
    return postsList.filter(post => {
      const postDate = post.scheduled_date?.split('T')[0];
      const platformMatch = selectedPlatform === 'all' || post.platform === selectedPlatform;
      return postDate === dateStr && platformMatch;
    });
  };

  const getPlatformIcon = (platform) => {
    const p = PLATFORMS.find(pl => pl.id === platform);
    return p ? p.icon : Globe;
  };

  const getPlatformColor = (platform) => {
    const p = PLATFORMS.find(pl => pl.id === platform);
    return p ? p.color : 'bg-gray-500';
  };

  // Navigation
  const prevMonth = () => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() - 1));
  const nextMonth = () => setCurrentDate(new Date(currentDate.getFullYear(), currentDate.getMonth() + 1));

  // Post editing
  const openEditModal = (post) => {
    if (selectionMode) {
      togglePostSelection(post.id);
    } else {
      setEditingPost(post);
    }
  };

  const handlePostSave = (updatedPost) => {
    setPostsList(prev => prev.map(p => p.id === updatedPost.id ? updatedPost : p));
  };

  // Selection mode
  const togglePostSelection = (postId) => {
    setSelectedPostIds(prev => 
      prev.includes(postId) 
        ? prev.filter(id => id !== postId)
        : [...prev, postId]
    );
  };

  const selectAllPosts = () => {
    const filtered = selectedPlatform === 'all' 
      ? postsList 
      : postsList.filter(p => p.platform === selectedPlatform);
    setSelectedPostIds(filtered.map(p => p.id));
  };

  const exitSelectionMode = () => {
    setSelectionMode(false);
    setSelectedPostIds([]);
  };

  // Add manual post
  const handleAddManualPost = async () => {
    setIsAddingPosts(true);
    setAddMessage(null);
    try {
      const res = await postsApi.createManual({
        project_id: parseInt(id),
        ...newPost
      });
      setPostsList(prev => [...prev, res.data]);
      setAddMessage({ type: 'success', text: 'Post creato con successo!' });
      setTimeout(() => {
        setShowAddModal(false);
        setAddMessage(null);
        setNewPost({
          platform: 'linkedin',
          scheduled_date: new Date().toISOString().split('T')[0],
          scheduled_time: '09:00',
          content: '',
          hashtags: '',
          pillar: '',
          cta: '',
          visual_suggestion: ''
        });
      }, 1500);
    } catch (error) {
      setAddMessage({ type: 'error', text: 'Errore nella creazione del post' });
    }
    setIsAddingPosts(false);
  };

  // Add AI campaign
  const handleAddAICampaign = async () => {
    setIsAddingPosts(true);
    setAddMessage(null);
    try {
      const res = await postsApi.generateAI({
        project_id: parseInt(id),
        ...aiCampaign
      });
      setPostsList(prev => [...prev, ...(res.data.posts || [])]);
      setAddMessage({ type: 'success', text: `${res.data.posts?.length || 0} post generati!` });
      setTimeout(() => {
        setShowAddModal(false);
        setAddMessage(null);
      }, 1500);
    } catch (error) {
      setAddMessage({ type: 'error', text: 'Errore nella generazione AI' });
    }
    setIsAddingPosts(false);
  };

  // Batch delete
  const handleBatchDelete = async () => {
    if (!selectedPostIds.length) return;
    if (!confirm(`Eliminare ${selectedPostIds.length} post selezionati?`)) return;
    
    try {
      await postsApi.batchDelete(selectedPostIds);
      setPostsList(prev => prev.filter(p => !selectedPostIds.includes(p.id)));
      exitSelectionMode();
    } catch (error) {
      alert('Errore nell\'eliminazione');
    }
  };

  // Batch replace
  const handleBatchReplace = async () => {
    if (!replaceBrief.trim()) return;
    setIsReplacing(true);
    try {
      const res = await postsApi.batchReplace(selectedPostIds, replaceBrief);
      // Remove old posts, add new ones
      setPostsList(prev => [
        ...prev.filter(p => !selectedPostIds.includes(p.id)),
        ...(res.data.posts || [])
      ]);
      setShowReplaceModal(false);
      setReplaceBrief('');
      exitSelectionMode();
    } catch (error) {
      alert('Errore nella sostituzione');
    }
    setIsReplacing(false);
  };

  // Settings handlers
  const updateSettingsForm = (field, value) => {
    setSettingsForm(prev => ({ ...prev, [field]: value }));
  };

  const togglePlatform = (platform) => {
    const platforms = settingsForm.platforms.includes(platform)
      ? settingsForm.platforms.filter(p => p !== platform)
      : [...settingsForm.platforms, platform];
    updateSettingsForm('platforms', platforms);
  };

  const addPillar = () => {
    if (newPillar.trim() && !settingsForm.content_pillars.includes(newPillar.trim())) {
      updateSettingsForm('content_pillars', [...settingsForm.content_pillars, newPillar.trim()]);
      setNewPillar('');
    }
  };

  const removePillar = (idx) => {
    updateSettingsForm('content_pillars', settingsForm.content_pillars.filter((_, i) => i !== idx));
  };

  const addUrl = () => {
    if (newUrl.trim()) {
      updateSettingsForm('reference_urls', [...settingsForm.reference_urls, newUrl.trim()]);
      setNewUrl('');
    }
  };

  const removeUrl = (idx) => {
    updateSettingsForm('reference_urls', settingsForm.reference_urls.filter((_, i) => i !== idx));
  };

  const addCompetitor = () => {
    if (newCompetitor.trim()) {
      updateSettingsForm('competitors', [...settingsForm.competitors, newCompetitor.trim()]);
      setNewCompetitor('');
    }
  };

  const removeCompetitor = (idx) => {
    updateSettingsForm('competitors', settingsForm.competitors.filter((_, i) => i !== idx));
  };

  const saveSettings = async () => {
    setIsSavingSettings(true);
    setSettingsMessage(null);
    try {
      await projectsApi.update(id, settingsForm);
      setProject(prev => ({ ...prev, ...settingsForm }));
      setSettingsMessage({ type: 'success', text: 'Impostazioni salvate!' });
      setTimeout(() => setSettingsMessage(null), 3000);
    } catch (error) {
      setSettingsMessage({ type: 'error', text: 'Errore nel salvataggio' });
    }
    setIsSavingSettings(false);
  };
  const regenerateCalendar = async () => {
    if (!confirm('Rigenerare tutti i post? I post esistenti verranno eliminati.')) return;
    setIsRegenerating(true);
    try {
      // Prima salva le impostazioni
      await projectsApi.update(id, settingsForm);
      // Avvia la generazione (non aspettiamo il completamento)
      generation.generateCalendar(id).catch(err => {
        console.error('Generation error:', err);
        setSettingsMessage({ type: 'error', text: 'Errore nella rigenerazione' });
        setIsRegenerating(false);
      });
      // Il progress bar gestirà il resto
    } catch (error) {
      setSettingsMessage({ type: 'error', text: 'Errore nel salvataggio impostazioni' });
      setIsRegenerating(false);
    }
  };

  const handleGenerationComplete = async () => {
    // Ricarica i post
    try {
      const postsRes = await postsApi.list(id);
      setPostsList(postsRes.data || []);
      setSettingsMessage({ type: 'success', text: 'Calendario rigenerato!' });
      setViewMode('calendar');
    } catch (error) {
      setSettingsMessage({ type: 'error', text: 'Errore nel caricamento post' });
    }
    setIsRegenerating(false);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={40} />
      </div>
    );
  }

  const days = getDaysInMonth(currentDate);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Progress Bar per generazione */}
      {isRegenerating && (
        <GenerationProgress
          projectId={id}
          isGenerating={isRegenerating}
          onComplete={handleGenerationComplete}
        />
      )}
      {/* Header */}
      <header className="bg-white shadow-sm sticky top-0 z-40">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <button onClick={() => navigate('/dashboard')} className="p-2 hover:bg-gray-100 rounded-lg" title="Torna alla Dashboard">
                <ArrowLeft size={20} />
              </button>
              <div>
                <h1 className="text-xl font-bold text-[#2C3E50]">{project?.name}</h1>
                <p className="text-sm text-gray-500">
                  {project?.start_date} → {project?.end_date} • {postsList.length} post
                </p>
              </div>
            </div>
            
            <div className="flex items-center gap-2">
              {/* View mode tabs */}
              <div className="flex bg-gray-100 rounded-lg p-1 mr-4">
                <button
                  onClick={() => setViewMode('calendar')}
                  className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${
                    viewMode === 'calendar' 
                      ? 'bg-white text-[#3DAFA8] shadow-sm' 
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  <Calendar size={16} className="inline mr-2" />
                  Calendario
                </button>
                <button
                  onClick={() => setViewMode('settings')}
                  className={`px-4 py-2 rounded-md text-sm font-medium transition-all ${
                    viewMode === 'settings' 
                      ? 'bg-white text-[#3DAFA8] shadow-sm' 
                      : 'text-gray-600 hover:text-gray-900'
                  }`}
                >
                  <Settings size={16} className="inline mr-2" />
                  Impostazioni
                </button>
              </div>
              
              <button 
                onClick={async () => {
                  try {
                    const token = localStorage.getItem('token');
                    const res = await fetch(`/api/export/excel/${id}`, {
                      headers: { 'Authorization': `Bearer ${token}` }
                    });
                    if (!res.ok) throw new Error('Export failed');
                    const blob = await res.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `piano_editoriale_${project?.name?.replace(/ /g, '_')}.xlsx`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                  } catch (err) {
                    console.error('Export error:', err);
                    alert('Errore durante l\'export');
                  }
                }}
                className="flex items-center gap-2 px-4 py-2 border rounded-lg hover:bg-gray-50"
              >
                <Download size={18} /> Esporta
              </button>
            </div>
          </div>
        </div>
      </header>

      {/* Calendar View */}
      {viewMode === 'calendar' && (
        <main className="max-w-7xl mx-auto px-4 py-6">
          {/* Actions Bar */}
          <div className="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div className="flex items-center justify-between flex-wrap gap-4">
              {/* Left: Add & Select */}
              <div className="flex items-center gap-3">
                <button
                  onClick={() => setShowAddModal(true)}
                  className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-lg hover:opacity-90"
                >
                  <Plus size={18} /> Aggiungi Post
                </button>
                
                {!selectionMode ? (
                  <button
                    onClick={() => setSelectionMode(true)}
                    className="flex items-center gap-2 px-4 py-2 border border-purple-300 text-purple-600 rounded-lg hover:bg-purple-50"
                  >
                    <Check size={18} /> Seleziona
                  </button>
                ) : (
                  <button
                    onClick={exitSelectionMode}
                    className="flex items-center gap-2 px-4 py-2 bg-purple-100 text-purple-700 rounded-lg"
                  >
                    <X size={18} /> Esci selezione
                  </button>
                )}
              </div>
              
              {/* Center: Month Navigation */}
              <div className="flex items-center gap-4">
                <button onClick={prevMonth} className="p-2 hover:bg-gray-100 rounded-lg">
                  <ChevronLeft size={20} />
                </button>
                <h2 className="text-lg font-semibold text-[#2C3E50] min-w-[200px] text-center">
                  {MONTHS[currentDate.getMonth()]} {currentDate.getFullYear()}
                </h2>
                <button onClick={nextMonth} className="p-2 hover:bg-gray-100 rounded-lg">
                  <ChevronRight size={20} />
                </button>
              </div>
              
              {/* Right: Filters & Batch Actions */}
              <div className="flex items-center gap-3">
                {selectionMode && selectedPostIds.length > 0 && (
                  <>
                    <button
                      onClick={() => setShowReplaceModal(true)}
                      className="flex items-center gap-2 px-3 py-2 bg-amber-100 text-amber-700 rounded-lg hover:bg-amber-200"
                    >
                      <RefreshCw size={16} /> Sostituisci ({selectedPostIds.length})
                    </button>
                    <button
                      onClick={handleBatchDelete}
                      className="flex items-center gap-2 px-3 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200"
                    >
                      <Trash2 size={16} /> Elimina ({selectedPostIds.length})
                    </button>
                  </>
                )}
                
                <select
                  value={selectedPlatform}
                  onChange={(e) => setSelectedPlatform(e.target.value)}
                  className="px-3 py-2 border rounded-lg"
                >
                  <option value="all">Tutte le piattaforme</option>
                  {PLATFORMS.filter(p => project?.platforms?.includes(p.id)).map(p => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
              </div>
            </div>
            
            {/* Selection info bar */}
            {selectionMode && (
              <div className="mt-4 pt-4 border-t flex items-center justify-between bg-purple-50 -mx-4 -mb-4 px-4 py-3 rounded-b-xl">
                <span className="text-purple-700">
                  {selectedPostIds.length} post selezionati
                </span>
                <button
                  onClick={selectAllPosts}
                  className="text-purple-600 hover:text-purple-800 text-sm font-medium"
                >
                  Seleziona tutti ({selectedPlatform === 'all' ? postsList.length : postsList.filter(p => p.platform === selectedPlatform).length})
                </button>
              </div>
            )}
          </div>

          {/* Calendar Grid */}
          <div className="bg-white rounded-xl shadow-sm overflow-hidden">
            {/* Days header */}
            <div className="grid grid-cols-7 border-b">
              {DAYS.map(day => (
                <div key={day} className="py-3 text-center text-sm font-medium text-gray-500 border-r last:border-r-0">
                  {day}
                </div>
              ))}
            </div>
            
            {/* Days grid */}
            <div className="grid grid-cols-7">
              {days.map((day, idx) => {
                const dayPosts = getPostsForDate(day.date);
                const isToday = day.date.toDateString() === new Date().toDateString();
                
                return (
                  <div
                    key={idx}
                    className={`min-h-[120px] border-r border-b last:border-r-0 p-2 ${
                      day.currentMonth ? 'bg-white' : 'bg-gray-50'
                    }`}
                  >
                    <div className={`text-sm mb-1 ${
                      isToday 
                        ? 'w-7 h-7 bg-[#3DAFA8] text-white rounded-full flex items-center justify-center font-bold' 
                        : day.currentMonth ? 'text-gray-900' : 'text-gray-400'
                    }`}>
                      {day.date.getDate()}
                    </div>
                    
                    <div className="space-y-1">
                      {dayPosts.slice(0, 3).map(post => {
                        const Icon = getPlatformIcon(post.platform);
                        const isSelected = selectedPostIds.includes(post.id);
                        
                        return (
                          <div
                            key={post.id}
                            onClick={() => openEditModal(post)}
                            className={`text-xs p-1.5 rounded cursor-pointer transition-all ${getPlatformColor(post.platform)} text-white truncate flex items-center gap-1 ${
                              isSelected ? 'ring-2 ring-purple-500 ring-offset-1' : ''
                            } ${selectionMode ? 'hover:ring-2 hover:ring-purple-300' : 'hover:opacity-80'}`}
                          >
                            {selectionMode && (
                              <input
                                type="checkbox"
                                checked={isSelected}
                                onChange={() => {}}
                                className="w-3 h-3 rounded"
                                onClick={(e) => e.stopPropagation()}
                              />
                            )}
                            <Icon size={12} />
                            <span className="truncate">{post.content?.substring(0, 25) || 'Post'}</span>
                          </div>
                        );
                      })}
                      {dayPosts.length > 3 && (
                        <div className="text-xs text-gray-500 text-center">
                          +{dayPosts.length - 3} altri
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </main>
      )}

      {/* Settings View */}
      {viewMode === 'settings' && settingsForm && (
        <main className="max-w-4xl mx-auto px-4 py-6">
          <div className="bg-white rounded-xl shadow-sm p-6">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-[#2C3E50]">Impostazioni Progetto</h2>
              {settingsMessage && (
                <div className={`px-4 py-2 rounded-lg text-sm ${
                  settingsMessage.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                }`}>
                  {settingsMessage.text}
                </div>
              )}
            </div>
            
            <div className="space-y-8">
              {/* Basic Info */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <FileText size={20} className="text-[#3DAFA8]" />
                  Informazioni Base
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="md:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Nome Progetto</label>
                    <input
                      type="text"
                      value={settingsForm.name}
                      onChange={(e) => updateSettingsForm('name', e.target.value)}
                      className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Data Inizio</label>
                    <input
                      type="date"
                      value={settingsForm.start_date}
                      onChange={(e) => updateSettingsForm('start_date', e.target.value)}
                      className="w-full px-4 py-2 border rounded-lg"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Data Fine</label>
                    <input
                      type="date"
                      value={settingsForm.end_date}
                      onChange={(e) => updateSettingsForm('end_date', e.target.value)}
                      className="w-full px-4 py-2 border rounded-lg"
                    />
                  </div>
                </div>
              </section>
              
              {/* Brief & Target */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Target size={20} className="text-[#E89548]" />
                  Brief e Target
                </h3>
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Brief / Obiettivi</label>
                    <textarea
                      value={settingsForm.brief}
                      onChange={(e) => updateSettingsForm('brief', e.target.value)}
                      rows={4}
                      placeholder="Descrivi gli obiettivi del piano editoriale..."
                      className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Target Audience</label>
                    <textarea
                      value={settingsForm.target_audience}
                      onChange={(e) => updateSettingsForm('target_audience', e.target.value)}
                      rows={2}
                      placeholder="Chi è il pubblico target..."
                      className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                    />
                  </div>
                </div>
              </section>
              
              
              {/* Buyer Personas */}
              {project.buyer_personas && project.buyer_personas.personas && (
                <section className="mb-6">
                  <div className="flex justify-between items-center mb-4">
                    <h3 className="text-lg font-semibold text-[#2C3E50] flex items-center gap-2">
                      <Users size={20} className="text-[#3DAFA8]" />
                      Buyer Personas
                    </h3>
                    <button
                      onClick={handleRegeneratePersonas}
                      disabled={isRegeneratingPersonas}
                      className="flex items-center gap-1 text-sm text-[#3DAFA8] hover:text-[#2C3E50] disabled:opacity-50"
                    >
                      <RefreshCw size={16} className={isRegeneratingPersonas ? "animate-spin" : ""} />
                      {isRegeneratingPersonas ? "Rigenerando..." : "Rigenera Tutte"}
                    </button>
                  </div>
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {project.buyer_personas.personas.map((persona, index) => (
                      <div key={index} className="bg-gray-50 rounded-lg p-4 border border-gray-200 relative group">
                        <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button
                            onClick={() => handleRegeneratePersona(index, persona.name)}
                            disabled={regeneratingPersonaIndex === index}
                            className="p-1 text-gray-400 hover:text-[#3DAFA8] disabled:opacity-50"
                            title="Rigenera"
                          >
                            <RefreshCw size={14} className={regeneratingPersonaIndex === index ? "animate-spin" : ""} />
                          </button>
                          <button
                            onClick={() => handleDeletePersona(index, persona.name)}
                            disabled={deletingPersonaIndex === index}
                            className="p-1 text-gray-400 hover:text-red-500 disabled:opacity-50"
                            title="Elimina"
                          >
                            <Trash2 size={14} />
                          </button>
                        </div>
                        <h4 className="font-semibold text-[#2C3E50] text-lg mb-1 pr-12">{persona.name}</h4>
                        <p className="text-sm text-[#3DAFA8] font-medium mb-2">{persona.demographics?.role || ""}</p>
                        {persona.demographics && (
                          <p className="text-xs text-gray-500 mb-3">{`${persona.demographics?.age_range || ""} • ${persona.demographics?.location || ""}`}</p>
                        )}
                        {persona.pain_points && persona.pain_points.length > 0 && (
                          <div className="mb-3">
                            <span className="text-xs font-semibold text-gray-700 uppercase">Pain Points</span>
                            <ul className="mt-1 space-y-1">
                              {persona.pain_points.map((p, i) => (
                                <li key={i} className="text-xs text-gray-600 flex items-start gap-1">
                                  <span className="text-red-400 mt-0.5">•</span> {p}
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                        {persona.goals && persona.goals.length > 0 && (
                          <div className="mb-3">
                            <span className="text-xs font-semibold text-gray-700 uppercase">Obiettivi</span>
                            <ul className="mt-1 space-y-1">
                              {persona.goals.map((g, i) => (
                                <li key={i} className="text-xs text-gray-600 flex items-start gap-1">
                                  <span className="text-green-400 mt-0.5">•</span> {g}
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                        {persona.content_preferences && persona.content_preferences.length > 0 && (
                          <div className="mb-3">
                            <span className="text-xs font-semibold text-gray-700 uppercase">Contenuti Preferiti</span>
                            <ul className="mt-1 space-y-1">
                              {persona.content_preferences.map((c, i) => (
                                <li key={i} className="text-xs text-gray-600 flex items-start gap-1">
                                  <span className="text-blue-400 mt-0.5">•</span> {c}
                                </li>
                              ))}
                            </ul>
                          </div>
                        )}
                      </div>
                    ))}
                    {/* Add Persona Button */}
                    <button
                      onClick={handleAddPersona}
                      disabled={isAddingPersona}
                      className="bg-gray-50 rounded-lg p-4 border-2 border-dashed border-gray-300 hover:border-[#3DAFA8] hover:bg-gray-100 transition-colors flex flex-col items-center justify-center gap-2 min-h-[200px] disabled:opacity-50"
                    >
                      {isAddingPersona ? (
                        <Loader2 size={24} className="text-[#3DAFA8] animate-spin" />
                      ) : (
                        <Plus size={24} className="text-gray-400" />
                      )}
                      <span className="text-sm text-gray-500">{isAddingPersona ? "Aggiungendo..." : "Aggiungi Persona"}</span>
                    </button>
                  </div>
                </section>
              )}
              {/* Platforms */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Globe size={20} className="text-blue-500" />

                  Piattaforme e Frequenza
                </h3>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  {PLATFORMS.map((platform) => (
                    <div
                      key={platform.id}
                      onClick={() => togglePlatform(platform.id)}
                      className={`p-4 border-2 rounded-xl cursor-pointer transition-all ${
                        settingsForm.platforms.includes(platform.id)
                          ? 'border-[#3DAFA8] bg-[#3DAFA8]/5'
                          : 'border-gray-200 hover:border-gray-300'
                      }`}
                    >
                      <div className="flex items-center gap-2 mb-2">
                        <div className={`${platform.color} p-1.5 rounded text-white`}>
                          <platform.icon size={16} />
                        </div>
                        <span className="font-medium text-sm">{platform.name}</span>
                        {settingsForm.platforms.includes(platform.id) && (
                          <Check className="ml-auto text-[#3DAFA8]" size={16} />
                        )}
                      </div>
                      
                      {settingsForm.platforms.includes(platform.id) && (
                        <div className="space-y-2">
                          <div>
                            <label className="text-xs text-gray-500">Post/sett</label>
                            <input
                              type="number"
                              min="1"
                              max="14"
                              value={settingsForm.posts_per_week[platform.id] || 3}
                              onChange={(e) => {
                                e.stopPropagation();
                                updateSettingsForm('posts_per_week', {
                                  ...settingsForm.posts_per_week,
                                  [platform.id]: parseInt(e.target.value) || 1
                                });
                              }}
                              onClick={(e) => e.stopPropagation()}
                              className="w-full mt-1 px-2 py-1 border rounded text-sm"
                            />
                          </div>
                          
                          {/* Stato connessione social */}
                          <div onClick={(e) => e.stopPropagation()} className="pt-2 border-t border-gray-100">
                            {isConnected(platform.id) ? (
                              <div className="flex items-center justify-between">
                                <span className="text-xs text-green-600 flex items-center gap-1">
                                  <Check size={12} /> Connesso
                                </span>
                                <button
                                  onClick={() => disconnectSocial(getConnection(platform.id)?.id)}
                                  className="text-xs text-red-500 hover:text-red-700"
                                >
                                  Disconnetti
                                </button>
                              </div>
                            ) : (
                              <button
                                onClick={() => connectSocial(platform.id)}
                                className="w-full text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 py-1.5 px-2 rounded flex items-center justify-center gap-1 transition-colors"
                              >
                                <Link size={12} /> Connetti
                              </button>
                            )}
                          </div>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              </section>

              {/* Knowledge Base - Documenti Brand */}
              <section className="bg-gray-50 rounded-xl p-6">
                <BrandDocuments brandId={project?.brand_id} />
              </section>
              
              {/* Content Pillars */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Users size={20} className="text-purple-500" />
                  Content Pillars
                </h3>
                <div className="flex gap-2 mb-3">
                  <input
                    type="text"
                    value={newPillar}
                    onChange={(e) => setNewPillar(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && addPillar()}
                    placeholder="Aggiungi un pillar..."
                    className="flex-1 px-4 py-2 border rounded-lg"
                  />
                  <button onClick={addPillar} className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg">
                    <Plus size={20} />
                  </button>
                </div>
                <div className="flex flex-wrap gap-2">
                  {settingsForm.content_pillars.map((pillar, idx) => (
                    <span key={idx} className="flex items-center gap-1 bg-[#3DAFA8]/10 text-[#3DAFA8] px-3 py-1 rounded-full">
                      {pillar}
                      <X size={14} className="cursor-pointer hover:text-red-500" onClick={() => removePillar(idx)} />
                    </span>
                  ))}
                  {settingsForm.content_pillars.length === 0 && (
                    <span className="text-gray-400 text-sm">Nessun pillar definito</span>
                  )}
                </div>
                <div className="mt-3 bg-gray-50 p-3 rounded-lg">
                  <p className="text-xs text-gray-500 mb-2">Suggerimenti:</p>
                  <div className="flex flex-wrap gap-2">
                    {['Thought Leadership', 'Educational', 'Case Study', 'Behind the Scenes', 'Tips & Tricks'].map(sug => (
                      <button
                        key={sug}
                        onClick={() => !settingsForm.content_pillars.includes(sug) && updateSettingsForm('content_pillars', [...settingsForm.content_pillars, sug])}
                        className="text-xs px-2 py-1 border border-gray-300 rounded-full hover:border-[#3DAFA8] hover:text-[#3DAFA8]"
                      >
                        + {sug}
                      </button>
                    ))}
                  </div>
                </div>
              </section>
              
              {/* Reference URLs */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Link size={20} className="text-green-500" />
                  URL di Riferimento
                </h3>
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
                  {settingsForm.reference_urls.map((url, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-gray-50 px-3 py-2 rounded-lg">
                      <Link size={14} className="text-gray-400" />
                      <span className="flex-1 text-sm truncate">{url}</span>
                      <X size={14} className="cursor-pointer text-gray-400 hover:text-red-500" onClick={() => removeUrl(idx)} />
                    </div>
                  ))}
                  {settingsForm.reference_urls.length === 0 && (
                    <span className="text-gray-400 text-sm">Nessun URL di riferimento</span>
                  )}
                </div>
              </section>
              
              {/* Competitors */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Target size={20} className="text-orange-500" />
                  Competitor
                </h3>
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
                  {settingsForm.competitors.map((url, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-orange-50 px-3 py-2 rounded-lg">
                      <Globe size={14} className="text-[#E89548]" />
                      <span className="flex-1 text-sm truncate">{url}</span>
                      <X size={14} className="cursor-pointer text-gray-400 hover:text-red-500" onClick={() => removeCompetitor(idx)} />
                    </div>
                  ))}
                  {settingsForm.competitors.length === 0 && (
                    <span className="text-gray-400 text-sm">Nessun competitor</span>
                  )}
                </div>
              </section>
              
              {/* Custom Prompt */}
              <section>
                <h3 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
                  <Sparkles size={20} className="text-amber-500" />
                  Prompt Personalizzato (Avanzato)
                </h3>
                <textarea
                  value={settingsForm.custom_prompt}
                  onChange={(e) => updateSettingsForm('custom_prompt', e.target.value)}
                  rows={4}
                  placeholder="Istruzioni aggiuntive per l'AI..."
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] font-mono text-sm"
                />
              </section>
              
              {/* Action Buttons */}
              <div className="flex items-center justify-between pt-6 border-t">
                <button
                  onClick={regenerateCalendar}
                  disabled={isRegenerating}
                  className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg hover:opacity-90 disabled:opacity-50"
                >
                  {isRegenerating ? (
                    <><Loader2 className="animate-spin" size={18} /> Rigenerazione...</>
                  ) : (
                    <><RefreshCw size={18} /> Rigenera Calendario</>
                  )}
                </button>
                
                <button
                  onClick={saveSettings}
                  disabled={isSavingSettings}
                  className="flex items-center gap-2 px-6 py-3 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50"
                >
                  {isSavingSettings ? (
                    <><Loader2 className="animate-spin" size={18} /> Salvataggio...</>
                  ) : (
                    <><Save size={18} /> Salva Impostazioni</>
                  )}
                </button>
              </div>
            </div>
          </div>
        </main>
      )}

      {/* Add Post Modal */}
      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div className="p-6 border-b bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-t-xl">
              <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold">Aggiungi Post</h2>
                <button onClick={() => setShowAddModal(false)} className="p-1 hover:bg-white/20 rounded">
                  <X size={24} />
                </button>
              </div>
              
              {/* Tabs */}
              <div className="flex gap-2 mt-4">
                <button
                  onClick={() => setAddMode('manual')}
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                    addMode === 'manual' ? 'bg-white text-green-600' : 'bg-white/20 hover:bg-white/30'
                  }`}
                >
                  Post Manuale
                </button>
                <button
                  onClick={() => setAddMode('ai')}
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                    addMode === 'ai' ? 'bg-white text-green-600' : 'bg-white/20 hover:bg-white/30'
                  }`}
                >
                  <Sparkles size={14} className="inline mr-1" />
                  Campagna AI
                </button>
              </div>
            </div>
            
            <div className="p-6">
              {addMessage && (
                <div className={`mb-4 p-3 rounded-lg ${
                  addMessage.type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                }`}>
                  {addMessage.text}
                </div>
              )}
              
              {addMode === 'manual' ? (
                <div className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Piattaforma</label>
                      <select
                        value={newPost.platform}
                        onChange={(e) => setNewPost({...newPost, platform: e.target.value})}
                        className="w-full px-3 py-2 border rounded-lg"
                      >
                        {PLATFORMS.map(p => (
                          <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Pillar</label>
                      <input
                        type="text"
                        value={newPost.pillar}
                        onChange={(e) => setNewPost({...newPost, pillar: e.target.value})}
                        placeholder="es. Educational"
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    </div>
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Data</label>
                      <input
                        type="date"
                        value={newPost.scheduled_date}
                        onChange={(e) => setNewPost({...newPost, scheduled_date: e.target.value})}
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Ora</label>
                      <input
                        type="time"
                        value={newPost.scheduled_time}
                        onChange={(e) => setNewPost({...newPost, scheduled_time: e.target.value})}
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    </div>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Contenuto</label>
                    <textarea
                      value={newPost.content}
                      onChange={(e) => setNewPost({...newPost, content: e.target.value})}
                      rows={5}
                      placeholder="Scrivi il contenuto del post..."
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Hashtags</label>
                    <input
                      type="text"
                      value={newPost.hashtags}
                      onChange={(e) => setNewPost({...newPost, hashtags: e.target.value})}
                      placeholder="#hashtag1 #hashtag2"
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">CTA</label>
                    <input
                      type="text"
                      value={newPost.cta}
                      onChange={(e) => setNewPost({...newPost, cta: e.target.value})}
                      placeholder="es. Scopri di più sul nostro sito"
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Suggerimento Visual</label>
                    <input
                      type="text"
                      value={newPost.visual_suggestion}
                      onChange={(e) => setNewPost({...newPost, visual_suggestion: e.target.value})}
                      placeholder="es. Infografica con dati"
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <button
                    onClick={handleAddManualPost}
                    disabled={isAddingPosts || !newPost.content}
                    className="w-full py-3 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {isAddingPosts ? <Loader2 className="animate-spin" size={18} /> : <Plus size={18} />}
                    Crea Post
                  </button>
                </div>
              ) : (
                <div className="space-y-4">
                  {/* Piattaforme */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="block text-sm font-medium text-gray-700">Piattaforme</label>
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={aiCampaign.ai_decide_platforms}
                          onChange={(e) => setAiCampaign({...aiCampaign, ai_decide_platforms: e.target.checked})}
                          className="rounded text-[#3DAFA8]"
                        />
                        <span className="text-purple-600 font-medium">🤖 Decidi tu AI</span>
                      </label>
                    </div>
                    {!aiCampaign.ai_decide_platforms && (
                      <div className="flex flex-wrap gap-2">
                        {PLATFORMS.filter(p => project?.platforms?.includes(p.id)).map(p => (
                          <label key={p.id} className={`flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-all ${
                            aiCampaign.platforms?.includes(p.id) ? 'border-[#3DAFA8] bg-[#3DAFA8]/10' : 'border-gray-200 hover:border-gray-300'
                          }`}>
                            <input
                              type="checkbox"
                              checked={aiCampaign.platforms?.includes(p.id)}
                              onChange={(e) => {
                                const platforms = e.target.checked 
                                  ? [...(aiCampaign.platforms || []), p.id]
                                  : (aiCampaign.platforms || []).filter(id => id !== p.id);
                                setAiCampaign({...aiCampaign, platforms});
                              }}
                              className="rounded text-[#3DAFA8]"
                            />
                            <span className="text-sm">{p.name}</span>
                          </label>
                        ))}
                      </div>
                    )}
                    {aiCampaign.ai_decide_platforms && (
                      <p className="text-sm text-purple-600 bg-purple-50 px-3 py-2 rounded-lg">
                        L'AI sceglierà le piattaforme più adatte per questa campagna
                      </p>
                    )}
                  </div>

                  {/* Numero Post */}
                  <div>
                    <div className="flex items-center justify-between mb-2">
                      <label className="block text-sm font-medium text-gray-700">Numero Post</label>
                      <label className="flex items-center gap-2 text-sm">
                        <input
                          type="checkbox"
                          checked={aiCampaign.ai_decide_num_posts}
                          onChange={(e) => setAiCampaign({...aiCampaign, ai_decide_num_posts: e.target.checked})}
                          className="rounded text-[#3DAFA8]"
                        />
                        <span className="text-purple-600 font-medium">🤖 Decidi tu AI</span>
                      </label>
                    </div>
                    {!aiCampaign.ai_decide_num_posts && (
                      <input
                        type="number"
                        min="1"
                        max="50"
                        value={aiCampaign.num_posts}
                        onChange={(e) => setAiCampaign({...aiCampaign, num_posts: parseInt(e.target.value)})}
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    )}
                    {aiCampaign.ai_decide_num_posts && (
                      <p className="text-sm text-purple-600 bg-purple-50 px-3 py-2 rounded-lg">
                        L'AI deciderà il numero ottimale di post per il periodo
                      </p>
                    )}
                  </div>
                  
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Data Inizio</label>
                      <input
                        type="date"
                        value={aiCampaign.start_date}
                        onChange={(e) => setAiCampaign({...aiCampaign, start_date: e.target.value})}
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">Data Fine</label>
                      <input
                        type="date"
                        value={aiCampaign.end_date}
                        onChange={(e) => setAiCampaign({...aiCampaign, end_date: e.target.value})}
                        className="w-full px-3 py-2 border rounded-lg"
                      />
                    </div>
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Pillar/Tema (opzionale)</label>
                    <input
                      type="text"
                      value={aiCampaign.pillar}
                      onChange={(e) => setAiCampaign({...aiCampaign, pillar: e.target.value})}
                      placeholder="es. Thought Leadership"
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Brief Campagna *</label>
                    <textarea
                      value={aiCampaign.brief}
                      onChange={(e) => setAiCampaign({...aiCampaign, brief: e.target.value})}
                      rows={4}
                      placeholder="Descrivi cosa vuoi comunicare con questa campagna..."
                      className="w-full px-3 py-2 border rounded-lg"
                    />
                  </div>
                  
                  <div className="bg-[#3DAFA8]/10 p-4 rounded-lg">
                    <div className="flex items-center gap-2 text-[#3DAFA8] mb-2">
                      <Sparkles size={18} />
                      <span className="font-medium">Generazione AI</span>
                    </div>
                    <p className="text-sm text-gray-600">
                      {aiCampaign.ai_decide_platforms && aiCampaign.ai_decide_num_posts 
                        ? `L'AI analizzerà il brief e deciderà piattaforme e numero di post ottimali per il periodo ${aiCampaign.start_date} - ${aiCampaign.end_date}.`
                        : aiCampaign.ai_decide_platforms 
                          ? `L'AI genererà ${aiCampaign.num_posts} post sulle piattaforme più adatte, dal ${aiCampaign.start_date} al ${aiCampaign.end_date}.`
                          : aiCampaign.ai_decide_num_posts
                            ? `L'AI deciderà quanti post creare per ${aiCampaign.platforms?.map(id => PLATFORMS.find(p => p.id === id)?.name).join(', ') || 'le piattaforme selezionate'}, dal ${aiCampaign.start_date} al ${aiCampaign.end_date}.`
                            : `L'AI genererà ${aiCampaign.num_posts} post per ${aiCampaign.platforms?.map(id => PLATFORMS.find(p => p.id === id)?.name).join(', ') || 'le piattaforme selezionate'}, dal ${aiCampaign.start_date} al ${aiCampaign.end_date}.`
                      }
                    </p>
                  </div>
                  
                  <button
                    onClick={handleAddAICampaign}
                    disabled={isAddingPosts || !aiCampaign.brief}
                    className="w-full py-3 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:opacity-90 disabled:opacity-50 flex items-center justify-center gap-2"
                  >
                    {isAddingPosts ? <Loader2 className="animate-spin" size={18} /> : <Sparkles size={18} />}
                    Genera con AI
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Batch Replace Modal */}
      {showReplaceModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl max-w-lg w-full">
            <div className="p-6 border-b bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-t-xl">
              <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold">Sostituisci Post Selezionati</h2>
                <button onClick={() => setShowReplaceModal(false)} className="p-1 hover:bg-white/20 rounded">
                  <X size={24} />
                </button>
              </div>
              <p className="mt-2 text-white/80">{selectedPostIds.length} post verranno sostituiti</p>
            </div>
            
            <div className="p-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Brief per i nuovi post
                </label>
                <textarea
                  value={replaceBrief}
                  onChange={(e) => setReplaceBrief(e.target.value)}
                  rows={4}
                  placeholder="Descrivi come devono essere i nuovi post..."
                  className="w-full px-3 py-2 border rounded-lg"
                />
              </div>
              
              <div className="bg-amber-50 p-4 rounded-lg">
                <p className="text-sm text-amber-800">
                  <strong>Nota:</strong> I nuovi post manterranno le stesse date, orari e piattaforme dei post originali.
                </p>
              </div>
              
              <div className="flex gap-3">
                <button
                  onClick={() => setShowReplaceModal(false)}
                  className="flex-1 py-2 border rounded-lg hover:bg-gray-50"
                >
                  Annulla
                </button>
                <button
                  onClick={handleBatchReplace}
                  disabled={isReplacing || !replaceBrief.trim()}
                  className="flex-1 py-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white rounded-lg hover:opacity-90 disabled:opacity-50 flex items-center justify-center gap-2"
                >
                  {isReplacing ? <Loader2 className="animate-spin" size={18} /> : <RefreshCw size={18} />}
                  Sostituisci
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Post Edit Modal */}
      {editingPost && (
        <PostEditModal
          post={editingPost}
          isOpen={!!editingPost}
          onClose={() => setEditingPost(null)}
          onSave={handlePostSave}
        />
      )}
    </div>
  );
}

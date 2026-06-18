import { useState } from 'react';
import {
  X, Loader2, Sparkles, PenLine, Linkedin, Instagram, Facebook, MapPin, Megaphone,
} from 'lucide-react';
import { posts as postsApi, projects as projectsApi, campaigns as campaignsApi } from '../services/api';
import CampaignAttachmentsManager from './CampaignAttachmentsManager';
import McpServersManager from './McpServersManager';

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
  brandId,
  projectPlatforms = [],
  projectPillars = [],
  onPostCreated,
  onCampaignLaunched,
}) {
  const [mode, setMode] = useState('manual'); // 'manual' | 'ai' | 'campaign'
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
    brief: '',
  });

  // ── Campaign-only state ────────────────────────────────────────
  const [campaignName, setCampaignName]   = useState('');
  const [campaignPlatforms, setCampaignPlatforms] = useState(projectPlatforms);
  const [aiDecidePlatforms, setAiDecidePlatforms] = useState(false);
  const [postsCount, setPostsCount]       = useState(6);
  const [aiDecideCount, setAiDecideCount] = useState(false);
  const [campaignStart, setCampaignStart] = useState('');
  const [campaignEnd, setCampaignEnd]     = useState('');
  const [draftCampaignId, setDraftCampaignId] = useState(null);
  const [brandDocuments, setBrandDocuments] = useState([]); // [{id, inject_mode}]
  const [showKb, setShowKb]   = useState(false);
  const [showMcp, setShowMcp] = useState(false);

  if (!isOpen) return null;

  const dateStr = selectedDate ? selectedDate.toISOString().split('T')[0] : '';
  const dateDisplay = selectedDate ? selectedDate.toLocaleDateString('it-IT', {
    weekday: 'long', day: 'numeric', month: 'long',
  }) : '';

  const availablePlatforms = PLATFORMS.filter(p => projectPlatforms.includes(p.id));

  // Crea Draft campaign on-demand (quando l'utente espande KB/MCP)
  const ensureDraftCampaign = async () => {
    if (draftCampaignId) return draftCampaignId;
    const res = await projectsApi.campaigns.createDraft(projectId, {
      name: campaignName || 'Bozza campagna',
    });
    const id = res.data?.id;
    setDraftCampaignId(id);
    return id;
  };

  const handleSubmit = async () => {
    setIsLoading(true);
    setMessage(null);

    try {
      if (mode === 'manual') {
        const res = await postsApi.createManual({
          project_id: parseInt(projectId),
          platform: formData.platform,
          scheduled_date: dateStr,
          scheduled_time: formData.scheduled_time,
          content: formData.content,
          hashtags: formData.hashtags,
          pillar: formData.pillar,
          cta: formData.cta,
          visual_suggestion: formData.visual_suggestion,
        });
        onPostCreated?.(res.data);
        setMessage({ type: 'success', text: 'Post creato con successo!' });
      } else if (mode === 'ai') {
        const res = await postsApi.generateAI({
          project_id: parseInt(projectId),
          platforms: [formData.platform],
          start_date: dateStr,
          end_date: dateStr,
          num_posts: 1,
          ai_decide_num_posts: false,
          ai_decide_platforms: false,
          brief: formData.brief || `Post per ${dateDisplay}`,
          pillar: formData.pillar,
        });
        const newPosts = res.data?.posts || res.data || [];
        if (Array.isArray(newPosts)) {
          newPosts.forEach(post => onPostCreated?.(post));
        } else if (newPosts?.id) {
          onPostCreated?.(newPosts);
        }
        setMessage({ type: 'success', text: 'Post AI generato!' });
      } else {
        // mode === 'campaign'
        if (!campaignName.trim() || !formData.brief.trim()) {
          setMessage({ type: 'error', text: 'Inserisci nome campagna e brief' });
          setIsLoading(false);
          return;
        }

        const payload = {
          name:        campaignName.trim(),
          brief:       formData.brief.trim(),
          pillar:      formData.pillar || null,
          start_date:  campaignStart || null,
          end_date:    campaignEnd || null,
          platforms:   aiDecidePlatforms ? null : campaignPlatforms,
          posts_count: aiDecideCount     ? null : Number(postsCount),
        };

        if (draftCampaignId) {
          // Draft già creata (l'utente ha caricato allegati/MCP) → promote.
          // brand_documents come array nativo nel body JSON.
          const res = await projectsApi.campaigns.promote(projectId, draftCampaignId, {
            ...payload,
            brand_documents: brandDocuments,
          });
          onCampaignLaunched?.(res.data);
        } else {
          // Flow standard: tutto in una request (FormData).
          const fd = new FormData();
          Object.entries(payload).forEach(([k, v]) => {
            if (v === null || v === undefined) return;
            if (Array.isArray(v)) {
              v.forEach((item) => fd.append(`${k}[]`, item));
            } else {
              fd.append(k, v);
            }
          });
          // brand_documents è un array di oggetti → serializza come JSON,
          // il backend (ProjectCampaignController::store) lo decodifica.
          if (brandDocuments.length > 0) {
            fd.append('brand_documents', JSON.stringify(brandDocuments));
          }
          const res = await projectsApi.campaigns.create(projectId, fd);
          onCampaignLaunched?.(res.data);
        }

        setMessage({
          type: 'success',
          text: 'Campagna lanciata. Generazione in corso, i post compariranno nel calendario in 1-3 minuti.',
        });
      }

      setTimeout(() => {
        onClose();
        resetForm();
      }, 1500);
    } catch (error) {
      console.error('Error in QuickAddPostModal submit:', error);
      const apiMessage =
        error.response?.data?.errors?.[Object.keys(error.response?.data?.errors || {})[0]]?.[0]
        || error.response?.data?.message
        || error.response?.data?.detail
        || 'Errore nella creazione';
      setMessage({ type: 'error', text: apiMessage });
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
      brief: '',
    });
    setCampaignName('');
    setCampaignPlatforms(projectPlatforms);
    setAiDecidePlatforms(false);
    setPostsCount(6);
    setAiDecideCount(false);
    setCampaignStart('');
    setCampaignEnd('');
    setDraftCampaignId(null);
    setBrandDocuments([]);
    setShowKb(false);
    setShowMcp(false);
    setMessage(null);
    setMode('manual');
  };

  const handleClose = () => { resetForm(); onClose(); };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
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

          <div className="flex gap-2 mt-4 flex-wrap">
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
            <button
              onClick={() => setMode('campaign')}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                mode === 'campaign' ? 'bg-white text-[#3DAFA8]' : 'bg-white/20 hover:bg-white/30'
              }`}
            >
              <Megaphone size={16} /> Campagna AI
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

          {/* ── Modes Manual / Single AI ─────────────────────────── */}
          {(mode === 'manual' || mode === 'ai') && (
            <>
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
                          isSelected ? `${platform.color} text-white shadow-md` : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                        }`}
                      >
                        <Icon size={16} /> {platform.name}
                      </button>
                    );
                  })}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Orario</label>
                  <input type="time"
                    value={formData.scheduled_time}
                    onChange={(e) => setFormData(prev => ({ ...prev, scheduled_time: e.target.value }))}
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Pillar</label>
                  <select
                    value={formData.pillar}
                    onChange={(e) => setFormData(prev => ({ ...prev, pillar: e.target.value }))}
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  >
                    <option value="">Seleziona...</option>
                    {projectPillars.map(p => <option key={p} value={p}>{p}</option>)}
                  </select>
                </div>
              </div>

              {mode === 'manual' ? (
                <>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Contenuto</label>
                    <textarea value={formData.content}
                      onChange={(e) => setFormData(prev => ({ ...prev, content: e.target.value }))}
                      placeholder="Scrivi il contenuto del post..."
                      className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] h-28 resize-none"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Hashtag</label>
                    <input type="text" value={formData.hashtags}
                      onChange={(e) => setFormData(prev => ({ ...prev, hashtags: e.target.value }))}
                      placeholder="#marketing, #socialmedia"
                      className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Call to Action</label>
                    <input type="text" value={formData.cta}
                      onChange={(e) => setFormData(prev => ({ ...prev, cta: e.target.value }))}
                      placeholder="Scopri di più sul nostro sito"
                      className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                    />
                  </div>
                </>
              ) : (
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Brief per l'AI <span className="text-gray-400">(opzionale)</span>
                  </label>
                  <textarea value={formData.brief}
                    onChange={(e) => setFormData(prev => ({ ...prev, brief: e.target.value }))}
                    placeholder="Descrivi cosa vuoi comunicare..."
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] h-28 resize-none"
                  />
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Suggerimento Visual <span className="text-gray-400">(per generazione immagine)</span>
                </label>
                <input type="text" value={formData.visual_suggestion}
                  onChange={(e) => setFormData(prev => ({ ...prev, visual_suggestion: e.target.value }))}
                  placeholder="Es: Grafica con icone tech su sfondo blu"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                />
              </div>
            </>
          )}

          {/* ── Campaign mode ────────────────────────────────────── */}
          {mode === 'campaign' && (
            <>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nome campagna *</label>
                <input type="text" value={campaignName}
                  onChange={(e) => setCampaignName(e.target.value)}
                  placeholder="Es. Lancio prodotto Q3"
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Brief *</label>
                <textarea value={formData.brief}
                  onChange={(e) => setFormData(prev => ({ ...prev, brief: e.target.value }))}
                  placeholder="Cosa vuoi comunicare con questa campagna. L'AI userà questo testo + KB + MCP per generare i post."
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] h-28 resize-none"
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Pillar</label>
                  <select
                    value={formData.pillar}
                    onChange={(e) => setFormData(prev => ({ ...prev, pillar: e.target.value }))}
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  >
                    <option value="">— Tutti —</option>
                    {projectPillars.map(p => <option key={p} value={p}>{p}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Numero post</label>
                  <div className="flex items-center gap-2">
                    <input type="number" min={1} max={50} value={postsCount}
                      disabled={aiDecideCount}
                      onChange={(e) => setPostsCount(e.target.value)}
                      className="flex-1 px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] disabled:bg-gray-100"
                    />
                    <label className="flex items-center gap-1 text-xs whitespace-nowrap">
                      <input type="checkbox" checked={aiDecideCount}
                        onChange={(e) => setAiDecideCount(e.target.checked)}
                      />
                      AI decide
                    </label>
                  </div>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Data inizio</label>
                  <input type="date" value={campaignStart}
                    onChange={(e) => setCampaignStart(e.target.value)}
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  />
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Data fine</label>
                  <input type="date" value={campaignEnd}
                    onChange={(e) => setCampaignEnd(e.target.value)}
                    className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">Piattaforme</label>
                <label className="flex items-center gap-2 text-sm mb-2">
                  <input type="checkbox" checked={aiDecidePlatforms}
                    onChange={(e) => setAiDecidePlatforms(e.target.checked)}
                  />
                  AI decide (usa platforms del project)
                </label>
                {!aiDecidePlatforms && (
                  <div className="flex flex-wrap gap-2">
                    {availablePlatforms.map(platform => {
                      const Icon = platform.icon;
                      const isSelected = campaignPlatforms.includes(platform.id);
                      return (
                        <button
                          key={platform.id}
                          type="button"
                          onClick={() => setCampaignPlatforms(prev => isSelected
                            ? prev.filter(p => p !== platform.id)
                            : [...prev, platform.id]
                          )}
                          className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                            isSelected ? `${platform.color} text-white shadow-md` : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                          }`}
                        >
                          <Icon size={16} /> {platform.name}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>

              {/* Collapsible: KB attachments */}
              <details
                open={showKb}
                onToggle={async (e) => {
                  setShowKb(e.target.open);
                  if (e.target.open) await ensureDraftCampaign();
                }}
                className="border border-gray-200 rounded-lg p-3"
              >
                <summary className="cursor-pointer font-medium text-sm text-[#2C3E50]">
                  📎 Documenti Knowledge Base <span className="text-gray-400 font-normal">(opzionale)</span>
                </summary>
                {draftCampaignId && (
                  <div className="mt-3">
                    <CampaignAttachmentsManager
                      campaignId={draftCampaignId}
                      brandId={brandId}
                      onBrandDocumentsChange={setBrandDocuments}
                    />
                  </div>
                )}
              </details>

              {/* Collapsible: MCP servers */}
              <details
                open={showMcp}
                onToggle={async (e) => {
                  setShowMcp(e.target.open);
                  if (e.target.open) await ensureDraftCampaign();
                }}
                className="border border-gray-200 rounded-lg p-3"
              >
                <summary className="cursor-pointer font-medium text-sm text-[#2C3E50]">
                  🔌 Connettori MCP <span className="text-gray-400 font-normal">(opzionale)</span>
                </summary>
                {draftCampaignId && (
                  <div className="mt-3">
                    <McpServersManager
                      scope="campaign"
                      listFn={() => campaignsApi.mcpServers.list(draftCampaignId)}
                      createFn={(payload) => campaignsApi.mcpServers.create(draftCampaignId, payload)}
                      deleteFn={(mcpId) => campaignsApi.mcpServers.delete(draftCampaignId, mcpId)}
                      showOverrideToggle={true}
                    />
                  </div>
                )}
              </details>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="p-5 border-t bg-gray-50 rounded-b-xl flex justify-end gap-3">
          <button onClick={handleClose} className="px-4 py-2 text-gray-600 hover:bg-gray-200 rounded-lg transition">
            Annulla
          </button>
          <button
            onClick={handleSubmit}
            disabled={isLoading
              || (mode === 'manual' && !formData.content.trim())
              || (mode === 'campaign' && (!campaignName.trim() || !formData.brief.trim()))
            }
            className="flex items-center gap-2 px-5 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 disabled:cursor-not-allowed transition"
          >
            {isLoading ? (
              <><Loader2 className="animate-spin" size={18} /> {mode === 'campaign' ? 'Lancio campagna...' : 'Creazione...'}</>
            ) : mode === 'manual' ? (
              <><PenLine size={18} /> Crea Post</>
            ) : mode === 'ai' ? (
              <><Sparkles size={18} /> Genera Post</>
            ) : (
              <><Megaphone size={18} /> Lancia Campagna AI</>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useDataStore } from '../store/dataStore';
import { brands, projects as projectsApi, social as socialApi } from '../services/api';
import {
  Plus, Calendar, Sparkles, Loader2, Trash2, Mic, Building2,
  Linkedin, Instagram, Facebook, MapPin, Link2, CheckCircle, XCircle,
  ExternalLink, Globe, Palette, Users, Layers
} from 'lucide-react';
import EditionBadge from '../components/EditionBadge';
import AddEditionModal from '../components/AddEditionModal';

const PLATFORMS = [
  { id: 'linkedin', name: 'LinkedIn', icon: Linkedin, color: 'bg-[#0077b5]' },
  { id: 'instagram', name: 'Instagram', icon: Instagram, color: 'bg-gradient-to-r from-[#f09433] via-[#dc2743] to-[#bc1888]' },
  { id: 'facebook', name: 'Facebook', icon: Facebook, color: 'bg-[#1877f2]' },
  { id: 'google_business', name: 'Google Business', icon: MapPin, color: 'bg-[#34a853]' },
];

export default function BrandDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { projects, fetchProjects, isLoading } = useDataStore();
  const [brand, setBrand] = useState(null);
  const [connections, setConnections] = useState([]);
  const [loadingConnections, setLoadingConnections] = useState(true);
  const [editionModalProject, setEditionModalProject] = useState(null);

  useEffect(() => {
    loadBrand();
    fetchProjects(id);
    loadConnections();
  }, [id]);

  const loadBrand = async () => {
    try {
      const res = await brands.get(id);
      setBrand(res.data);
    } catch (err) {
      console.error('Error loading brand:', err);
    }
  };

  const loadConnections = async () => {
    try {
      const res = await socialApi.getConnections(id);
      setConnections(res.data || []);
    } catch (err) {
      console.error('Error loading connections:', err);
    } finally {
      setLoadingConnections(false);
    }
  };

  const handleDeleteProject = async (e, projectId) => {
    e.stopPropagation();
    if (!window.confirm('Sei sicuro di voler eliminare questo calendario?')) return;
    
    try {
      await projectsApi.delete(projectId);
      fetchProjects(id);
    } catch (err) {
      alert('Errore durante l\'eliminazione');
    }
  };

  const handleConnectSocial = async (platform) => {
    try {
      const token = localStorage.getItem('token');
      window.location.href = `/api/social/authorize/${platform}?brand_id=${id}&token=${token}`;
    } catch (err) {
      alert('Errore nella connessione');
    }
  };

  const handleDisconnect = async (connectionId) => {
    if (!window.confirm('Disconnettere questo account?')) return;
    try {
      await socialApi.disconnect(connectionId);
      loadConnections();
    } catch (err) {
      alert('Errore nella disconnessione');
    }
  };

  const getConnection = (platformId) => {
    return connections.find(c => c.platform === platformId);
  };

  if (!brand) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      {/* Brand Header Card */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <div className="flex items-start justify-between">
          <div className="flex items-center gap-4">
            <div className="w-16 h-16 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-2xl flex items-center justify-center">
              <Building2 className="text-white" size={32} />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-[#2C3E50]">{brand.name}</h1>
              <div className="flex items-center gap-3 mt-1 text-sm text-gray-500">
                {brand.sector && (
                  <span className="flex items-center gap-1">
                    <Users size={14} /> {brand.sector}
                  </span>
                )}
                {brand.tone_of_voice && (
                  <span className="flex items-center gap-1">
                    <Palette size={14} /> {brand.tone_of_voice}
                  </span>
                )}
                {brand.website && (
                  <a 
                    href={brand.website} 
                    target="_blank" 
                    rel="noopener noreferrer"
                    className="flex items-center gap-1 text-[#3DAFA8] hover:underline"
                  >
                    <Globe size={14} /> Website
                  </a>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Social Connections */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h2 className="text-lg font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
          <Link2 className="text-[#3DAFA8]" /> Connessioni Social
        </h2>
        
        {loadingConnections ? (
          <div className="flex justify-center py-4">
            <Loader2 className="animate-spin text-gray-400" size={24} />
          </div>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            {PLATFORMS.map(platform => {
              const connection = getConnection(platform.id);
              const Icon = platform.icon;
              
              return (
                <div
                  key={platform.id}
                  className={`p-4 rounded-xl border-2 transition-all ${
                    connection 
                      ? 'border-green-200 bg-green-50' 
                      : 'border-gray-200 bg-gray-50'
                  }`}
                >
                  <div className="flex items-center justify-between mb-3">
                    <div className={`w-10 h-10 ${platform.color} rounded-lg flex items-center justify-center`}>
                      <Icon className="text-white" size={20} />
                    </div>
                    {connection ? (
                      <CheckCircle className="text-green-500" size={20} />
                    ) : (
                      <XCircle className="text-gray-300" size={20} />
                    )}
                  </div>
                  
                  <p className="font-medium text-sm mb-1">{platform.name}</p>
                  
                  {connection ? (
                    <>
                      <p className="text-xs text-green-600 truncate mb-2">
                        {connection.external_account_name || 'Connesso'}
                      </p>
                      <button
                        onClick={() => handleDisconnect(connection.id)}
                        className="text-xs text-red-500 hover:text-red-700"
                      >
                        Disconnetti
                      </button>
                    </>
                  ) : (
                    <button
                      onClick={() => handleConnectSocial(platform.id)}
                      className="text-xs text-[#3DAFA8] hover:text-[#2C3E50] font-medium"
                    >
                      Connetti →
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Calendars Section */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-lg font-semibold text-[#2C3E50] flex items-center gap-2">
            <Calendar className="text-[#3DAFA8]" /> Calendari Editoriali
          </h2>
          <div className="flex gap-3">
            <button
              onClick={() => navigate(`/brand/${id}/voice-interview`)}
              className="flex items-center gap-2 bg-[#E89548] text-white px-4 py-2 rounded-lg hover:bg-[#d4823c] transition-colors"
            >
              <Mic size={18} /> Intervista AI
            </button>
            <button
              onClick={() => navigate(`/brand/${id}/new-project`)}
              className="flex items-center gap-2 bg-[#3DAFA8] text-white px-4 py-2 rounded-lg hover:bg-[#2C3E50] transition-colors"
            >
              <Sparkles size={18} /> Wizard Manuale
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="flex justify-center py-12">
            <Loader2 className="animate-spin text-gray-400" size={32} />
          </div>
        ) : projects.length === 0 ? (
          <div className="text-center py-12 bg-gray-50 rounded-xl">
            <Calendar size={48} className="mx-auto text-gray-300 mb-4" />
            <h3 className="text-lg font-medium text-gray-700 mb-2">Nessun calendario</h3>
            <p className="text-gray-500 mb-6">Crea il tuo primo calendario editoriale</p>
            <div className="flex gap-4 justify-center">
              <button
                onClick={() => navigate(`/brand/${id}/voice-interview`)}
                className="inline-flex items-center gap-2 bg-[#E89548] text-white px-6 py-3 rounded-lg hover:bg-[#d4823c]"
              >
                <Mic size={20} /> Intervista AI
              </button>
              <button
                onClick={() => navigate(`/brand/${id}/new-project`)}
                className="inline-flex items-center gap-2 bg-[#3DAFA8] text-white px-6 py-3 rounded-lg hover:bg-[#2C3E50]"
              >
                <Plus size={20} /> Wizard Manuale
              </button>
            </div>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {projects.map(project => (
              <div key={project.id} className="bg-gray-50 rounded-xl group relative">
                <div
                  onClick={() => navigate(`/project/${project.id}`)}
                  className="p-5 cursor-pointer hover:bg-gray-100 transition-all rounded-xl"
                >
                  <button
                    onClick={(e) => handleDeleteProject(e, project.id)}
                    className="absolute top-3 right-3 p-2 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <Trash2 size={16} />
                  </button>

                  <div className="flex items-center gap-3 mb-3">
                    <div className="w-10 h-10 bg-[#3DAFA8]/10 rounded-lg flex items-center justify-center">
                      <Calendar className="text-[#3DAFA8]" size={20} />
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <h3 className="font-semibold text-[#2C3E50] truncate">{project.name}</h3>
                        <EditionBadge editionNumber={project.edition_number} />
                      </div>
                      <p className="text-xs text-gray-400">
                        {new Date(project.created_at).toLocaleDateString('it-IT')}
                      </p>
                    </div>
                  </div>

                  <p className="text-sm text-gray-500 mb-3 line-clamp-2">
                    {project.description || `${project.start_date} — ${project.end_date}`}
                  </p>

                  <div className="flex items-center justify-between pt-3 border-t border-gray-200">
                    <span className="text-sm text-gray-500">
                      {project.posts_count || 0} post
                    </span>
                    <ExternalLink className="text-gray-400 group-hover:text-[#3DAFA8]" size={16} />
                  </div>
                </div>

                {/* Edition sub-list */}
                {project.editions?.length > 0 && (
                  <div className="px-5 pb-3 space-y-1">
                    {project.editions.map(ed => (
                      <div
                        key={ed.id}
                        onClick={() => navigate(`/project/${ed.id}`)}
                        className="flex items-center justify-between py-1.5 px-3 rounded-lg hover:bg-gray-200 cursor-pointer text-sm"
                      >
                        <span className="flex items-center gap-2 text-gray-600">
                          <Layers size={12} className="text-[#3DAFA8]" />
                          Ed. {ed.edition_number} — {ed.name}
                        </span>
                        <span className="text-xs text-gray-400">{ed.posts_count || 0} post</span>
                      </div>
                    ))}
                  </div>
                )}

                {/* + Nuova Edizione button */}
                <div className="px-5 pb-4">
                  <button
                    onClick={(e) => { e.stopPropagation(); setEditionModalProject(project); }}
                    className="w-full flex items-center justify-center gap-1.5 py-1.5 text-xs text-[#3DAFA8] hover:text-[#2C3E50] border border-dashed border-[#3DAFA8]/30 hover:border-[#3DAFA8] rounded-lg transition-colors"
                  >
                    <Plus size={14} /> Nuova Edizione
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Add Edition Modal */}
      {editionModalProject && (
        <AddEditionModal
          project={editionModalProject}
          onClose={() => setEditionModalProject(null)}
          onCreated={(newEdition) => {
            setEditionModalProject(null);
            navigate(`/project/${newEdition.id}`);
          }}
        />
      )}
    </div>
  );
}

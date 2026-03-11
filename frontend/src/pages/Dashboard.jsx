import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Building2, Calendar, Plus, TrendingUp, Clock, CheckCircle, 
  Image, FileText, Loader2, ArrowRight, Sparkles
} from 'lucide-react';
import { brands as brandsApi } from '../services/api';

export default function Dashboard() {
  const navigate = useNavigate();
  const [brands, setBrands] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    totalBrands: 0,
    totalCalendars: 0,
    totalPosts: 0,
    scheduledPosts: 0,
    publishedPosts: 0,
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const res = await brandsApi.list();
      setBrands(res.data);
      
      // Calculate stats
      let totalCalendars = 0;
      let totalPosts = 0;
      
      res.data.forEach(brand => {
        totalCalendars += brand.projects_count || 0;
        totalPosts += brand.posts_count || 0;
      });

      setStats({
        totalBrands: res.data.length,
        totalCalendars,
        totalPosts,
        scheduledPosts: Math.floor(totalPosts * 0.3), // Placeholder
        publishedPosts: Math.floor(totalPosts * 0.5), // Placeholder
      });
    } catch (err) {
      console.error('Error loading dashboard:', err);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto space-y-6">
      {/* Welcome Banner */}
      <div className="bg-gradient-to-r from-[#3DAFA8] to-[#2C3E50] rounded-2xl p-6 text-white">
        <h2 className="text-2xl font-bold mb-2">Benvenuto su Noscite Calendar! 👋</h2>
        <p className="text-white/80">Gestisci i tuoi calendari editoriali con l'aiuto dell'AI</p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="bg-white rounded-xl p-5 shadow-sm border-l-4 border-[#3DAFA8]">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Brand</p>
              <p className="text-3xl font-bold text-[#2C3E50]">{stats.totalBrands}</p>
            </div>
            <div className="w-12 h-12 bg-[#3DAFA8]/10 rounded-xl flex items-center justify-center">
              <Building2 className="text-[#3DAFA8]" size={24} />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-5 shadow-sm border-l-4 border-[#E89548]">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Calendari</p>
              <p className="text-3xl font-bold text-[#2C3E50]">{stats.totalCalendars}</p>
            </div>
            <div className="w-12 h-12 bg-[#E89548]/10 rounded-xl flex items-center justify-center">
              <Calendar className="text-[#E89548]" size={24} />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-5 shadow-sm border-l-4 border-blue-500">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Post Totali</p>
              <p className="text-3xl font-bold text-[#2C3E50]">{stats.totalPosts}</p>
            </div>
            <div className="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center">
              <FileText className="text-blue-500" size={24} />
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl p-5 shadow-sm border-l-4 border-green-500">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Pubblicati</p>
              <p className="text-3xl font-bold text-[#2C3E50]">{stats.publishedPosts}</p>
            </div>
            <div className="w-12 h-12 bg-green-500/10 rounded-xl flex items-center justify-center">
              <CheckCircle className="text-green-500" size={24} />
            </div>
          </div>
        </div>
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Brands */}
        <div className="lg:col-span-2 bg-white rounded-xl shadow-sm p-6">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold text-lg text-[#2C3E50]">I tuoi Brand</h3>
            <button
              onClick={() => navigate('/brands')}
              className="text-[#3DAFA8] hover:text-[#2C3E50] text-sm font-medium flex items-center gap-1"
            >
              Vedi tutti <ArrowRight size={16} />
            </button>
          </div>

          {brands.length === 0 ? (
            <div className="text-center py-8">
              <Building2 size={48} className="mx-auto text-gray-300 mb-4" />
              <p className="text-gray-500 mb-4">Nessun brand ancora</p>
              <button
                onClick={() => navigate('/brands')}
                className="inline-flex items-center gap-2 bg-[#3DAFA8] text-white px-4 py-2 rounded-lg hover:bg-[#2C3E50]"
              >
                <Plus size={18} /> Crea il primo brand
              </button>
            </div>
          ) : (
            <div className="space-y-3">
              {brands.slice(0, 4).map(brand => (
                <div
                  key={brand.id}
                  onClick={() => navigate(`/brand/${brand.id}`)}
                  className="flex items-center justify-between p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition-all group"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-lg flex items-center justify-center">
                      <Building2 className="text-white" size={20} />
                    </div>
                    <div>
                      <p className="font-medium text-[#2C3E50]">{brand.name}</p>
                      <p className="text-sm text-gray-500">{brand.sector || 'Nessun settore'}</p>
                    </div>
                  </div>
                  <div className="flex items-center gap-4">
                    <div className="text-right">
                      <p className="text-sm font-medium">{brand.projects_count || 0}</p>
                      <p className="text-xs text-gray-400">calendari</p>
                    </div>
                    <ArrowRight className="text-gray-400 group-hover:text-[#3DAFA8] transition-colors" size={20} />
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Quick Actions */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-lg text-[#2C3E50] mb-4">Azioni Rapide</h3>
          <div className="space-y-3">
            <button
              onClick={() => navigate('/brands')}
              className="w-full flex items-center gap-3 p-4 bg-gradient-to-r from-[#3DAFA8] to-[#2C3E50] text-white rounded-xl hover:shadow-lg transition-all"
            >
              <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                <Plus size={20} />
              </div>
              <div className="text-left">
                <p className="font-medium">Nuovo Brand</p>
                <p className="text-sm text-white/70">Aggiungi un nuovo brand</p>
              </div>
            </button>

            <button
              onClick={() => navigate('/ai-assistant')}
              className="w-full flex items-center gap-3 p-4 bg-gradient-to-r from-[#E89548] to-[#d4823c] text-white rounded-xl hover:shadow-lg transition-all"
            >
              <div className="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                <Sparkles size={20} />
              </div>
              <div className="text-left">
                <p className="font-medium">AI Assistant</p>
                <p className="text-sm text-white/70">Crea con intervista vocale</p>
              </div>
            </button>

            <button
              onClick={() => navigate('/documents')}
              className="w-full flex items-center gap-3 p-4 bg-gray-100 text-[#2C3E50] rounded-xl hover:bg-gray-200 transition-all"
            >
              <div className="w-10 h-10 bg-white rounded-lg flex items-center justify-center shadow-sm">
                <FileText size={20} className="text-[#3DAFA8]" />
              </div>
              <div className="text-left">
                <p className="font-medium">Documenti</p>
                <p className="text-sm text-gray-500">Carica knowledge base</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      {/* Activity / Tips Section */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h3 className="font-semibold text-lg text-[#2C3E50] mb-4">💡 Suggerimenti</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div className="p-4 bg-blue-50 rounded-xl">
            <p className="font-medium text-blue-800 mb-1">Carica Documenti</p>
            <p className="text-sm text-blue-600">Migliora la qualità dei contenuti AI caricando documenti sul tuo brand</p>
          </div>
          <div className="p-4 bg-green-50 rounded-xl">
            <p className="font-medium text-green-800 mb-1">Collega i Social</p>
            <p className="text-sm text-green-600">Pubblica automaticamente collegando i tuoi account social</p>
          </div>
          <div className="p-4 bg-purple-50 rounded-xl">
            <p className="font-medium text-purple-800 mb-1">Usa l'AI Assistant</p>
            <p className="text-sm text-purple-600">Crea calendari con un'intervista vocale guidata dall'AI</p>
          </div>
        </div>
      </div>
    </div>
  );
}

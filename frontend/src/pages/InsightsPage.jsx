import { useState, useEffect } from 'react';
import { BarChart3, TrendingUp, Users, Eye, Heart, MessageCircle, Share, RefreshCw, Calendar } from 'lucide-react';
import api from '../services/api';

export default function InsightsPage() {
  const [brands, setBrands] = useState([]);
  const [selectedBrand, setSelectedBrand] = useState(null);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(false);
  const [period, setPeriod] = useState(30);

  useEffect(() => {
    loadBrands();
  }, []);

  useEffect(() => {
    if (selectedBrand) {
      loadStats();
    }
  }, [selectedBrand, period]);

  const loadBrands = async () => {
    try {
      const res = await api.get('/brands/');
      setBrands(res.data);
      if (res.data.length > 0) {
        setSelectedBrand(res.data[0].id);
      }
    } catch (err) {
      console.error('Errore caricamento brand:', err);
    }
  };

  const loadStats = async () => {
    setLoading(true);
    try {
      const res = await api.get(`/social/stats/brand/${selectedBrand}?days=${period}`);
      setStats(res.data);
    } catch (err) {
      console.error('Errore caricamento stats:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchFromPlatforms = async () => {
    setFetching(true);
    try {
      await api.post(`/social/stats/fetch/${selectedBrand}`);
      await loadStats();
    } catch (err) {
      console.error('Errore fetch stats:', err);
    } finally {
      setFetching(false);
    }
  };

  const StatCard = ({ icon: Icon, label, value, color = "blue" }) => (
    <div className="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
      <div className="flex items-center gap-3 mb-2">
        <div className={`p-2 rounded-lg bg-${color}-50`}>
          <Icon className={`w-5 h-5 text-${color}-600`} />
        </div>
        <span className="text-gray-500 text-sm">{label}</span>
      </div>
      <p className="text-2xl font-bold text-gray-900">
        {typeof value === 'number' ? value.toLocaleString('it-IT') : value || '-'}
      </p>
    </div>
  );

  const PlatformCard = ({ platform }) => {
    const platformColors = {
      facebook: 'blue',
      instagram: 'pink',
      linkedin: 'sky'
    };
    const color = platformColors[platform.platform] || 'gray';

    return (
      <div className="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center gap-2">
            <div className={`w-3 h-3 rounded-full bg-${color}-500`}></div>
            <h3 className="font-semibold capitalize">{platform.platform}</h3>
          </div>
          <span className="text-sm text-gray-500">{platform.account_name}</span>
        </div>
        
        <div className="grid grid-cols-2 gap-4">
          <div>
            <p className="text-xs text-gray-500">Impressioni</p>
            <p className="text-lg font-semibold">{platform.impressions?.toLocaleString('it-IT') || 0}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Copertura</p>
            <p className="text-lg font-semibold">{platform.reach?.toLocaleString('it-IT') || 0}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Engagement</p>
            <p className="text-lg font-semibold">{platform.engagement?.toLocaleString('it-IT') || 0}</p>
          </div>
          <div>
            <p className="text-xs text-gray-500">Follower</p>
            <p className="text-lg font-semibold">{platform.followers_count?.toLocaleString('it-IT') || '-'}</p>
          </div>
        </div>

        <div className="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-2 text-center">
          <div>
            <Heart className="w-4 h-4 mx-auto text-red-400 mb-1" />
            <p className="text-sm font-medium">{platform.likes || 0}</p>
          </div>
          <div>
            <MessageCircle className="w-4 h-4 mx-auto text-blue-400 mb-1" />
            <p className="text-sm font-medium">{platform.comments || 0}</p>
          </div>
          <div>
            <Share className="w-4 h-4 mx-auto text-green-400 mb-1" />
            <p className="text-sm font-medium">{platform.shares || 0}</p>
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="p-6 max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Insights</h1>
          <p className="text-gray-500">Analisi performance social media</p>
        </div>
        
        <div className="flex items-center gap-3">
          {/* Selettore Brand */}
          <select
            value={selectedBrand || ''}
            onChange={(e) => setSelectedBrand(Number(e.target.value))}
            className="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
          >
            {brands.map(brand => (
              <option key={brand.id} value={brand.id}>{brand.name}</option>
            ))}
          </select>

          {/* Selettore Periodo */}
          <select
            value={period}
            onChange={(e) => setPeriod(Number(e.target.value))}
            className="px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"
          >
            <option value={7}>Ultimi 7 giorni</option>
            <option value={30}>Ultimi 30 giorni</option>
            <option value={90}>Ultimi 90 giorni</option>
          </select>

          {/* Refresh */}
          <button
            onClick={fetchFromPlatforms}
            disabled={fetching || !selectedBrand}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
          >
            <RefreshCw className={`w-4 h-4 ${fetching ? 'animate-spin' : ''}`} />
            Aggiorna
          </button>
        </div>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        </div>
      ) : stats ? (
        <>
          {/* Overview Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard 
              icon={Eye} 
              label="Impressioni Totali" 
              value={stats.platforms?.reduce((sum, p) => sum + (p.impressions || 0), 0)}
              color="blue"
            />
            <StatCard 
              icon={Users} 
              label="Copertura Totale" 
              value={stats.platforms?.reduce((sum, p) => sum + (p.reach || 0), 0)}
              color="green"
            />
            <StatCard 
              icon={TrendingUp} 
              label="Engagement Rate" 
              value={`${stats.engagement_rate || 0}%`}
              color="purple"
            />
            <StatCard 
              icon={Calendar} 
              label="Post Pubblicati" 
              value={`${stats.published_posts || 0} / ${stats.total_posts || 0}`}
              color="orange"
            />
          </div>

          {/* Platform Cards */}
          <h2 className="text-lg font-semibold text-gray-900 mb-4">Performance per Piattaforma</h2>
          
          {stats.platforms && stats.platforms.length > 0 ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {stats.platforms.map((platform, idx) => (
                <PlatformCard key={idx} platform={platform} />
              ))}
            </div>
          ) : (
            <div className="bg-gray-50 rounded-xl p-8 text-center">
              <BarChart3 className="w-12 h-12 text-gray-300 mx-auto mb-3" />
              <p className="text-gray-500">Nessuna connessione social attiva</p>
              <p className="text-sm text-gray-400 mt-1">
                Collega i tuoi account dalla sezione Social
              </p>
            </div>
          )}

          {/* Info periodo */}
          <div className="mt-6 text-sm text-gray-400 text-center">
            Dati dal {new Date(stats.period_start).toLocaleDateString('it-IT')} al {new Date(stats.period_end).toLocaleDateString('it-IT')}
          </div>
        </>
      ) : (
        <div className="bg-gray-50 rounded-xl p-8 text-center">
          <BarChart3 className="w-12 h-12 text-gray-300 mx-auto mb-3" />
          <p className="text-gray-500">Seleziona un brand per vedere le statistiche</p>
        </div>
      )}
    </div>
  );
}

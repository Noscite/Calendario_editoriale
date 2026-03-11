import { useState, useEffect } from 'react';
import { Share2, Linkedin, Instagram, Facebook, MapPin, Loader2, CheckCircle, XCircle } from 'lucide-react';
import { brands as brandsApi, social as socialApi } from '../services/api';

const PLATFORMS = [
  { id: 'linkedin', name: 'LinkedIn', icon: Linkedin, color: 'bg-[#0077b5]' },
  { id: 'instagram', name: 'Instagram', icon: Instagram, color: 'bg-gradient-to-r from-[#f09433] via-[#dc2743] to-[#bc1888]' },
  { id: 'facebook', name: 'Facebook', icon: Facebook, color: 'bg-[#1877f2]' },
  { id: 'google_business', name: 'Google Business', icon: MapPin, color: 'bg-[#34a853]' },
];

export default function SocialPage() {
  const [brands, setBrands] = useState([]);
  const [connections, setConnections] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const brandsRes = await brandsApi.list();
      setBrands(brandsRes.data);
      
      // Load connections for each brand
      const connectionsMap = {};
      for (const brand of brandsRes.data) {
        try {
          const connRes = await socialApi.getConnections(brand.id);
          connectionsMap[brand.id] = connRes.data;
        } catch (err) {
          connectionsMap[brand.id] = [];
        }
      }
      setConnections(connectionsMap);
    } catch (err) {
      console.error('Error loading data:', err);
    } finally {
      setLoading(false);
    }
  };

  const getConnectionStatus = (brandId, platform) => {
    const brandConnections = connections[brandId] || [];
    return brandConnections.find(c => c.platform === platform);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto">
      <div className="mb-6">
        <p className="text-gray-500">Gestisci le connessioni social dei tuoi brand</p>
      </div>

      {brands.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center shadow-sm">
          <Share2 size={48} className="mx-auto text-gray-300 mb-4" />
          <h3 className="text-lg font-medium text-gray-700 mb-2">Nessun brand</h3>
          <p className="text-gray-500">Crea prima un brand per collegare i social</p>
        </div>
      ) : (
        <div className="space-y-6">
          {brands.map(brand => (
            <div key={brand.id} className="bg-white rounded-xl shadow-sm p-6">
              <h3 className="font-semibold text-lg text-[#2C3E50] mb-4">{brand.name}</h3>
              
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                {PLATFORMS.map(platform => {
                  const connection = getConnectionStatus(brand.id, platform.id);
                  const Icon = platform.icon;
                  
                  return (
                    <div
                      key={platform.id}
                      className={`p-4 rounded-xl border-2 transition-all ${
                        connection 
                          ? 'border-green-200 bg-green-50' 
                          : 'border-gray-200 bg-gray-50 hover:border-[#3DAFA8]'
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
                      
                      <p className="font-medium text-sm">{platform.name}</p>
                      {connection ? (
                        <p className="text-xs text-green-600 mt-1">
                          {connection.external_account_name || 'Connesso'}
                        </p>
                      ) : (
                        <p className="text-xs text-gray-400 mt-1">Non connesso</p>
                      )}
                    </div>
                  );
                })}
              </div>
              
              <p className="text-sm text-gray-500 mt-4">
                Vai alla pagina del brand per gestire le connessioni
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

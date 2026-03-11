import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Linkedin, Building2, User, Loader2, AlertCircle } from 'lucide-react';
import api from '../services/api';

export default function SelectLinkedInOrg() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token');
  
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState(null);
  const [selectedType, setSelectedType] = useState(null); // 'profile' o org id

  useEffect(() => {
    if (token) {
      loadOrganizations();
    } else {
      setError('Token mancante');
      setLoading(false);
    }
  }, [token]);

  const loadOrganizations = async () => {
    try {
      const res = await api.get(`/social/linkedin-orgs/${token}`);
      setData(res.data);
    } catch (err) {
      setError('Token non valido o scaduto');
    } finally {
      setLoading(false);
    }
  };

  const handleSelect = async () => {
    if (!selectedType) return;
    
    setSaving(true);
    try {
      if (selectedType === 'profile') {
        await api.post(`/social/linkedin-orgs/${token}/select?org_id=profile&include_profile=true`);
      } else {
        await api.post(`/social/linkedin-orgs/${token}/select?org_id=${selectedType}&include_profile=false`);
      }
      navigate('/?social_connected=linkedin');
    } catch (err) {
      setError('Errore durante il salvataggio');
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="w-8 h-8 animate-spin text-blue-600 mx-auto mb-4" />
          <p className="text-gray-600">Caricamento organizzazioni...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white p-8 rounded-xl shadow-sm max-w-md text-center">
          <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-semibold text-gray-900 mb-2">Errore</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={() => navigate('/brands')}
            className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
          >
            Torna ai Brand
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
      <div className="bg-white rounded-xl shadow-sm max-w-lg w-full p-6">
        <div className="flex items-center gap-3 mb-6">
          <div className="p-3 bg-blue-100 rounded-xl">
            <Linkedin className="w-6 h-6 text-blue-700" />
          </div>
          <div>
            <h1 className="text-xl font-semibold text-gray-900">Seleziona Account LinkedIn</h1>
            <p className="text-sm text-gray-500">Scegli quale account collegare al brand</p>
          </div>
        </div>

        <div className="space-y-3 mb-6">
          {/* Profilo personale */}
          {data?.profile && (
            <button
              onClick={() => setSelectedType('profile')}
              className={`w-full p-4 rounded-lg border-2 text-left transition-all flex items-center gap-3 ${
                selectedType === 'profile'
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <User className={`w-5 h-5 ${selectedType === 'profile' ? 'text-blue-600' : 'text-gray-400'}`} />
              <div>
                <p className="font-medium text-gray-900">{data.profile.name}</p>
                <p className="text-sm text-gray-500">Profilo personale</p>
              </div>
            </button>
          )}

          {/* Separatore se ci sono entrambi */}
          {data?.profile && data?.organizations?.length > 0 && (
            <div className="flex items-center gap-3 py-2">
              <div className="flex-1 border-t border-gray-200"></div>
              <span className="text-xs text-gray-400">oppure una pagina aziendale</span>
              <div className="flex-1 border-t border-gray-200"></div>
            </div>
          )}

          {/* Organizzazioni */}
          {data?.organizations?.map((org) => (
            <button
              key={org.id}
              onClick={() => setSelectedType(String(org.id))}
              className={`w-full p-4 rounded-lg border-2 text-left transition-all flex items-center gap-3 ${
                selectedType === String(org.id)
                  ? 'border-blue-500 bg-blue-50'
                  : 'border-gray-200 hover:border-gray-300'
              }`}
            >
              <Building2 className={`w-5 h-5 ${selectedType === String(org.id) ? 'text-blue-600' : 'text-gray-400'}`} />
              <div>
                <p className="font-medium text-gray-900">{org.name}</p>
                <p className="text-sm text-gray-500">linkedin.com/company/{org.vanity_name}</p>
              </div>
            </button>
          ))}
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => navigate('/brands')}
            className="flex-1 px-4 py-2 border border-gray-200 text-gray-700 rounded-lg hover:bg-gray-50"
          >
            Annulla
          </button>
          <button
            onClick={handleSelect}
            disabled={!selectedType || saving}
            className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            {saving ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Salvataggio...
              </>
            ) : (
              'Collega'
            )}
          </button>
        </div>
      </div>
    </div>
  );
}

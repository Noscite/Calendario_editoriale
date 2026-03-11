import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Facebook, Loader2, AlertCircle } from 'lucide-react';
import api from '../services/api';

export default function SelectFacebookPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');
  const [pages, setPages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selecting, setSelecting] = useState(false);
  const [error, setError] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    if (!token) {
      setError('Token mancante');
      setLoading(false);
      return;
    }

    const fetchPages = async () => {
      try {
        const response = await api.get(`/social/facebook-pages/${token}`);
        setPages(response.data.pages);
      } catch (err) {
        setError('Token non valido o scaduto. Riprova la connessione.');
      } finally {
        setLoading(false);
      }
    };

    fetchPages();
  }, [token]);

  const handleSelect = async (pageId) => {
    setSelecting(true);
    try {
      const response = await api.post(`/social/facebook-pages/${token}/select?page_id=${encodeURIComponent(pageId)}`);
      if (response.data.success) {
        navigate('/dashboard?social_connected=facebook');
      }
    } catch (err) {
      setError('Errore durante la selezione. Riprova.');
      setSelecting(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#3DAFA8] mx-auto mb-4" />
          <p className="text-gray-600">Caricamento pagine Facebook...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md text-center">
          <AlertCircle className="w-12 h-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-bold text-gray-800 mb-2">Errore</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button
            onClick={() => navigate('/dashboard')}
            className="px-6 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] transition-colors"
          >
            Torna alla Dashboard
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-lg">
        <div className="text-center mb-6">
          <Facebook className="w-12 h-12 text-blue-600 mx-auto mb-3" />
          <h1 className="text-2xl font-bold text-[#2C3E50]">Seleziona Pagina</h1>
          <p className="text-gray-500 mt-2">Quale pagina Facebook vuoi collegare?</p>
        </div>

        <div className="space-y-3">
          {pages.map((page) => (
            <button
              key={page.id}
              onClick={() => handleSelect(page.id)}
              disabled={selecting}
              className="w-full p-4 border border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all text-left flex items-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Facebook className="w-5 h-5 text-blue-600 flex-shrink-0" />
              <span className="font-medium text-gray-800">{page.name}</span>
            </button>
          ))}
        </div>

        {selecting && (
          <div className="mt-4 text-center text-gray-500 flex items-center justify-center gap-2">
            <Loader2 className="w-4 h-4 animate-spin" />
            Collegamento in corso...
          </div>
        )}

        <button
          onClick={() => navigate('/dashboard')}
          className="mt-6 w-full py-2 text-gray-500 hover:text-gray-700 transition-colors"
        >
          Annulla
        </button>
      </div>
    </div>
  );
}

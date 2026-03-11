import { useState, useEffect } from 'react';
import { useNavigate, Link, useSearchParams } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const { login, loginWithToken, isLoading } = useAuthStore();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  useEffect(() => {
    const azureToken = searchParams.get('azure_token');
    const azureError = searchParams.get('error');
    
    if (azureToken) {
      loginWithToken(azureToken).then(result => {
        if (result.success) {
          navigate('/dashboard');
        } else {
          setError(result.error || 'Errore login Azure');
        }
      });
    }
    
    if (azureError) {
      const errorMessages = {
        azure_denied: 'Accesso negato da Microsoft',
        azure_token_failed: 'Errore autenticazione Microsoft',
        graph_failed: 'Errore recupero profilo Microsoft',
        no_email: "Email non disponibile dall'account Microsoft",
        azure_exception: 'Errore imprevisto. Riprova.',
        no_code: 'Codice autorizzazione mancante',
      };
      setError(errorMessages[azureError] || 'Errore login Microsoft');
    }
  }, [searchParams]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    const result = await login(email, password);
    if (result.success) {
      navigate('/dashboard');
    } else {
      setError(result.error);
    }
  };

  const handleAzureLogin = () => {
    window.location.href = '/api/auth/azure/login';
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-[#2C3E50]">Noscite Calendar</h1>
          <p className="text-gray-500 mt-2">Accedi al tuo account</p>
        </div>
        
        {error && (
          <div className="bg-red-50 text-red-600 p-3 rounded-lg mb-4">{error}</div>
        )}

        <button
          onClick={handleAzureLogin}
          className="w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-200 text-gray-700 py-2.5 rounded-lg hover:bg-gray-50 hover:border-gray-300 transition-all mb-6 font-medium"
        >
          <svg width="20" height="20" viewBox="0 0 21 21">
            <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
            <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
            <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
            <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
          </svg>
          Accedi con Microsoft
        </button>

        <div className="flex items-center gap-3 mb-6">
          <div className="flex-1 h-px bg-gray-200"></div>
          <span className="text-sm text-gray-400">oppure</span>
          <div className="flex-1 h-px bg-gray-200"></div>
        </div>
        
        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              required
            />
          </div>
          <button
            type="submit"
            disabled={isLoading}
            className="w-full bg-[#3DAFA8] text-white py-2 rounded-lg hover:bg-[#2C3E50] transition-colors disabled:opacity-50"
          >
            {isLoading ? 'Accesso...' : 'Accedi'}
          </button>
        </form>
        
        <p className="text-center mt-6 text-gray-600">
          Non hai un account?{' '}
          <Link to="/register" className="text-[#3DAFA8] hover:underline">Registrati</Link>
        </p>
        <p className="text-center mt-4 text-sm text-gray-500">
          <Link to="/privacy" className="hover:underline">Privacy Policy</Link>
          <span className="mx-2">·</span>
          <Link to="/terms" className="hover:underline">Termini di Servizio</Link>
        </p>
      </div>
    </div>
  );
}

import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import api from '../services/api';

export default function ResetPassword() {
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (password !== passwordConfirmation) {
      setError('Le password non coincidono.');
      return;
    }

    setLoading(true);
    try {
      await api.post('/auth/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      setSuccess(true);
      setTimeout(() => {
        navigate('/login', { state: { resetSuccess: true } });
      }, 1500);
    } catch (err) {
      setError('Link non valido o scaduto. Richiedi un nuovo link.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-[#2C3E50]">Reimposta Password</h1>
          <p className="text-gray-500 mt-2">Inserisci la tua nuova password</p>
        </div>

        {error && (
          <div className="bg-red-50 text-red-600 p-3 rounded-lg mb-4">
            <p>{error}</p>
            {error.includes('scaduto') && (
              <Link
                to="/forgot-password"
                className="inline-block mt-2 text-sm text-[#3DAFA8] hover:underline font-medium"
              >
                Richiedi un nuovo link
              </Link>
            )}
          </div>
        )}

        {success && (
          <div className="bg-green-50 text-green-600 p-3 rounded-lg mb-4">
            Password reimpostata con successo! Reindirizzamento al login...
          </div>
        )}

        {!success && (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nuova password</label>
              <input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                required
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Conferma password</label>
              <input
                type="password"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                required
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="w-full bg-[#3DAFA8] text-white py-2 rounded-lg hover:bg-[#2C3E50] transition-colors disabled:opacity-50"
            >
              {loading ? 'Reimpostazione...' : 'Reimposta password'}
            </button>
          </form>
        )}

        <p className="text-center mt-6 text-gray-600">
          <Link to="/login" className="text-[#3DAFA8] hover:underline">Torna al login</Link>
        </p>
      </div>
    </div>
  );
}

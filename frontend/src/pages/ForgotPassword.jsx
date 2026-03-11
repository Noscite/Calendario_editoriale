import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../services/api';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState(false);
  const [error, setError] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess(false);
    setLoading(true);

    try {
      await api.post('/auth/forgot-password', { email });
      setSuccess(true);
    } catch (err) {
      setError(
        err.response?.data?.message || 'Errore durante la richiesta. Riprova.'
      );
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-[#2C3E50]">Recupera Password</h1>
          <p className="text-gray-500 mt-2">
            Inserisci la tua email per ricevere il link di reset
          </p>
        </div>

        {error && (
          <div className="bg-red-50 text-red-600 p-3 rounded-lg mb-4">{error}</div>
        )}

        {success && (
          <div className="bg-green-50 text-green-700 p-3 rounded-lg mb-4">
            Se l'email è registrata, riceverai un link a breve. Controlla anche la cartella spam.
          </div>
        )}

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
          <button
            type="submit"
            disabled={loading}
            className="w-full bg-[#3DAFA8] text-white py-2 rounded-lg hover:bg-[#2C3E50] transition-colors disabled:opacity-50"
          >
            {loading ? 'Invio in corso...' : 'Invia link di reset'}
          </button>
        </form>

        <p className="text-center mt-6 text-gray-600">
          <Link to="/login" className="text-[#3DAFA8] hover:underline">
            &larr; Torna al login
          </Link>
        </p>
      </div>
    </div>
  );
}

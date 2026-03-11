import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api from '../services/api';

export default function AcceptInvitation() {
  const { token } = useParams();
  const navigate = useNavigate();

  const [validating, setValidating] = useState(true);
  const [invitation, setInvitation] = useState(null);
  const [tokenError, setTokenError] = useState('');

  const [fullName, setFullName] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    api.get('/auth/invitation/' + token)
      .then((res) => {
        setInvitation(res.data);
        setValidating(false);
      })
      .catch(() => {
        setTokenError('Invito non valido o scaduto.');
        setValidating(false);
      });
  }, [token]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (password.length < 8) {
      setError('La password deve contenere almeno 8 caratteri.');
      return;
    }

    if (password !== confirmPassword) {
      setError('Le password non coincidono.');
      return;
    }

    setSubmitting(true);
    try {
      const res = await api.post('/auth/invitation/' + token + '/accept', {
        password,
        full_name: fullName,
      });
      const data = res.data;
      localStorage.setItem('token', data.access_token);
      window.location.href = '/dashboard';
    } catch (err) {
      setError(
        err.response?.data?.message ||
        "Errore durante l'accettazione dell'invito."
      );
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-[#2C3E50]">Noscite Calendar</h1>
          <p className="text-gray-500 mt-2">Accetta invito</p>
        </div>

        {validating && (
          <div className="text-center py-8">
            <div className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-[#3DAFA8] border-r-transparent"></div>
            <p className="text-gray-500 mt-4">Verifica invito in corso...</p>
          </div>
        )}

        {!validating && tokenError && (
          <div className="text-center py-4">
            <div className="bg-red-50 text-red-600 p-4 rounded-lg mb-6">
              {tokenError}
            </div>
            <Link
              to="/register"
              className="text-[#3DAFA8] hover:underline font-medium"
            >
              Vai alla registrazione
            </Link>
          </div>
        )}

        {!validating && invitation && (
          <>
            <div className="bg-gray-50 rounded-lg p-4 mb-6">
              <p className="text-sm text-gray-600">
                Sei stato invitato a unirti a{' '}
                <span className="font-semibold text-[#2C3E50]">
                  {invitation.organization_name}
                </span>{' '}
                con il ruolo di{' '}
                <span className="font-semibold text-[#3DAFA8]">
                  {invitation.role}
                </span>
                .
              </p>
            </div>

            {error && (
              <div className="bg-red-50 text-red-600 p-3 rounded-lg mb-4">
                {error}
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Nome completo
                </label>
                <input
                  type="text"
                  value={fullName}
                  onChange={(e) => setFullName(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  placeholder="Mario Rossi"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Password
                </label>
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  required
                  minLength={8}
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Conferma password
                </label>
                <input
                  type="password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  required
                  minLength={8}
                />
              </div>
              <button
                type="submit"
                disabled={submitting}
                className="w-full bg-[#3DAFA8] text-white py-2 rounded-lg hover:bg-[#2C3E50] transition-colors disabled:opacity-50"
              >
                {submitting ? 'Accettazione in corso...' : 'Accetta invito'}
              </button>
            </form>
          </>
        )}
      </div>
    </div>
  );
}

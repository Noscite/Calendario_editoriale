import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import HomePage from './HomePage';

/**
 * `/` route handler:
 *   - utente loggato → redirect a /dashboard (preserva il flusso esistente)
 *   - visitatore anonimo → mostra la HomePage pubblica di marketing
 */
export default function HomePageOrRedirect() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }
  return <HomePage />;
}

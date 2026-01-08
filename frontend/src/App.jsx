import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from './store/authStore';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import BrandDetail from './pages/BrandDetail';
import ProjectDetail from './pages/ProjectDetail';
import AdminDashboard from './pages/AdminDashboard';
import Profile from './pages/Profile';
import ProjectWizard from './pages/ProjectWizard';
import SelectGoogleLocation from './pages/SelectGoogleLocation';
import Privacy from './pages/Privacy';
import Home from './pages/Home';

function PrivateRoute({ children }) {
  const { isAuthenticated, isLoading } = useAuthStore();
  
  if (isLoading) {
    return <div className="min-h-screen flex items-center justify-center">Caricamento...</div>;
  }
  
  return isAuthenticated ? children : <Navigate to="/login" />;
}

function App() {
  const { checkAuth } = useAuthStore();

  useEffect(() => {
    checkAuth();
  }, []);

  return (
    <BrowserRouter>
      <Routes>
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/dashboard" element={<PrivateRoute><Dashboard /></PrivateRoute>} />
        <Route path="/brand/:brandId" element={<PrivateRoute><BrandDetail /></PrivateRoute>} />
        <Route path="/brand/:brandId/new-project" element={<PrivateRoute><ProjectWizard /></PrivateRoute>} />
        <Route path="/project/:projectId" element={<PrivateRoute><ProjectDetail /></PrivateRoute>} />
        <Route path="/admin" element={<PrivateRoute><AdminDashboard /></PrivateRoute>} />
        <Route path="/profile" element={<PrivateRoute><Profile /></PrivateRoute>} />
        <Route path="/select-google-location" element={<SelectGoogleLocation />} />
        <Route path="/privacy" element={<Privacy />} />
        <Route path="/" element={<Home />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;

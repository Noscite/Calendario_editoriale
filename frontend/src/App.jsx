import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from './store/authStore';
import { AppLayout } from './components/layout';

// Pages
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import BrandDetail from './pages/BrandDetail';
import ProjectDetail from './pages/ProjectDetail';
import ProjectWizard from './pages/ProjectWizard';
import VoiceProfilingInterview from './pages/VoiceProfilingInterview';
import Profile from './pages/Profile';
import AdminDashboard from './pages/AdminDashboard';
import SaasAdmin from './pages/SaasAdmin';
import BrandsPage from './pages/BrandsPage';
import SocialPage from './pages/SocialPage';
import InsightsPage from './pages/InsightsPage';
import SelectFacebookPage from './pages/SelectFacebookPage';
import SelectGoogleLocation from './pages/SelectGoogleLocation';
import SelectLinkedInOrg from './pages/SelectLinkedInOrg';
import DocumentsPage from './pages/DocumentsPage';
import SettingsPage from './pages/SettingsPage';
import ApiKeys from './pages/ApiKeys';
import Privacy from './pages/Privacy';
import TermsOfService from './pages/TermsOfService';
import DataDeletion from './pages/DataDeletion';
import HelpCenterPage from './pages/HelpCenterPage';
import HelpArticlePage from './pages/HelpArticlePage';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import AcceptInvitation from './pages/AcceptInvitation';

// Protected Route wrapper
function ProtectedRoute({ children }) {
  const { isAuthenticated } = useAuthStore();
  return isAuthenticated ? children : <Navigate to="/login" />;
}

function App() {
  const { checkAuth } = useAuthStore();

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/select-facebook-page" element={<SelectFacebookPage />} />
        <Route path="/select-google-location" element={<SelectGoogleLocation />} />
        <Route path="/select-linkedin-org" element={<SelectLinkedInOrg />} />
        <Route path="/privacy" element={<Privacy />} />
        <Route path="/terms" element={<TermsOfService />} />
        <Route path="/data-deletion" element={<DataDeletion />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/invitation/:token" element={<AcceptInvitation />} />

        {/* Protected routes with layout */}
        <Route
          path="/"
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          {/* Dashboard */}
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="dashboard" element={<Dashboard />} />
          
          {/* Brands */}
          <Route path="brands" element={<BrandsPage />} />
          <Route path="brand/:id" element={<BrandDetail />} />
          
          {/* Projects/Calendars */}
          <Route path="calendars" element={<Navigate to="/brands" replace />} />
          <Route path="project/:id" element={<ProjectDetail />} />
          <Route path="brand/:brandId/new-project" element={<ProjectWizard />} />
          <Route path="brand/:brandId/voice-interview" element={<VoiceProfilingInterview />} />
          
          {/* Social */}
          <Route path="social" element={<SocialPage />} />
          <Route path="insights" element={<InsightsPage />} />
          
          {/* Documents */}
          <Route path="documents" element={<DocumentsPage />} />
          
          {/* AI Assistant */}
          <Route path="ai-assistant" element={<VoiceProfilingInterview />} />
          
          {/* Settings & Profile */}
          <Route path="settings" element={<SettingsPage />} />
          <Route path="profile" element={<Profile />} />
          <Route path="api-keys" element={<ApiKeys />} />
          
          {/* Help Center */}
          <Route path="help" element={<HelpCenterPage />} />
          <Route path="help/article/:slug" element={<HelpArticlePage />} />

          {/* Admin */}
          <Route path="admin" element={<AdminDashboard />} />
          <Route path="admin/saas" element={<SaasAdmin />} />
        </Route>

        {/* Catch all */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

export default App;

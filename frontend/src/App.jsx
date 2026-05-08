import { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from './store/authStore';
import { AppLayout } from './components/layout';
import CookieBanner from './components/CookieBanner';

// Pages
import HomePage from './pages/HomePage';
import HomePageOrRedirect from './pages/HomePageOrRedirect';
import Login from './pages/Login';
import Register from './pages/Register';
import Dashboard from './pages/Dashboard';
import BrandDetail from './pages/BrandDetail';
import ProjectDetail from './pages/ProjectDetail';
import ProjectWizard from './pages/ProjectWizard';
import CreateProjectWizardV2 from './pages/CreateProjectWizardV2';
import EditProjectWizardV2 from './pages/EditProjectWizardV2';
import VoiceProfilingInterview from './pages/VoiceProfilingInterview';
import Profile from './pages/Profile';
import AdminDashboard from './pages/AdminDashboard';
import SaasAdmin from './pages/SaasAdmin';
import BrandsPage from './pages/BrandsPage';
import CreateBrandWizard from './pages/CreateBrandWizard';
import EditBrandWizard from './pages/EditBrandWizard';
import SocialPage from './pages/SocialPage';
import ReviewsPage from './pages/ReviewsPage';
import ReviewDetailPage from './pages/ReviewDetailPage';
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
import CookiePolicy from './pages/CookiePolicy';
import HelpCenterPage from './pages/HelpCenterPage';
import HelpArticlePage from './pages/HelpArticlePage';
import ForgotPassword from './pages/ForgotPassword';
import ResetPassword from './pages/ResetPassword';
import AcceptInvitation from './pages/AcceptInvitation';
import AuditProspectPage from './pages/AuditProspectPage';
import AuditSharePage from './pages/AuditSharePage';
import AuditAdminPage from './pages/AuditAdminPage';
import SubscriptionInactive from './pages/SubscriptionInactive';
import FeatureGatedModal from './components/FeatureGatedModal';

// Protected Route wrapper
function ProtectedRoute({ children }) {
  const { isAuthenticated } = useAuthStore();
  return isAuthenticated ? children : <Navigate to="/login" />;
}

function App() {
  const { checkAuth } = useAuthStore();
  const [gatedFeature, setGatedFeature] = useState(null);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  useEffect(() => {
    const handler = (e) => setGatedFeature(e.detail?.feature ?? 'unknown');
    window.addEventListener('kalendarium:feature-gated', handler);
    return () => window.removeEventListener('kalendarium:feature-gated', handler);
  }, []);

  return (
    <BrowserRouter>
      <Routes>
        {/* Public homepage with auto-redirect for authenticated users */}
        <Route path="/" element={<HomePageOrRedirect />} />

        {/* Public routes */}
        <Route path="/home" element={<HomePage />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="/select-facebook-page" element={<SelectFacebookPage />} />
        <Route path="/select-google-location" element={<SelectGoogleLocation />} />
        <Route path="/select-linkedin-org" element={<SelectLinkedInOrg />} />
        <Route path="/privacy" element={<Privacy />} />
        <Route path="/terms" element={<TermsOfService />} />
        <Route path="/data-deletion" element={<DataDeletion />} />
        <Route path="/cookies" element={<CookiePolicy />} />
        <Route path="/forgot-password" element={<ForgotPassword />} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/invitation/:token" element={<AcceptInvitation />} />
        <Route path="/audit/share/:token" element={<AuditSharePage />} />
        <Route path="/subscription/inactive" element={<SubscriptionInactive />} />

        {/* Protected routes with layout (path invariati) */}
        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          {/* Dashboard */}
          <Route path="/dashboard" element={<Dashboard />} />

          {/* Brands */}
          <Route path="/brands" element={<BrandsPage />} />
          <Route path="/brand/new-wizard" element={<CreateBrandWizard />} />
          <Route path="/brand/:id" element={<BrandDetail />} />
          <Route path="/brand/:id/wizard" element={<EditBrandWizard />} />

          {/* Projects/Calendars */}
          <Route path="/calendars" element={<Navigate to="/brands" replace />} />
          <Route path="/project/:id" element={<ProjectDetail />} />
          <Route path="/brand/:brandId/new-project" element={<ProjectWizard />} />
          <Route path="/brand/:brandId/new-project-v2" element={<CreateProjectWizardV2 />} />
          <Route path="/brand/:brandId/edit-project-v2/:projectId" element={<EditProjectWizardV2 />} />
          <Route path="/brand/:brandId/voice-interview" element={<VoiceProfilingInterview />} />

          {/* Audit */}
          <Route path="/audit" element={<AuditProspectPage />} />

          {/* Social */}
          <Route path="/social" element={<SocialPage />} />
          <Route path="/insights" element={<InsightsPage />} />

          {/* Recensioni */}
          <Route path="/reviews" element={<ReviewsPage />} />
          <Route path="/reviews/:id" element={<ReviewDetailPage />} />

          {/* Documents */}
          <Route path="/documents" element={<DocumentsPage />} />

          {/* AI Assistant */}
          <Route path="/ai-assistant" element={<VoiceProfilingInterview />} />

          {/* Settings & Profile */}
          <Route path="/settings" element={<SettingsPage />} />
          <Route path="/profile" element={<Profile />} />
          <Route path="/api-keys" element={<ApiKeys />} />

          {/* Help Center */}
          <Route path="/help" element={<HelpCenterPage />} />
          <Route path="/help/article/:slug" element={<HelpArticlePage />} />

          {/* Admin */}
          <Route path="/admin" element={<AdminDashboard />} />
          <Route path="/admin/saas" element={<SaasAdmin />} />
          <Route path="/admin/audits" element={<AuditAdminPage />} />
        </Route>

        {/* Catch all → home (gestita da HomePageOrRedirect) */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>

      {/* Cookie banner globale (mostrato finché l'utente non ha scelto) */}
      <CookieBanner />

      {/* Modal globale: mostrato quando un endpoint risponde con FEATURE_DISABLED_DURING_TRIAL */}
      <FeatureGatedModal
        isOpen={gatedFeature !== null}
        onClose={() => setGatedFeature(null)}
        feature={gatedFeature}
      />
    </BrowserRouter>
  );
}

export default App;

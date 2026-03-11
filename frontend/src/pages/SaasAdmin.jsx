import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, Users, Activity, BarChart3, Plus, 
  Edit3, Trash2, Check, X, Shield, Eye, Pencil,
  Loader2, RefreshCw, Building2, Crown, CreditCard,
  TrendingUp, Calendar, Image, FileText, Settings,
  ChevronDown, ChevronUp, AlertTriangle, CheckCircle, XCircle
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

const ROLES = [
  { id: 'superuser', name: 'Superuser', icon: Crown, color: 'text-purple-600 bg-purple-100' },
  { id: 'admin', name: 'Admin', icon: Shield, color: 'text-red-600 bg-red-100' },
  { id: 'editor', name: 'Editor', icon: Pencil, color: 'text-blue-600 bg-blue-100' },
  { id: 'viewer', name: 'Viewer', icon: Eye, color: 'text-gray-600 bg-gray-100' },
];

const ACTION_LABELS = {
  create: { label: 'Creato', color: 'bg-green-100 text-green-700' },
  update: { label: 'Modificato', color: 'bg-blue-100 text-blue-700' },
  delete: { label: 'Eliminato', color: 'bg-red-100 text-red-700' },
  generate: { label: 'Generato', color: 'bg-purple-100 text-purple-700' },
  export: { label: 'Esportato', color: 'bg-yellow-100 text-yellow-700' },
  login: { label: 'Login', color: 'bg-gray-100 text-gray-700' },
};

const STATUS_LABELS = {
  trial: { label: 'Trial', color: 'bg-yellow-100 text-yellow-700' },
  active: { label: 'Attivo', color: 'bg-green-100 text-green-700' },
  past_due: { label: 'Scaduto', color: 'bg-red-100 text-red-700' },
  cancelled: { label: 'Cancellato', color: 'bg-gray-100 text-gray-700' },
};

// Componente Progress Bar
function UsageBar({ used, limit, label, color = 'bg-[#3DAFA8]' }) {
  const percentage = limit === -1 ? 0 : Math.min(100, (used / limit) * 100);
  const isUnlimited = limit === -1;
  const isWarning = percentage >= 80;
  const isCritical = percentage >= 95;
  
  return (
    <div className="mb-3">
      <div className="flex justify-between text-sm mb-1">
        <span className="text-gray-600">{label}</span>
        <span className={`font-medium ${isCritical ? 'text-red-600' : isWarning ? 'text-yellow-600' : 'text-gray-800'}`}>
          {used.toLocaleString()} / {isUnlimited ? '∞' : limit.toLocaleString()}
        </span>
      </div>
      <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
        <div 
          className={`h-full rounded-full transition-all ${isCritical ? 'bg-red-500' : isWarning ? 'bg-yellow-500' : color}`}
          style={{ width: `${isUnlimited ? 5 : percentage}%` }}
        />
      </div>
    </div>
  );
}


// Modal per modificare piano
function EditPlanModal({ plan, onSave, onClose }) {
  const [formData, setFormData] = useState({
    display_name: plan.display_name,
    price_monthly: plan.price_monthly,
    price_yearly: plan.price_yearly || '',
    max_brands: plan.max_brands,
    max_users: plan.max_users,
    monthly_calendar_generations: plan.monthly_calendar_generations,
    monthly_text_tokens: plan.monthly_text_tokens,
    monthly_images: plan.monthly_images,
    has_export_excel: plan.has_export_excel,
    has_activity_log: plan.has_activity_log,
    has_advanced_roles: plan.has_advanced_roles,
    has_api_access: plan.has_api_access,
    has_crm_integration: plan.has_crm_integration,
    has_auto_publishing: plan.has_auto_publishing,
    has_analytics: plan.has_analytics,
    has_ab_testing: plan.has_ab_testing,
    allows_overage: plan.allows_overage,
    is_active: plan.is_active
  });
  const [saving, setSaving] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    await onSave(plan.id, formData);
    setSaving(false);
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="p-6 border-b sticky top-0 bg-white">
          <div className="flex items-center justify-between">
            <h2 className="text-xl font-bold">Modifica Piano: {plan.display_name}</h2>
            <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-lg">
              <X size={20} />
            </button>
          </div>
        </div>
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          <div>
            <h3 className="font-semibold mb-3">Informazioni Base</h3>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm text-gray-600 mb-1">Nome Display</label>
                <input type="text" value={formData.display_name} onChange={(e) => setFormData({...formData, display_name: e.target.value})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Prezzo Mensile</label>
                <input type="number" step="0.01" value={formData.price_monthly} onChange={(e) => setFormData({...formData, price_monthly: e.target.value})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Prezzo Annuale</label>
                <input type="number" step="0.01" value={formData.price_yearly} onChange={(e) => setFormData({...formData, price_yearly: e.target.value})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div className="flex items-center gap-2">
                <input type="checkbox" id="is_active" checked={formData.is_active} onChange={(e) => setFormData({...formData, is_active: e.target.checked})} className="w-4 h-4" />
                <label htmlFor="is_active" className="text-sm">Piano Attivo</label>
              </div>
            </div>
          </div>
          <div>
            <h3 className="font-semibold mb-3">Limiti (-1 = illimitato)</h3>
            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm text-gray-600 mb-1">Max Brand</label>
                <input type="number" value={formData.max_brands} onChange={(e) => setFormData({...formData, max_brands: parseInt(e.target.value)})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Max Utenti</label>
                <input type="number" value={formData.max_users} onChange={(e) => setFormData({...formData, max_users: parseInt(e.target.value)})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Calendari/mese</label>
                <input type="number" value={formData.monthly_calendar_generations} onChange={(e) => setFormData({...formData, monthly_calendar_generations: parseInt(e.target.value)})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Token/mese</label>
                <input type="number" value={formData.monthly_text_tokens} onChange={(e) => setFormData({...formData, monthly_text_tokens: parseInt(e.target.value)})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
              <div>
                <label className="block text-sm text-gray-600 mb-1">Immagini/mese</label>
                <input type="number" value={formData.monthly_images} onChange={(e) => setFormData({...formData, monthly_images: parseInt(e.target.value)})} className="w-full px-3 py-2 border rounded-lg" />
              </div>
            </div>
          </div>
          <div>
            <h3 className="font-semibold mb-3">Funzionalità</h3>
            <div className="grid grid-cols-3 gap-3">
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_export_excel} onChange={(e) => setFormData({...formData, has_export_excel: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Export Excel</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_activity_log} onChange={(e) => setFormData({...formData, has_activity_log: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Activity Log</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_advanced_roles} onChange={(e) => setFormData({...formData, has_advanced_roles: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Ruoli Avanzati</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_api_access} onChange={(e) => setFormData({...formData, has_api_access: e.target.checked})} className="w-4 h-4" /><span className="text-sm">API Access</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_crm_integration} onChange={(e) => setFormData({...formData, has_crm_integration: e.target.checked})} className="w-4 h-4" /><span className="text-sm">CRM Integration</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_auto_publishing} onChange={(e) => setFormData({...formData, has_auto_publishing: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Auto-publishing</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_analytics} onChange={(e) => setFormData({...formData, has_analytics: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Analytics</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.has_ab_testing} onChange={(e) => setFormData({...formData, has_ab_testing: e.target.checked})} className="w-4 h-4" /><span className="text-sm">A/B Testing</span></label>
              <label className="flex items-center gap-2 cursor-pointer"><input type="checkbox" checked={formData.allows_overage} onChange={(e) => setFormData({...formData, allows_overage: e.target.checked})} className="w-4 h-4" /><span className="text-sm">Overage</span></label>
            </div>
          </div>
          <div className="flex justify-end gap-3 pt-4 border-t">
            <button type="button" onClick={onClose} className="px-4 py-2 border rounded-lg hover:bg-gray-50">Annulla</button>
            <button type="submit" disabled={saving} className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50">{saving ? 'Salvataggio...' : 'Salva'}</button>
          </div>
        </form>
      </div>
    </div>
  );
}

export default function SaasAdmin() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('subscriptions');
  const [users, setUsers] = useState([]);
  const [organizations, setOrganizations] = useState([]);
  const [saasOrganizations, setSaasOrganizations] = useState([]);
  const [plans, setPlans] = useState([]);
  const [activities, setActivities] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isSuperuser, setIsSuperuser] = useState(false);
  
  // Filtri
  const [selectedOrg, setSelectedOrg] = useState('');
  const [selectedPlan, setSelectedPlan] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('');
  
  // Expanded org per vedere usage
  const [expandedOrg, setExpandedOrg] = useState(null);
  const [orgUsage, setOrgUsage] = useState({});
  
  // New user form
  const [showNewUser, setShowNewUser] = useState(false);
  const [newUser, setNewUser] = useState({ email: '', full_name: '', password: '', role: 'editor', organization_id: '' });
  const [saving, setSaving] = useState(false);
  
  // New org form
  const [showNewOrg, setShowNewOrg] = useState(false);
  const [newOrg, setNewOrg] = useState({ name: '', email: '', phone: '', vat_number: '', plan_id: '' });
  
  // Edit user
  const [editingUser, setEditingUser] = useState(null);

  // Edit plan modal
  const [editingPlan, setEditingPlan] = useState(null);

  // Edit org config modal
  const [editingOrgConfig, setEditingOrgConfig] = useState(null);
  
  // Edit org
  const [editingOrg, setEditingOrg] = useState(null);
  
  const token = localStorage.getItem('token');
  
  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const headers = { 'Authorization': `Bearer ${token}` };
      
      // Carica piani (pubblico)
      const plansRes = await fetch(`${API_URL}/subscriptions/plans`);
      if (plansRes.ok) {
        setPlans(await plansRes.json());
      }
      
      // Prova a caricare le organizzazioni (solo superuser può farlo)
      let orgsData = [];
      try {
        const orgsRes = await fetch(`${API_URL}/admin/organizations`, { headers });
        if (orgsRes.ok) {
          orgsData = await orgsRes.json();
          setIsSuperuser(true);
        }
      } catch {}
      setOrganizations(orgsData);
      
      // Carica organizzazioni SaaS con più dettagli
      if (orgsData.length > 0) {
        try {
          const saasRes = await fetch(`${API_URL}/subscriptions/organizations`, { headers });
          if (saasRes.ok) {
            setSaasOrganizations(await saasRes.json());
          }
        } catch {}
      }
      
      const orgParam = selectedOrg ? `?organization_id=${selectedOrg}` : '';
      
      const [usersRes, activityRes, statsRes] = await Promise.all([
        fetch(`${API_URL}/admin/users${orgParam}`, { headers }),
        fetch(`${API_URL}/admin/activity?limit=50${selectedOrg ? `&organization_id=${selectedOrg}` : ''}`, { headers }),
        fetch(`${API_URL}/admin/stats${orgParam}`, { headers })
      ]);
      
      if (!usersRes.ok) {
        if (usersRes.status === 403) {
          setError('Accesso negato. Solo admin e superuser possono accedere.');
          return;
        }
        throw new Error('Errore caricamento dati');
      }
      
      setUsers(await usersRes.json());
      setActivities(await activityRes.json());
      setStats(await statsRes.json());
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  
  const fetchOrgUsage = async (orgId) => {
    try {
      const headers = { 'Authorization': `Bearer ${token}` };
      const res = await fetch(`${API_URL}/subscriptions/usage/${orgId}`, { headers });
      if (res.ok) {
        const data = await res.json();
        setOrgUsage(prev => ({ ...prev, [orgId]: data }));
      }
    } catch (err) {
      console.error('Error fetching usage:', err);
    }
  };
  
  useEffect(() => {
    fetchData();
  }, [selectedOrg]);
  
  useEffect(() => {
    if (expandedOrg && !orgUsage[expandedOrg]) {
      fetchOrgUsage(expandedOrg);
    }
  }, [expandedOrg]);
  
  const handleCreateUser = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const userData = { ...newUser };
      if (userData.organization_id === '') delete userData.organization_id;
      else userData.organization_id = parseInt(userData.organization_id);
      
      const res = await fetch(`${API_URL}/admin/users`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(userData)
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore creazione utente');
      }
      
      setShowNewUser(false);
      setNewUser({ email: '', full_name: '', password: '', role: 'editor', organization_id: '' });
      fetchData();
    } catch (err) {
      alert(err.message);
    } finally {
      setSaving(false);
    }
  };
  
  const handleCreateOrg = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      const orgData = { 
        ...newOrg,
        plan_id: parseInt(newOrg.plan_id)
      };
      
      const res = await fetch(`${API_URL}/subscriptions/organizations`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(orgData)
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore creazione organizzazione');
      }
      
      setShowNewOrg(false);
      setNewOrg({ name: '', email: '', phone: '', vat_number: '', plan_id: '' });
      fetchData();
    } catch (err) {
      alert(err.message);
    } finally {
      setSaving(false);
    }
  };
  
  const handleUpdatePlan = async (planId, data) => {
    try {
      const res = await fetch(API_URL + "/subscriptions/plans/" + planId, {
        method: "PUT",
        headers: {
          "Authorization": "Bearer " + token,
          "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || "Errore aggiornamento piano");
      }
      setEditingPlan(null);
      fetchData();
    } catch (err) {
      alert(err.message);
    }
  };

  const handleUpdateOrg = async (orgId, data) => {
    try {
      const res = await fetch(`${API_URL}/subscriptions/organizations/${orgId}`, {
        method: 'PUT',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore aggiornamento');
      }
      
      setEditingOrg(null);
      fetchData();
    } catch (err) {
      alert(err.message);
    }
  };
  
  const handleUpdateUser = async (userId, data) => {
    try {
      const res = await fetch(`${API_URL}/admin/users/${userId}`, {
        method: 'PUT',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore aggiornamento');
      }
      
      setEditingUser(null);
      fetchData();
    } catch (err) {
      alert(err.message);
    }
  };
  
  const handleDeleteUser = async (userId) => {
    if (!confirm('Sei sicuro di voler disattivare questo utente?')) return;
    
    try {
      const res = await fetch(`${API_URL}/admin/users/${userId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore eliminazione');
      }
      
      fetchData();
    } catch (err) {
      alert(err.message);
    }
  };

  const handlePermanentDeleteUser = async (userId, userEmail) => {
    if (!confirm("ATTENZIONE: Eliminazione DEFINITIVA di " + userEmail + ". IRREVERSIBILE. Continuare?")) return;
    try {
      const res = await fetch(API_URL + "/admin/users/" + userId + "/permanent", {
        method: "DELETE",
        headers: { "Authorization": "Bearer " + token }
      });
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || "Errore eliminazione");
      }
      fetchData();
      alert("Utente " + userEmail + " eliminato definitivamente");
    } catch (err) {
      alert(err.message);
    }
  };
  
  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('it-IT', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };
  
  const formatPrice = (price) => {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(price);
  };
  
  // Filtra organizzazioni SaaS
  const filteredSaasOrgs = saasOrganizations.filter(org => {
    if (selectedPlan && org.plan_id !== parseInt(selectedPlan)) return false;
    if (selectedStatus && org.subscription_status !== selectedStatus) return false;
    return true;
  });
  
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={40} />
      </div>
    );
  }
  
  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="bg-white p-8 rounded-xl shadow-sm text-center max-w-md">
          <Shield className="mx-auto text-red-500 mb-4" size={48} />
          <h2 className="text-xl font-bold text-gray-800 mb-2">Accesso Negato</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button 
            onClick={() => navigate('/dashboard')}
            className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg"
          >
            Torna alla Dashboard
          </button>
        </div>
      </div>
    );
  }
  
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Modal Modifica Piano */}
      {editingPlan && (
        <EditPlanModal
          plan={editingPlan}
          onSave={handleUpdatePlan}
          onClose={() => setEditingPlan(null)}
        />
      )}
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <button onClick={() => navigate('/dashboard')} className="p-2 hover:bg-gray-100 rounded-lg">
                <ArrowLeft size={20} />
              </button>
              <div>
                <div className="flex items-center gap-2">
                  <h1 className="text-xl font-bold text-[#2C3E50]">Gestione SaaS</h1>
                  {isSuperuser && (
                    <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full font-medium">
                      Superuser
                    </span>
                  )}
                </div>
                <p className="text-sm text-gray-500">Gestione clienti, piani e abbonamenti</p>
              </div>
            </div>
            <div className="flex items-center gap-4">
              <button onClick={fetchData} className="p-2 hover:bg-gray-100 rounded-lg">
                <RefreshCw size={20} />
              </button>
            </div>
          </div>
        </div>
      </header>
      
      {/* Stats */}
      {stats && (
        <div className="max-w-7xl mx-auto px-4 py-6">
          <div className="grid gap-4 grid-cols-2 md:grid-cols-5">
            {isSuperuser && (
              <div className="bg-white rounded-xl p-4 shadow-sm">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-purple-100 rounded-lg">
                    <Building2 className="text-purple-600" size={20} />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{stats.organizations || saasOrganizations.length}</p>
                    <p className="text-sm text-gray-500">Organizzazioni</p>
                  </div>
                </div>
              </div>
            )}
            <div className="bg-white rounded-xl p-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-blue-100 rounded-lg">
                  <Users className="text-blue-600" size={20} />
                </div>
                <div>
                  <p className="text-2xl font-bold">{stats.users}</p>
                  <p className="text-sm text-gray-500">Utenti</p>
                </div>
              </div>
            </div>
            <div className="bg-white rounded-xl p-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-green-100 rounded-lg">
                  <BarChart3 className="text-green-600" size={20} />
                </div>
                <div>
                  <p className="text-2xl font-bold">{stats.brands}</p>
                  <p className="text-sm text-gray-500">Brand</p>
                </div>
              </div>
            </div>
            <div className="bg-white rounded-xl p-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-orange-100 rounded-lg">
                  <Calendar className="text-orange-600" size={20} />
                </div>
                <div>
                  <p className="text-2xl font-bold">{stats.projects}</p>
                  <p className="text-sm text-gray-500">Progetti</p>
                </div>
              </div>
            </div>
            <div className="bg-white rounded-xl p-4 shadow-sm">
              <div className="flex items-center gap-3">
                <div className="p-2 bg-teal-100 rounded-lg">
                  <FileText className="text-teal-600" size={20} />
                </div>
                <div>
                  <p className="text-2xl font-bold">{stats.posts}</p>
                  <p className="text-sm text-gray-500">Post</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
      
      {/* Tabs */}
      <div className="max-w-7xl mx-auto px-4">
        <div className="flex gap-4 border-b overflow-x-auto">
          <button
            onClick={() => setActiveTab('users')}
            className={`px-4 py-3 font-medium border-b-2 transition-colors whitespace-nowrap ${
              activeTab === 'users' 
                ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Users size={18} className="inline mr-2" />
            Utenti
          </button>
          {isSuperuser && (
            <>
              <button
                onClick={() => setActiveTab('subscriptions')}
                className={`px-4 py-3 font-medium border-b-2 transition-colors whitespace-nowrap ${
                  activeTab === 'subscriptions' 
                    ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <CreditCard size={18} className="inline mr-2" />
                Abbonamenti
              </button>
              <button
                onClick={() => setActiveTab('plans')}
                className={`px-4 py-3 font-medium border-b-2 transition-colors whitespace-nowrap ${
                  activeTab === 'plans' 
                    ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                    : 'border-transparent text-gray-500 hover:text-gray-700'
                }`}
              >
                <Settings size={18} className="inline mr-2" />
                Piani
              </button>
            </>
          )}
          <button
            onClick={() => setActiveTab('activity')}
            className={`px-4 py-3 font-medium border-b-2 transition-colors whitespace-nowrap ${
              activeTab === 'activity' 
                ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Activity size={18} className="inline mr-2" />
            Activity Log
          </button>
        </div>
      </div>
      
      {/* Content */}
      <main className="max-w-7xl mx-auto px-4 py-6">
        
        {/* Users Tab */}
        {activeTab === 'users' && (
          <div className="bg-white rounded-xl shadow-sm">
            <div className="p-4 border-b flex justify-between items-center flex-wrap gap-4">
              <h2 className="font-semibold">Utenti ({users.length})</h2>
              <div className="flex items-center gap-4">
                {isSuperuser && organizations.length > 0 && (
                  <select
                    value={selectedOrg}
                    onChange={(e) => setSelectedOrg(e.target.value)}
                    className="px-3 py-2 border rounded-lg text-sm"
                  >
                    <option value="">Tutte le organizzazioni</option>
                    {organizations.map(org => (
                      <option key={org.id} value={org.id}>{org.name}</option>
                    ))}
                  </select>
                )}
                <button
                  onClick={() => setShowNewUser(true)}
                  className="flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50]"
                >
                  <Plus size={18} /> Nuovo Utente
                </button>
              </div>
            </div>
            
            {/* New User Form */}
            {showNewUser && (
              <div className="p-4 bg-gray-50 border-b">
                <form onSubmit={handleCreateUser} className="grid grid-cols-1 md:grid-cols-6 gap-4">
                  <input
                    type="email"
                    placeholder="Email *"
                    value={newUser.email}
                    onChange={(e) => setNewUser({...newUser, email: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  />
                  <input
                    type="text"
                    placeholder="Nome completo *"
                    value={newUser.full_name}
                    onChange={(e) => setNewUser({...newUser, full_name: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  />
                  <input
                    type="password"
                    placeholder="Password *"
                    value={newUser.password}
                    onChange={(e) => setNewUser({...newUser, password: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  />
                  <select
                    value={newUser.role}
                    onChange={(e) => setNewUser({...newUser, role: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                  >
                    {ROLES.filter(r => isSuperuser || r.id !== 'superuser').map(r => (
                      <option key={r.id} value={r.id}>{r.name}</option>
                    ))}
                  </select>
                  {isSuperuser && (
                    <select
                      value={newUser.organization_id}
                      onChange={(e) => setNewUser({...newUser, organization_id: e.target.value})}
                      className="px-3 py-2 border rounded-lg"
                    >
                      <option value="">Seleziona org...</option>
                      {organizations.map(org => (
                        <option key={org.id} value={org.id}>{org.name}</option>
                      ))}
                    </select>
                  )}
                  <div className="flex gap-2">
                    <button
                      type="submit"
                      disabled={saving}
                      className="flex-1 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg disabled:opacity-50"
                    >
                      {saving ? <Loader2 className="animate-spin mx-auto" size={18} /> : 'Crea'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setShowNewUser(false)}
                      className="px-4 py-2 border rounded-lg"
                    >
                      <X size={18} />
                    </button>
                  </div>
                </form>
              </div>
            )}
            
            {/* Users Table */}
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Utente</th>
                    {isSuperuser && <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Organizzazione</th>}
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Ruolo</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Stato</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Creato</th>
                    <th className="text-right px-4 py-3 text-sm font-medium text-gray-500">Azioni</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {users.map(user => (
                    <tr key={user.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3">
                        <div>
                          <p className="font-medium">{user.full_name || '-'}</p>
                          <p className="text-sm text-gray-500">{user.email}</p>
                        </div>
                      </td>
                      {isSuperuser && (
                        <td className="px-4 py-3 text-sm text-gray-600">
                          {user.organization_name || '-'}
                        </td>
                      )}
                      <td className="px-4 py-3">
                        {editingUser === user.id ? (
                          <select
                            defaultValue={user.role}
                            onChange={(e) => handleUpdateUser(user.id, { role: e.target.value })}
                            className="px-2 py-1 border rounded"
                          >
                            {ROLES.filter(r => isSuperuser || r.id !== 'superuser').map(r => (
                              <option key={r.id} value={r.id}>{r.name}</option>
                            ))}
                          </select>
                        ) : (
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                            ROLES.find(r => r.id === user.role)?.color || 'bg-gray-100'
                          }`}>
                            {ROLES.find(r => r.id === user.role)?.name || user.role}
                          </span>
                        )}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                          user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        }`}>
                          {user.is_active ? 'Attivo' : 'Disattivato'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500">
                        {formatDate(user.created_at)}
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex justify-end gap-2">
                          <button
                            onClick={() => setEditingUser(editingUser === user.id ? null : user.id)}
                            className="p-1 hover:bg-gray-100 rounded"
                          >
                            <Edit3 size={16} />
                          </button>
                          <button
                            onClick={() => handleDeleteUser(user.id)}
                            className="p-1 hover:bg-red-100 text-red-600 rounded"
                          >
                            <Trash2 size={16} />
                          </button>
                          <button
                            onClick={() => handlePermanentDeleteUser(user.id, user.email)}
                            className="p-1 hover:bg-red-100 text-red-800 rounded"
                            title="Elimina DEFINITIVAMENTE"
                          >
                            <XCircle size={16} />
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        
        {/* Subscriptions Tab */}
        {activeTab === 'subscriptions' && isSuperuser && (
          <div className="space-y-6">
            {/* Filtri e azioni */}
            <div className="bg-white rounded-xl shadow-sm p-4">
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-4">
                  <select
                    value={selectedPlan}
                    onChange={(e) => setSelectedPlan(e.target.value)}
                    className="px-3 py-2 border rounded-lg text-sm"
                  >
                    <option value="">Tutti i piani</option>
                    {plans.map(plan => (
                      <option key={plan.id} value={plan.id}>{plan.display_name}</option>
                    ))}
                  </select>
                  <select
                    value={selectedStatus}
                    onChange={(e) => setSelectedStatus(e.target.value)}
                    className="px-3 py-2 border rounded-lg text-sm"
                  >
                    <option value="">Tutti gli stati</option>
                    <option value="trial">Trial</option>
                    <option value="active">Attivo</option>
                    <option value="past_due">Scaduto</option>
                    <option value="cancelled">Cancellato</option>
                  </select>
                </div>
                <button
                  onClick={() => setShowNewOrg(true)}
                  className="flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50]"
                >
                  <Plus size={18} /> Nuova Organizzazione
                </button>
              </div>
            </div>
            
            {/* New Org Form */}
            {showNewOrg && (
              <div className="bg-white rounded-xl shadow-sm p-4">
                <h3 className="font-semibold mb-4">Nuova Organizzazione</h3>
                <form onSubmit={handleCreateOrg} className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <input
                    type="text"
                    placeholder="Nome azienda *"
                    value={newOrg.name}
                    onChange={(e) => setNewOrg({...newOrg, name: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  />
                  <input
                    type="email"
                    placeholder="Email *"
                    value={newOrg.email}
                    onChange={(e) => setNewOrg({...newOrg, email: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  />
                  <input
                    type="tel"
                    placeholder="Telefono"
                    value={newOrg.phone}
                    onChange={(e) => setNewOrg({...newOrg, phone: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                  />
                  <input
                    type="text"
                    placeholder="P.IVA"
                    value={newOrg.vat_number}
                    onChange={(e) => setNewOrg({...newOrg, vat_number: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                  />
                  <select
                    value={newOrg.plan_id}
                    onChange={(e) => setNewOrg({...newOrg, plan_id: e.target.value})}
                    className="px-3 py-2 border rounded-lg"
                    required
                  >
                    <option value="">Seleziona piano *</option>
                    {plans.map(plan => (
                      <option key={plan.id} value={plan.id}>
                        {plan.display_name} - {formatPrice(plan.price_monthly)}/mese
                      </option>
                    ))}
                  </select>
                  <div className="flex gap-2">
                    <button
                      type="submit"
                      disabled={saving}
                      className="flex-1 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg disabled:opacity-50"
                    >
                      {saving ? <Loader2 className="animate-spin mx-auto" size={18} /> : 'Crea'}
                    </button>
                    <button
                      type="button"
                      onClick={() => setShowNewOrg(false)}
                      className="px-4 py-2 border rounded-lg"
                    >
                      <X size={18} />
                    </button>
                  </div>
                </form>
              </div>
            )}
            
            {/* Organizations List */}
            <div className="bg-white rounded-xl shadow-sm">
              <div className="p-4 border-b">
                <h2 className="font-semibold">Organizzazioni ({filteredSaasOrgs.length})</h2>
              </div>
              <div className="divide-y">
                {filteredSaasOrgs.map(org => (
                  <div key={org.id} className="p-4">
                    {/* Main row */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-4 flex-1">
                        <button
                          onClick={() => setExpandedOrg(expandedOrg === org.id ? null : org.id)}
                          className="p-1 hover:bg-gray-100 rounded"
                        >
                          {expandedOrg === org.id ? <ChevronUp size={20} /> : <ChevronDown size={20} />}
                        </button>
                        <div className="flex-1">
                          <div className="flex items-center gap-2">
                            <h3 className="font-medium">{org.name}</h3>
                            <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                              STATUS_LABELS[org.subscription_status]?.color || 'bg-gray-100'
                            }`}>
                              {STATUS_LABELS[org.subscription_status]?.label || org.subscription_status}
                            </span>
                          </div>
                          <p className="text-sm text-gray-500">{org.email}</p>
                        </div>
                        <div className="text-center px-4">
                          <p className="text-sm font-medium text-[#3DAFA8]">
                            {org.plan_display_name || 'Nessun piano'}
                          </p>
                          <p className="text-xs text-gray-500">
                            {org.brands_count} brand · {org.users_count} utenti
                          </p>
                        </div>
                        <div className="text-right">
                          {org.trial_ends_at && org.subscription_status === 'trial' && (
                            <p className="text-sm text-yellow-600">
                              Trial scade: {formatDate(org.trial_ends_at).split(',')[0]}
                            </p>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-2 ml-4">
                        <select
                          value={org.plan_id || ''}
                          onChange={(e) => handleUpdateOrg(org.id, { plan_id: parseInt(e.target.value) })}
                          className="px-2 py-1 border rounded text-sm"
                        >
                          {plans.map(plan => (
                            <option key={plan.id} value={plan.id}>{plan.display_name}</option>
                          ))}
                        </select>
                        <select
                          value={org.subscription_status}
                          onChange={(e) => handleUpdateOrg(org.id, { subscription_status: e.target.value })}
                          className="px-2 py-1 border rounded text-sm"
                        >
                          <option value="trial">Trial</option>
                          <option value="active">Attivo</option>
                          <option value="past_due">Scaduto</option>
                          <option value="cancelled">Cancellato</option>
                        </select>
                      </div>
                    </div>
                    
                    {/* Expanded details */}
                    {expandedOrg === org.id && (
                      <div className="mt-4 pt-4 border-t grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Info */}
                        <div>
                          <h4 className="font-medium text-sm text-gray-500 mb-2">Dettagli</h4>
                          <div className="space-y-1 text-sm">
                            <p><span className="text-gray-500">Slug:</span> {org.slug}</p>
                            <p><span className="text-gray-500">Telefono:</span> {org.phone || '-'}</p>
                            <p><span className="text-gray-500">P.IVA:</span> {org.vat_number || '-'}</p>
                            <p><span className="text-gray-500">Creato:</span> {formatDate(org.created_at)}</p>
                          </div>
                          
                          <h4 className="font-medium text-sm text-gray-500 mt-4 mb-2">Limiti Piano</h4>
                          <div className="space-y-1 text-sm">
                            <p><span className="text-gray-500">Brand:</span> {org.effective_max_brands === -1 ? 'Illimitati' : org.effective_max_brands}</p>
                            <p><span className="text-gray-500">Utenti:</span> {org.effective_max_users === -1 ? 'Illimitati' : org.effective_max_users}</p>
                            <p><span className="text-gray-500">Calendari/mese:</span> {org.effective_monthly_calendars === -1 ? 'Illimitati' : org.effective_monthly_calendars}</p>
                            <p><span className="text-gray-500">Token/mese:</span> {org.effective_monthly_tokens === -1 ? 'Illimitati' : org.effective_monthly_tokens?.toLocaleString()}</p>
                            <p><span className="text-gray-500">Immagini/mese:</span> {org.effective_monthly_images === -1 ? 'Illimitate' : org.effective_monthly_images}</p>
                          </div>
                        </div>
                        
                        {/* Usage */}
                        <div>
                          <h4 className="font-medium text-sm text-gray-500 mb-2">Utilizzo Mese Corrente</h4>
                          {orgUsage[org.id] ? (
                            <div>
                              <UsageBar 
                                used={orgUsage[org.id].calendar_generations_used} 
                                limit={orgUsage[org.id].calendar_generations_limit}
                                label="Calendari"
                              />
                              <UsageBar 
                                used={orgUsage[org.id].text_tokens_used} 
                                limit={orgUsage[org.id].text_tokens_limit}
                                label="Token"
                              />
                              <UsageBar 
                                used={orgUsage[org.id].images_generated} 
                                limit={orgUsage[org.id].images_limit}
                                label="Immagini"
                              />
                              {orgUsage[org.id].overage_cost > 0 && (
                                <p className="text-sm text-orange-600 mt-2">
                                  <AlertTriangle size={14} className="inline mr-1" />
                                  Costo overage: {formatPrice(orgUsage[org.id].overage_cost)}
                                </p>
                              )}
                            </div>
                          ) : (
                            <div className="flex items-center justify-center py-8">
                              <Loader2 className="animate-spin text-gray-400" size={24} />
                            </div>
                          )}
                        </div>
                      </div>
                    )}
                  </div>
                ))}
                
                {filteredSaasOrgs.length === 0 && (
                  <p className="p-8 text-center text-gray-500">Nessuna organizzazione trovata</p>
                )}
              </div>
            </div>
          </div>
        )}
        
        {/* Plans Tab */}
        {activeTab === 'plans' && isSuperuser && (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            {plans.map(plan => (
              <div 
                key={plan.id} 
                className={`bg-white rounded-xl shadow-sm overflow-hidden ${
                  plan.name === 'pro' ? 'ring-2 ring-[#3DAFA8]' : ''
                }`}
              >
                {plan.name === 'pro' && (
                  <div className="bg-[#3DAFA8] text-white text-center py-1 text-sm font-medium">
                    Più Popolare
                  </div>
                )}
                <div className="p-6">
                  <div className="flex items-start justify-between"><h3 className="text-xl font-bold">{plan.display_name}</h3><button onClick={() => setEditingPlan(plan)} className="p-1 hover:bg-gray-100 rounded"><Edit3 size={16} /></button></div>
                  <div className="mt-2">
                    <span className="text-3xl font-bold">{formatPrice(plan.price_monthly)}</span>
                    <span className="text-gray-500">/mese</span>
                  </div>
                  {plan.price_yearly && (
                    <p className="text-sm text-gray-500">
                      o {formatPrice(plan.price_yearly)}/anno (risparmi 2 mesi)
                    </p>
                  )}
                  
                  <div className="mt-6 space-y-3">
                    <div className="flex items-center gap-2 text-sm">
                      <Building2 size={16} className="text-gray-400" />
                      <span>{plan.max_brands === -1 ? 'Brand illimitati' : `${plan.max_brands} brand`}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                      <Users size={16} className="text-gray-400" />
                      <span>{plan.max_users === -1 ? 'Utenti illimitati' : `${plan.max_users} utenti`}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                      <Calendar size={16} className="text-gray-400" />
                      <span>{plan.monthly_calendar_generations === -1 ? 'Calendari illimitati' : `${plan.monthly_calendar_generations} calendari/mese`}</span>
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                      <Image size={16} className="text-gray-400" />
                      <span>{plan.monthly_images === -1 ? 'Immagini illimitate' : `${plan.monthly_images} immagini/mese`}</span>
                    </div>
                  </div>
                  
                  <div className="mt-6 pt-4 border-t space-y-2">
                    {plan.has_export_excel && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle size={14} /> Export Excel
                      </div>
                    )}
                    {plan.has_auto_publishing && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle size={14} /> Auto-publishing
                      </div>
                    )}
                    {plan.has_analytics && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle size={14} /> Analytics
                      </div>
                    )}
                    {plan.has_api_access && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle size={14} /> API Access
                      </div>
                    )}
                    {plan.allows_overage && (
                      <div className="flex items-center gap-2 text-sm text-green-600">
                        <CheckCircle size={14} /> Overage disponibile
                      </div>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
        
        {/* Activity Tab */}
        {activeTab === 'activity' && (
          <div className="bg-white rounded-xl shadow-sm">
            <div className="p-4 border-b flex items-center justify-between">
              <h2 className="font-semibold">Activity Log</h2>
              {isSuperuser && organizations.length > 0 && (
                <select
                  value={selectedOrg}
                  onChange={(e) => setSelectedOrg(e.target.value)}
                  className="px-3 py-2 border rounded-lg text-sm"
                >
                  <option value="">Tutte le organizzazioni</option>
                  {organizations.map(org => (
                    <option key={org.id} value={org.id}>{org.name}</option>
                  ))}
                </select>
              )}
            </div>
            <div className="divide-y max-h-[600px] overflow-y-auto">
              {activities.length === 0 ? (
                <p className="p-8 text-center text-gray-500">Nessuna attività registrata</p>
              ) : (
                activities.map(activity => (
                  <div key={activity.id} className="p-4 hover:bg-gray-50">
                    <div className="flex items-start gap-4">
                      <div className={`px-2 py-1 rounded text-xs font-medium ${
                        ACTION_LABELS[activity.action]?.color || 'bg-gray-100'
                      }`}>
                        {ACTION_LABELS[activity.action]?.label || activity.action}
                      </div>
                      <div className="flex-1">
                        <p className="text-sm">
                          <span className="font-medium">{activity.user_name || activity.user_email}</span>
                          {' '}{activity.entity_type && (
                            <>ha {activity.action === 'delete' ? 'eliminato' : activity.action === 'create' ? 'creato' : 'modificato'} {activity.entity_type}</>
                          )}
                          {activity.entity_name && (
                            <span className="font-medium"> "{activity.entity_name}"</span>
                          )}
                        </p>
                        <p className="text-xs text-gray-400 mt-1">
                          {formatDate(activity.created_at)}
                        </p>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

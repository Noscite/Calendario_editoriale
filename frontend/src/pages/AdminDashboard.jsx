import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, Users, Activity, BarChart3, Plus, 
  Edit3, Trash2, Check, X, Shield, Eye, Pencil,
  Loader2, RefreshCw, Building2, Crown
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

export default function AdminDashboard() {
  const navigate = useNavigate();
  const [activeTab, setActiveTab] = useState('users');
  const [users, setUsers] = useState([]);
  const [organizations, setOrganizations] = useState([]);
  const [activities, setActivities] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isSuperuser, setIsSuperuser] = useState(false);
  
  // Filtri
  const [selectedOrg, setSelectedOrg] = useState('');
  
  // New user form
  const [showNewUser, setShowNewUser] = useState(false);
  const [newUser, setNewUser] = useState({ email: '', full_name: '', password: '', role: 'editor', organization_id: '' });
  const [saving, setSaving] = useState(false);
  
  // Edit user
  const [editingUser, setEditingUser] = useState(null);
  
  const token = localStorage.getItem('token');
  
  const fetchData = async () => {
    setLoading(true);
    setError(null);
    try {
      const headers = { 'Authorization': `Bearer ${token}` };
      
      // Prima prova a caricare le organizzazioni (solo superuser può farlo)
      let orgsData = [];
      try {
        const orgsRes = await fetch(`${API_URL}/admin/organizations`, { headers });
        if (orgsRes.ok) {
          orgsData = await orgsRes.json();
          setIsSuperuser(true);
        }
      } catch {}
      setOrganizations(orgsData);
      
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
  
  useEffect(() => {
    fetchData();
  }, [selectedOrg]);
  
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
                  <h1 className="text-xl font-bold text-[#2C3E50]">Admin Dashboard</h1>
                  {isSuperuser && (
                    <span className="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full font-medium">
                      Superuser
                    </span>
                  )}
                </div>
                <p className="text-sm text-gray-500">Gestione utenti e attività</p>
              </div>
            </div>
            <div className="flex items-center gap-4">
              {/* Filtro organizzazione (solo superuser) */}
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
          <div className={`grid gap-4 ${isSuperuser && !selectedOrg ? 'grid-cols-2 md:grid-cols-5' : 'grid-cols-2 md:grid-cols-4'}`}>
            {isSuperuser && !selectedOrg && (
              <div className="bg-white rounded-xl p-4 shadow-sm">
                <div className="flex items-center gap-3">
                  <div className="p-2 bg-purple-100 rounded-lg">
                    <Building2 className="text-purple-600" size={20} />
                  </div>
                  <div>
                    <p className="text-2xl font-bold">{stats.organizations}</p>
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
                  <Activity className="text-orange-600" size={20} />
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
                  <Activity className="text-teal-600" size={20} />
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
        <div className="flex gap-4 border-b">
          <button
            onClick={() => setActiveTab('users')}
            className={`px-4 py-3 font-medium border-b-2 transition-colors ${
              activeTab === 'users' 
                ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Users size={18} className="inline mr-2" />
            Utenti
          </button>
          <button
            onClick={() => setActiveTab('activity')}
            className={`px-4 py-3 font-medium border-b-2 transition-colors ${
              activeTab === 'activity' 
                ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                : 'border-transparent text-gray-500 hover:text-gray-700'
            }`}
          >
            <Activity size={18} className="inline mr-2" />
            Activity Log
          </button>
          {isSuperuser && (
            <button
              onClick={() => setActiveTab('organizations')}
              className={`px-4 py-3 font-medium border-b-2 transition-colors ${
                activeTab === 'organizations' 
                  ? 'border-[#3DAFA8] text-[#3DAFA8]' 
                  : 'border-transparent text-gray-500 hover:text-gray-700'
              }`}
            >
              <Building2 size={18} className="inline mr-2" />
              Organizzazioni
            </button>
          )}
        </div>
      </div>
      
      {/* Content */}
      <main className="max-w-7xl mx-auto px-4 py-6">
        {/* Users Tab */}
        {activeTab === 'users' && (
          <div className="bg-white rounded-xl shadow-sm">
            <div className="p-4 border-b flex justify-between items-center">
              <h2 className="font-semibold">Utenti ({users.length})</h2>
              <button
                onClick={() => setShowNewUser(true)}
                className="flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50]"
              >
                <Plus size={18} /> Nuovo Utente
              </button>
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
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        
        {/* Activity Tab */}
        {activeTab === 'activity' && (
          <div className="bg-white rounded-xl shadow-sm">
            <div className="p-4 border-b">
              <h2 className="font-semibold">Activity Log</h2>
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
        
        {/* Organizations Tab (solo superuser) */}
        {activeTab === 'organizations' && isSuperuser && (
          <div className="bg-white rounded-xl shadow-sm">
            <div className="p-4 border-b flex justify-between items-center">
              <h2 className="font-semibold">Organizzazioni ({organizations.length})</h2>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">ID</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Nome</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Slug</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Utenti</th>
                    <th className="text-left px-4 py-3 text-sm font-medium text-gray-500">Creato</th>
                  </tr>
                </thead>
                <tbody className="divide-y">
                  {organizations.map(org => (
                    <tr key={org.id} className="hover:bg-gray-50">
                      <td className="px-4 py-3 text-sm">{org.id}</td>
                      <td className="px-4 py-3 font-medium">{org.name}</td>
                      <td className="px-4 py-3 text-sm text-gray-500">{org.slug || '-'}</td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs">
                          {org.user_count} utenti
                        </span>
                      </td>
                      <td className="px-4 py-3 text-sm text-gray-500">{formatDate(org.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

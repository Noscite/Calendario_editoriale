import { useState, useEffect } from 'react';
import { Settings, Bell, Palette, Globe, Shield, Save, Loader2, Users, UserPlus, Trash2, Mail, X } from 'lucide-react';
import { useAuthStore } from '../store/authStore';
import { invitations } from '../services/api';

export default function SettingsPage() {
  const { user } = useAuthStore();
  const isAdminOrOwner = user?.role === 'owner' || user?.role === 'admin';

  const [saving, setSaving] = useState(false);
  const [settings, setSettings] = useState({
    notifications_email: true,
    notifications_push: false,
    language: 'it',
    theme: 'light',
    auto_publish: false,
  });

  const handleSave = async () => {
    setSaving(true);
    // Simulate save
    await new Promise(r => setTimeout(r, 1000));
    setSaving(false);
    alert('Impostazioni salvate!');
  };

  return (
    <div className="max-w-3xl mx-auto">
      <div className="mb-6">
        <p className="text-gray-500">Configura le tue preferenze</p>
      </div>

      <div className="space-y-6">
        {/* Notifications */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
            <Bell className="text-[#3DAFA8]" size={20} /> Notifiche
          </h3>
          <div className="space-y-4">
            <label className="flex items-center justify-between">
              <span>Notifiche email</span>
              <input
                type="checkbox"
                checked={settings.notifications_email}
                onChange={(e) => setSettings(prev => ({ ...prev, notifications_email: e.target.checked }))}
                className="w-5 h-5 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
              />
            </label>
            <label className="flex items-center justify-between">
              <span>Notifiche push</span>
              <input
                type="checkbox"
                checked={settings.notifications_push}
                onChange={(e) => setSettings(prev => ({ ...prev, notifications_push: e.target.checked }))}
                className="w-5 h-5 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
              />
            </label>
          </div>
        </div>

        {/* Appearance */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
            <Palette className="text-[#3DAFA8]" size={20} /> Aspetto
          </h3>
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-2">Tema</label>
              <select
                value={settings.theme}
                onChange={(e) => setSettings(prev => ({ ...prev, theme: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
              >
                <option value="light">Chiaro</option>
                <option value="dark">Scuro</option>
                <option value="system">Sistema</option>
              </select>
            </div>
          </div>
        </div>

        {/* Language */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
            <Globe className="text-[#3DAFA8]" size={20} /> Lingua
          </h3>
          <select
            value={settings.language}
            onChange={(e) => setSettings(prev => ({ ...prev, language: e.target.value }))}
            className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
          >
            <option value="it">Italiano</option>
            <option value="en">English</option>
          </select>
        </div>

        {/* Publishing */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-lg mb-4 flex items-center gap-2">
            <Shield className="text-[#3DAFA8]" size={20} /> Pubblicazione
          </h3>
          <label className="flex items-center justify-between">
            <div>
              <span className="font-medium">Pubblicazione automatica</span>
              <p className="text-sm text-gray-500">Pubblica i post automaticamente all'orario programmato</p>
            </div>
            <input
              type="checkbox"
              checked={settings.auto_publish}
              onChange={(e) => setSettings(prev => ({ ...prev, auto_publish: e.target.checked }))}
              className="w-5 h-5 rounded text-[#3DAFA8] focus:ring-[#3DAFA8]"
            />
          </label>
        </div>

        {/* Team (solo admin/owner) */}
        {isAdminOrOwner && <TeamSection />}

        {/* Save Button */}
        <button
          onClick={handleSave}
          disabled={saving}
          className="w-full flex items-center justify-center gap-2 bg-[#3DAFA8] text-white px-6 py-3 rounded-xl hover:bg-[#2C3E50] disabled:opacity-50 transition-colors"
        >
          {saving ? (
            <><Loader2 className="animate-spin" size={20} /> Salvataggio...</>
          ) : (
            <><Save size={20} /> Salva Impostazioni</>
          )}
        </button>
      </div>
    </div>
  );
}

function TeamSection() {
  const [teamData, setTeamData] = useState({ users: [], invitations: [] });
  const [showInviteModal, setShowInviteModal] = useState(false);
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteRole, setInviteRole] = useState('editor');
  const [inviting, setInviting] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => { loadTeam(); }, []);

  const loadTeam = async () => {
    try {
      const res = await invitations.list();
      setTeamData(res.data);
    } catch (err) {
      console.error('Error loading team:', err);
    }
  };

  const handleInvite = async (e) => {
    e.preventDefault();
    setInviting(true);
    setError('');
    try {
      await invitations.invite({ email: inviteEmail, role: inviteRole });
      setShowInviteModal(false);
      setInviteEmail('');
      loadTeam();
    } catch (err) {
      setError(err.response?.data?.detail || 'Errore nell\'invio dell\'invito');
    } finally {
      setInviting(false);
    }
  };

  const handleRevoke = async (id) => {
    if (!confirm('Revocare questo invito?')) return;
    try {
      await invitations.revoke(id);
      loadTeam();
    } catch (err) {
      console.error('Error revoking:', err);
    }
  };

  const pendingInvitations = teamData.invitations?.filter(i => !i.is_accepted && !i.is_expired) || [];

  return (
    <div className="bg-white rounded-xl shadow-sm p-6">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-lg flex items-center gap-2">
          <Users className="text-[#3DAFA8]" size={20} /> Team
        </h3>
        <button
          onClick={() => setShowInviteModal(true)}
          className="flex items-center gap-1 text-sm bg-[#3DAFA8] text-white px-3 py-1.5 rounded-lg hover:bg-[#2C3E50] transition-colors"
        >
          <UserPlus size={14} /> Invita utente
        </button>
      </div>

      {/* Utenti attuali */}
      <div className="space-y-2 mb-4">
        <h4 className="text-sm font-medium text-gray-500">Membri ({teamData.users?.length || 0})</h4>
        {teamData.users?.map(u => (
          <div key={u.id} className="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
            <div>
              <span className="font-medium text-sm">{u.full_name || u.email}</span>
              <span className="text-xs text-gray-400 ml-2">{u.email}</span>
            </div>
            <span className="text-xs bg-gray-200 text-gray-600 px-2 py-0.5 rounded-full capitalize">{u.role}</span>
          </div>
        ))}
      </div>

      {/* Inviti pendenti */}
      {pendingInvitations.length > 0 && (
        <div className="space-y-2">
          <h4 className="text-sm font-medium text-gray-500">Inviti pendenti ({pendingInvitations.length})</h4>
          {pendingInvitations.map(inv => (
            <div key={inv.id} className="flex items-center justify-between py-2 px-3 bg-amber-50 rounded-lg border border-amber-100">
              <div className="flex items-center gap-2">
                <Mail size={14} className="text-amber-500" />
                <span className="text-sm">{inv.email}</span>
                <span className="text-xs text-gray-400 capitalize">{inv.role}</span>
              </div>
              <button onClick={() => handleRevoke(inv.id)} className="text-red-400 hover:text-red-600">
                <Trash2 size={14} />
              </button>
            </div>
          ))}
        </div>
      )}

      {/* Modal invito */}
      {showInviteModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 w-full max-w-md mx-4">
            <div className="flex justify-between items-center mb-4">
              <h3 className="font-semibold text-lg">Invita un utente</h3>
              <button onClick={() => setShowInviteModal(false)}><X size={20} /></button>
            </div>
            {error && <div className="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-sm">{error}</div>}
            <form onSubmit={handleInvite} className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Email</label>
                <input
                  type="email" required value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                  placeholder="collega@azienda.it"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Ruolo</label>
                <select
                  value={inviteRole} onChange={(e) => setInviteRole(e.target.value)}
                  className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                >
                  <option value="editor">Editor</option>
                  <option value="admin">Admin</option>
                  <option value="viewer">Viewer</option>
                </select>
              </div>
              <button
                type="submit" disabled={inviting}
                className="w-full bg-[#3DAFA8] text-white py-2 rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 transition-colors"
              >
                {inviting ? 'Invio in corso...' : 'Invia invito'}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}

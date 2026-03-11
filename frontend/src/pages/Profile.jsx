import { useState, useEffect } from 'react';
import { 
  User, Mail, Lock, Save, Loader2, 
  CheckCircle, AlertCircle, Eye, EyeOff, Phone,
  Building, MapPin, Globe, FileText, Key
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

export default function Profile() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [savingPassword, setSavingPassword] = useState(false);
  const [message, setMessage] = useState(null);
  const [showCurrentPassword, setShowCurrentPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  
  const [profile, setProfile] = useState({
    email: '',
    full_name: '',
    role: '',
    organization_name: '',
    phone: '',
    company: '',
    address: '',
    city: '',
    country: '',
    vat_number: '',
    notes: ''
  });
  
  const [passwords, setPasswords] = useState({
    current_password: '',
    new_password: '',
    confirm_password: ''
  });
  
  const token = localStorage.getItem('token');
  
  useEffect(() => {
    fetchProfile();
  }, []);
  
  const fetchProfile = async () => {
    try {
      const res = await fetch(`${API_URL}/auth/me`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (res.ok) {
        const data = await res.json();
        setProfile({
          email: data.email || '',
          full_name: data.full_name || '',
          role: data.role || '',
          organization_name: data.organization_name || '',
          phone: data.phone || '',
          company: data.company || '',
          address: data.address || '',
          city: data.city || '',
          country: data.country || '',
          vat_number: data.vat_number || '',
          notes: data.notes || ''
        });
      }
    } catch (err) {
      console.error('Error fetching profile:', err);
    } finally {
      setLoading(false);
    }
  };
  
  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage(null);
    
    try {
      const res = await fetch(`${API_URL}/auth/profile`, {
        method: 'PUT',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          full_name: profile.full_name,
          phone: profile.phone,
          company: profile.company,
          address: profile.address,
          city: profile.city,
          country: profile.country,
          vat_number: profile.vat_number,
          notes: profile.notes
        })
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore aggiornamento profilo');
      }
      
      setMessage({ type: 'success', text: 'Profilo aggiornato con successo!' });
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setSaving(false);
    }
  };
  
  const handleChangePassword = async (e) => {
    e.preventDefault();
    
    if (passwords.new_password !== passwords.confirm_password) {
      setMessage({ type: 'error', text: 'Le password non coincidono' });
      return;
    }
    
    if (passwords.new_password.length < 8) {
      setMessage({ type: 'error', text: 'La password deve essere di almeno 8 caratteri' });
      return;
    }
    
    setSavingPassword(true);
    setMessage(null);
    
    try {
      const res = await fetch(`${API_URL}/auth/change-password`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          current_password: passwords.current_password,
          new_password: passwords.new_password
        })
      });
      
      if (!res.ok) {
        const err = await res.json();
        throw new Error(err.detail || 'Errore cambio password');
      }
      
      setMessage({ type: 'success', text: 'Password cambiata con successo!' });
      setPasswords({ current_password: '', new_password: '', confirm_password: '' });
    } catch (err) {
      setMessage({ type: 'error', text: err.message });
    } finally {
      setSavingPassword(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
      </div>
    );
  }

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Message */}
      {message && (
        <div className={`p-4 rounded-xl flex items-center gap-3 ${
          message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
        }`}>
          {message.type === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
          {message.text}
        </div>
      )}

      {/* Profile Info Card */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <div className="flex items-center gap-4 mb-6 pb-6 border-b">
          <div className="w-16 h-16 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-2xl flex items-center justify-center">
            <span className="text-2xl font-bold text-white">
              {profile.full_name?.[0] || profile.email?.[0] || 'U'}
            </span>
          </div>
          <div>
            <h2 className="text-xl font-bold text-[#2C3E50]">{profile.full_name || 'Utente'}</h2>
            <p className="text-gray-500">{profile.email}</p>
            <span className="inline-block mt-1 px-2 py-0.5 bg-[#3DAFA8]/10 text-[#3DAFA8] text-xs rounded-full">
              {profile.role || 'user'}
            </span>
          </div>
        </div>

        <form onSubmit={handleUpdateProfile}>
          <h3 className="font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
            <User className="text-[#3DAFA8]" size={18} /> Informazioni Personali
          </h3>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <Mail size={14} className="inline mr-1" /> Email
              </label>
              <input
                type="email"
                value={profile.email}
                disabled
                className="w-full px-3 py-2 bg-gray-100 border rounded-lg text-gray-500 cursor-not-allowed"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
              <input
                type="text"
                value={profile.full_name}
                onChange={(e) => setProfile(prev => ({ ...prev, full_name: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="Mario Rossi"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <Phone size={14} className="inline mr-1" /> Telefono
              </label>
              <input
                type="tel"
                value={profile.phone}
                onChange={(e) => setProfile(prev => ({ ...prev, phone: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="+39 123 456 7890"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <Building size={14} className="inline mr-1" /> Azienda
              </label>
              <input
                type="text"
                value={profile.company}
                onChange={(e) => setProfile(prev => ({ ...prev, company: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="Nome azienda"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <MapPin size={14} className="inline mr-1" /> Indirizzo
              </label>
              <input
                type="text"
                value={profile.address}
                onChange={(e) => setProfile(prev => ({ ...prev, address: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="Via Roma 1"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Città</label>
              <input
                type="text"
                value={profile.city}
                onChange={(e) => setProfile(prev => ({ ...prev, city: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="Milano"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <Globe size={14} className="inline mr-1" /> Paese
              </label>
              <input
                type="text"
                value={profile.country}
                onChange={(e) => setProfile(prev => ({ ...prev, country: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="Italia"
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                <FileText size={14} className="inline mr-1" /> P.IVA
              </label>
              <input
                type="text"
                value={profile.vat_number}
                onChange={(e) => setProfile(prev => ({ ...prev, vat_number: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="IT12345678901"
              />
            </div>
          </div>
          
          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-1">Note</label>
            <textarea
              value={profile.notes}
              onChange={(e) => setProfile(prev => ({ ...prev, notes: e.target.value }))}
              className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent h-24 resize-none"
              placeholder="Note personali..."
            />
          </div>
          
          <button
            type="submit"
            disabled={saving}
            className="flex items-center justify-center gap-2 w-full bg-[#3DAFA8] text-white px-6 py-3 rounded-xl hover:bg-[#2C3E50] disabled:opacity-50 transition-colors"
          >
            {saving ? (
              <><Loader2 className="animate-spin" size={18} /> Salvataggio...</>
            ) : (
              <><Save size={18} /> Salva Profilo</>
            )}
          </button>
        </form>
      </div>

      {/* API Keys */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
        <div className="p-6">
          <h3 className="font-semibold mb-2 flex items-center gap-2">
            <Key size={18} className="text-[#3DAFA8]" /> API Keys
          </h3>
          <p className="text-sm text-gray-500 mb-4">
            Gestisci le chiavi di accesso per integrare il tuo calendario con servizi esterni (MCP Hub, CRM, automazioni).
          </p>
          <a
            href="/api-keys"
            className="inline-flex items-center gap-2 px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50]"
          >
            <Key size={16} />
            Gestisci API Keys
          </a>
        </div>
      </div>
      {/* Change Password Card */}
      <div className="bg-white rounded-xl shadow-sm p-6">
        <h3 className="font-semibold text-[#2C3E50] mb-4 flex items-center gap-2">
          <Lock className="text-[#3DAFA8]" size={18} /> Cambia Password
        </h3>
        
        <form onSubmit={handleChangePassword}>
          <div className="space-y-4 mb-6">
            <div className="relative">
              <label className="block text-sm font-medium text-gray-700 mb-1">Password Attuale</label>
              <input
                type={showCurrentPassword ? 'text' : 'password'}
                value={passwords.current_password}
                onChange={(e) => setPasswords(prev => ({ ...prev, current_password: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent pr-10"
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowCurrentPassword(!showCurrentPassword)}
                className="absolute right-3 top-8 text-gray-400 hover:text-gray-600"
              >
                {showCurrentPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            
            <div className="relative">
              <label className="block text-sm font-medium text-gray-700 mb-1">Nuova Password</label>
              <input
                type={showNewPassword ? 'text' : 'password'}
                value={passwords.new_password}
                onChange={(e) => setPasswords(prev => ({ ...prev, new_password: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent pr-10"
                placeholder="••••••••"
              />
              <button
                type="button"
                onClick={() => setShowNewPassword(!showNewPassword)}
                className="absolute right-3 top-8 text-gray-400 hover:text-gray-600"
              >
                {showNewPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Conferma Nuova Password</label>
              <input
                type="password"
                value={passwords.confirm_password}
                onChange={(e) => setPasswords(prev => ({ ...prev, confirm_password: e.target.value }))}
                className="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                placeholder="••••••••"
              />
            </div>
          </div>
          
          <button
            type="submit"
            disabled={savingPassword || !passwords.current_password || !passwords.new_password}
            className="flex items-center justify-center gap-2 w-full bg-gray-800 text-white px-6 py-3 rounded-xl hover:bg-gray-900 disabled:opacity-50 transition-colors"
          >
            {savingPassword ? (
              <><Loader2 className="animate-spin" size={18} /> Salvataggio...</>
            ) : (
              <><Lock size={18} /> Cambia Password</>
            )}
          </button>
        </form>
      </div>
    </div>
  );
}

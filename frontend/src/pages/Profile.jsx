import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  ArrowLeft, User, Mail, Lock, Save, Loader2, 
  CheckCircle, AlertCircle, Eye, EyeOff, Phone,
  Building, MapPin, Globe, FileText
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '/api';

export default function Profile() {
  const navigate = useNavigate();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [showPassword, setShowPassword] = useState(false);
  
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
      setMessage({ type: 'error', text: 'Le password non corrispondono' });
      return;
    }
    
    if (passwords.new_password.length < 8) {
      setMessage({ type: 'error', text: 'La password deve essere di almeno 8 caratteri' });
      return;
    }
    
    setSaving(true);
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
      setSaving(false);
    }
  };
  
  const getRoleBadge = (role) => {
    const badges = {
      superuser: 'bg-purple-100 text-purple-700',
      admin: 'bg-red-100 text-red-700',
      editor: 'bg-blue-100 text-blue-700',
      viewer: 'bg-gray-100 text-gray-700'
    };
    return badges[role] || 'bg-gray-100 text-gray-700';
  };
  
  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <Loader2 className="animate-spin text-[#3DAFA8]" size={40} />
      </div>
    );
  }
  
  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-3xl mx-auto px-4 py-4">
          <div className="flex items-center gap-4">
            <button onClick={() => navigate('/dashboard')} className="p-2 hover:bg-gray-100 rounded-lg">
              <ArrowLeft size={20} />
            </button>
            <div>
              <h1 className="text-xl font-bold text-[#2C3E50]">Il mio Profilo</h1>
              <p className="text-sm text-gray-500">Gestisci le tue informazioni personali</p>
            </div>
          </div>
        </div>
      </header>
      
      <main className="max-w-3xl mx-auto px-4 py-8">
        {/* Message */}
        {message && (
          <div className={`mb-6 p-4 rounded-lg flex items-center gap-3 ${
            message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
          }`}>
            {message.type === 'success' ? <CheckCircle size={20} /> : <AlertCircle size={20} />}
            {message.text}
          </div>
        )}
        
        {/* Profile Card */}
        <div className="bg-white rounded-xl shadow-sm mb-6">
          <div className="p-6 border-b">
            <div className="flex items-center gap-4">
              <div className="w-16 h-16 bg-[#3DAFA8] rounded-full flex items-center justify-center text-white text-2xl font-bold">
                {profile.full_name?.charAt(0)?.toUpperCase() || profile.email?.charAt(0)?.toUpperCase()}
              </div>
              <div>
                <h2 className="text-xl font-bold">{profile.full_name || 'Utente'}</h2>
                <p className="text-gray-500">{profile.email}</p>
                <div className="flex items-center gap-2 mt-1">
                  <span className={`px-2 py-1 rounded-full text-xs font-medium ${getRoleBadge(profile.role)}`}>
                    {profile.role?.charAt(0).toUpperCase() + profile.role?.slice(1)}
                  </span>
                  {profile.organization_name && (
                    <span className="text-sm text-gray-500">• {profile.organization_name}</span>
                  )}
                </div>
              </div>
            </div>
          </div>
          
          {/* Edit Profile Form */}
          <form onSubmit={handleUpdateProfile} className="p-6">
            <h3 className="font-semibold mb-4 flex items-center gap-2">
              <User size={18} /> Informazioni Personali
            </h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                  <input
                    type="email"
                    value={profile.email}
                    disabled
                    className="w-full pl-10 pr-4 py-2 border rounded-lg bg-gray-50 text-gray-500"
                  />
                </div>
                <p className="text-xs text-gray-400 mt-1">L'email non può essere modificata</p>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Nome Completo</label>
                  <input
                    type="text"
                    value={profile.full_name}
                    onChange={(e) => setProfile({...profile, full_name: e.target.value})}
                    className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="Il tuo nome"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Telefono</label>
                  <div className="relative">
                    <Phone className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                    <input
                      type="tel"
                      value={profile.phone}
                      onChange={(e) => setProfile({...profile, phone: e.target.value})}
                      className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                      placeholder="+39 123 456 7890"
                    />
                  </div>
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Azienda</label>
                <div className="relative">
                  <Building className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                  <input
                    type="text"
                    value={profile.company}
                    onChange={(e) => setProfile({...profile, company: e.target.value})}
                    className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="Nome azienda"
                  />
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Partita IVA</label>
                <div className="relative">
                  <FileText className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                  <input
                    type="text"
                    value={profile.vat_number}
                    onChange={(e) => setProfile({...profile, vat_number: e.target.value})}
                    className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="IT12345678901"
                  />
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Indirizzo</label>
                <div className="relative">
                  <MapPin className="absolute left-3 top-3 text-gray-400" size={18} />
                  <input
                    type="text"
                    value={profile.address}
                    onChange={(e) => setProfile({...profile, address: e.target.value})}
                    className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="Via Roma 1"
                  />
                </div>
              </div>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Città</label>
                  <input
                    type="text"
                    value={profile.city}
                    onChange={(e) => setProfile({...profile, city: e.target.value})}
                    className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="Milano"
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">Paese</label>
                  <div className="relative">
                    <Globe className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                    <input
                      type="text"
                      value={profile.country}
                      onChange={(e) => setProfile({...profile, country: e.target.value})}
                      className="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                      placeholder="Italia"
                    />
                  </div>
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea
                  value={profile.notes}
                  onChange={(e) => setProfile({...profile, notes: e.target.value})}
                  rows={3}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  placeholder="Altre informazioni..."
                />
              </div>
              
              <button
                type="submit"
                disabled={saving}
                className="flex items-center gap-2 px-6 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:opacity-50"
              >
                {saving ? <Loader2 className="animate-spin" size={18} /> : <Save size={18} />}
                Salva Modifiche
              </button>
            </div>
          </form>
        </div>
        
        {/* Change Password */}
        <div className="bg-white rounded-xl shadow-sm">
          <form onSubmit={handleChangePassword} className="p-6">
            <h3 className="font-semibold mb-4 flex items-center gap-2">
              <Lock size={18} /> Cambia Password
            </h3>
            
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Password Attuale</label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    value={passwords.current_password}
                    onChange={(e) => setPasswords({...passwords, current_password: e.target.value})}
                    className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                    placeholder="••••••••"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                  >
                    {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                  </button>
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Nuova Password</label>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={passwords.new_password}
                  onChange={(e) => setPasswords({...passwords, new_password: e.target.value})}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  placeholder="Minimo 8 caratteri"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Conferma Nuova Password</label>
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={passwords.confirm_password}
                  onChange={(e) => setPasswords({...passwords, confirm_password: e.target.value})}
                  className="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
                  placeholder="Ripeti la nuova password"
                />
              </div>
              
              <button
                type="submit"
                disabled={saving || !passwords.current_password || !passwords.new_password}
                className="flex items-center gap-2 px-6 py-2 bg-[#2C3E50] text-white rounded-lg hover:bg-gray-800 disabled:opacity-50"
              >
                {saving ? <Loader2 className="animate-spin" size={18} /> : <Lock size={18} />}
                Cambia Password
              </button>
            </div>
          </form>
        </div>
      </main>
    </div>
  );
}

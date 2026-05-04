import { NavLink, useNavigate } from 'react-router-dom';
import {
  LayoutDashboard,
  Building2,
  Share2,
  Star,
  BarChart3,
  FileText,
  Settings,
  User,
  Shield,
  HelpCircle,
  LogOut,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  ImageIcon,
  TrendingUp,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { useAuthStore } from '../../store/authStore';
import { subscriptions } from '../../services/api';

export default function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);
  const [usage, setUsage] = useState(null);
  const { user, logout } = useAuthStore();
  const navigate = useNavigate();

  useEffect(() => {
    loadUsage();
    const interval = setInterval(loadUsage, 30000);
    return () => clearInterval(interval);
  }, []);

  const loadUsage = async () => {
    try {
      const res = await subscriptions.getUsage();
      setUsage(res.data);
    } catch (err) {
      console.error('Error loading usage:', err);
    }
  };

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  const navItems = [
    { to: '/dashboard',  icon: LayoutDashboard, label: 'Dashboard' },
    { to: '/brands',     icon: Building2,       label: 'I miei Brand' },
    { to: '/audit',      icon: TrendingUp,      label: 'Audit' },
    { to: '/social',     icon: Share2,           label: 'Social' },
    { to: '/reviews',    icon: Star,             label: 'Recensioni' },
    { to: '/insights',   icon: BarChart3,        label: 'Insights' },
    { to: '/documents',  icon: FileText,         label: 'Documenti' },
  ];

  const bottomItems = [
    { to: '/help', icon: HelpCircle, label: 'Assistenza' },
    { to: '/settings', icon: Settings, label: 'Impostazioni' },
    { to: '/profile', icon: User, label: 'Profilo' },
  ];

  const isAdmin = user?.role === 'admin' || user?.role === 'superuser' || user?.is_superuser;

  // Calcola percentuali uso
  const tokensPercentage = usage?.tokens_percentage || 0;
  const imagesPercentage = usage?.images_percentage || 0;
  const tokensUsed = usage?.tokens_used || 0;
  const tokensLimit = usage?.tokens_limit || 0;
  const imagesUsed = usage?.images_used || 0;
  const imagesLimit = usage?.images_limit || 0;

  return (
    <aside className={`fixed left-0 top-0 h-screen bg-[#1a1f2e] text-white flex flex-col transition-all duration-300 z-40 ${
      collapsed ? 'w-16' : 'w-64'
    }`}>
      {/* Logo */}
      <div className="p-4 border-b border-white/10">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-xl flex items-center justify-center flex-shrink-0">
            <LayoutDashboard className="text-white" size={22} />
          </div>
          {!collapsed && (
            <div>
              <h1 className="font-bold text-lg tracking-tight">NOSCITE</h1>
              <p className="text-xs text-gray-400">Calendar</p>
            </div>
          )}
        </div>
      </div>

      {/* Main Navigation */}
      <nav className="flex-1 py-4 overflow-y-auto">
        <ul className="space-y-1 px-2">
          {navItems.map((item) => (
            <li key={item.to}>
              <NavLink
                to={item.to}
                className={({ isActive }) => `
                  flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all
                  ${isActive 
                    ? 'bg-[#3DAFA8] text-white shadow-lg shadow-[#3DAFA8]/20' 
                    : 'text-gray-400 hover:text-white hover:bg-white/5'
                  }
                `}
              >
                <item.icon size={20} className="flex-shrink-0" />
                {!collapsed && <span className="font-medium">{item.label}</span>}
              </NavLink>
            </li>
          ))}
        </ul>

        {/* Divider */}
        <div className="my-4 mx-4 border-t border-white/10" />

        {/* Bottom Navigation */}
        <ul className="space-y-1 px-2">
          {bottomItems.map((item) => (
            <li key={item.to}>
              <NavLink
                to={item.to}
                className={({ isActive }) => `
                  flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all
                  ${isActive 
                    ? 'bg-[#3DAFA8] text-white' 
                    : 'text-gray-400 hover:text-white hover:bg-white/5'
                  }
                `}
              >
                <item.icon size={20} className="flex-shrink-0" />
                {!collapsed && <span className="font-medium">{item.label}</span>}
              </NavLink>
            </li>
          ))}
          
          {isAdmin && (
            <li>
              <NavLink
                to="/admin"
                className={({ isActive }) => `
                  flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all
                  ${isActive 
                    ? 'bg-amber-500 text-white' 
                    : 'text-amber-400 hover:text-amber-300 hover:bg-amber-500/10'
                  }
                `}
              >
                <Shield size={20} className="flex-shrink-0" />
                {!collapsed && <span className="font-medium">Admin</span>}
              </NavLink>
            </li>
          )}
        </ul>
      </nav>

      {/* Usage Stats */}
      {!collapsed && (
        <div className="px-4 py-3 border-t border-white/10">
          <div className="space-y-3">

            {/* Contenuto AI (token → giorni leggibili) */}
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400 flex items-center gap-1">
                  <Sparkles size={12} /> Contenuto AI
                </span>
                <span className="text-gray-300">
                  {tokensLimit === -1 ? '∞' : `${Math.round(tokensPercentage)}%`}
                </span>
              </div>
              <div className="h-1.5 bg-white/10 rounded-full overflow-hidden">
                <div
                  className="h-full rounded-full transition-all"
                  style={{
                    width: `${tokensLimit === -1 ? 0 : Math.min(tokensPercentage, 100)}%`,
                    background: tokensPercentage >= 90
                      ? 'linear-gradient(to right, #ef4444, #dc2626)'
                      : tokensPercentage >= 70
                        ? 'linear-gradient(to right, #f97316, #ea580c)'
                        : 'linear-gradient(to right, #3DAFA8, #34d399)',
                  }}
                />
              </div>
              {tokensLimit === -1 ? (
                <p className="text-[10px] text-gray-500 mt-0.5">Illimitato</p>
              ) : (
                <p className="text-[10px] text-gray-500 mt-0.5">
                  ~{Math.round(tokensUsed / 1500)} giorni generati su ~{Math.round(tokensLimit / 1500)} disponibili
                </p>
              )}
            </div>

            {/* Immagini */}
            <div>
              <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400 flex items-center gap-1">
                  <ImageIcon size={12} /> Immagini
                </span>
                <span className="text-gray-300">
                  {imagesLimit === -1 ? '∞' : `${Math.round(imagesPercentage)}%`}
                </span>
              </div>
              <div className="h-1.5 bg-white/10 rounded-full overflow-hidden">
                <div
                  className="h-full rounded-full transition-all"
                  style={{
                    width: `${imagesLimit === -1 ? 0 : Math.min(imagesPercentage, 100)}%`,
                    background: imagesPercentage >= 90
                      ? 'linear-gradient(to right, #ef4444, #dc2626)'
                      : imagesPercentage >= 70
                        ? 'linear-gradient(to right, #f97316, #ea580c)'
                        : 'linear-gradient(to right, #E89548, #f59e0b)',
                  }}
                />
              </div>
              {imagesLimit === -1 ? (
                <p className="text-[10px] text-gray-500 mt-0.5">Illimitate</p>
              ) : (
                <p className="text-[10px] text-gray-500 mt-0.5">
                  {imagesUsed} su {imagesLimit} immagini usate
                </p>
              )}
            </div>

          </div>
        </div>
      )}

      {/* User & Logout */}
      <div className="p-3 border-t border-white/10">
        <div className={`flex items-center ${collapsed ? 'justify-center' : 'justify-between'}`}>
          {!collapsed && (
            <div className="flex items-center gap-2 min-w-0">
              <div className="w-8 h-8 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-full flex items-center justify-center flex-shrink-0">
                <span className="text-sm font-bold">{user?.full_name?.[0] || user?.email?.[0] || 'U'}</span>
              </div>
              <div className="min-w-0">
                <p className="text-sm font-medium truncate">{user?.full_name || 'Utente'}</p>
                <p className="text-xs text-gray-400 truncate">{user?.email}</p>
              </div>
            </div>
          )}
          <button
            onClick={handleLogout}
            className="p-2 text-gray-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-all"
            title="Esci"
          >
            <LogOut size={18} />
          </button>
        </div>
      </div>

      {/* Collapse Toggle */}
      <button
        onClick={() => setCollapsed(!collapsed)}
        className="absolute -right-3 top-20 w-6 h-6 bg-[#1a1f2e] border border-white/20 rounded-full flex items-center justify-center text-gray-400 hover:text-white hover:bg-[#3DAFA8] transition-all"
      >
        {collapsed ? <ChevronRight size={14} /> : <ChevronLeft size={14} />}
      </button>

      {/* Version */}
      {!collapsed && (
        <div className="px-4 py-2 text-xs text-gray-500 text-center">
          Noscite v1.0.0
        </div>
      )}
    </aside>
  );
}

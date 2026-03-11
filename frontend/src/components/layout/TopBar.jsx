import { HelpCircle, Search } from 'lucide-react';
import NotificationBell from '../NotificationBell';
import { useLocation } from 'react-router-dom';

const pageTitles = {
  '/dashboard': { title: 'Dashboard', subtitle: 'Panoramica delle tue attività' },
  '/brands': { title: 'I miei Brand', subtitle: 'Gestisci i tuoi brand' },
  '/calendars': { title: 'Calendari', subtitle: 'I tuoi calendari editoriali' },
  '/social': { title: 'Social', subtitle: 'Connessioni social media' },
  '/documents': { title: 'Documenti', subtitle: 'Knowledge base' },
  '/ai-assistant': { title: 'AI Assistant', subtitle: 'Assistente vocale AI' },
  '/settings': { title: 'Impostazioni', subtitle: 'Configura il tuo account' },
  '/profile': { title: 'Profilo', subtitle: 'I tuoi dati personali' },
  '/admin': { title: 'Admin', subtitle: 'Pannello di amministrazione' },
};

export default function TopBar() {
  const location = useLocation();
  
  // Get page info, handle dynamic routes
  const getPageInfo = () => {
    const path = location.pathname;
    
    // Check exact match first
    if (pageTitles[path]) return pageTitles[path];
    
    // Check for dynamic routes
    if (path.startsWith('/brand/')) {
      return { title: 'Dettaglio Brand', subtitle: 'Gestisci il tuo brand' };
    }
    if (path.startsWith('/project/')) {
      return { title: 'Calendario', subtitle: 'Gestisci il calendario editoriale' };
    }
    if (path.includes('/voice-interview')) {
      return { title: 'Intervista AI', subtitle: 'Crea il tuo profilo brand' };
    }
    if (path.includes('/new-project')) {
      return { title: 'Nuovo Calendario', subtitle: 'Crea un nuovo calendario editoriale' };
    }
    
    return { title: 'Noscite Calendar', subtitle: '' };
  };

  const pageInfo = getPageInfo();

  return (
    <header className="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6 sticky top-0 z-[60]">
      {/* Left: Page Title */}
      <div>
        <h1 className="text-xl font-bold text-[#2C3E50]">{pageInfo.title}</h1>
        {pageInfo.subtitle && (
          <p className="text-sm text-gray-500">{pageInfo.subtitle}</p>
        )}
      </div>

      {/* Right: Actions */}
      <div className="flex items-center gap-3">
        {/* Search */}
        <div className="relative hidden md:block">
          <Search size={18} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input
            type="text"
            placeholder="Cerca..."
            className="pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-lg text-sm focus:ring-2 focus:ring-[#3DAFA8] focus:bg-white transition-all w-64"
          />
        </div>

        {/* Notifications */}
        <NotificationBell />

        {/* Help */}
        <button className="p-2 text-gray-500 hover:text-[#3DAFA8] hover:bg-gray-100 rounded-lg transition-all">
          <HelpCircle size={20} />
        </button>
      </div>
    </header>
  );
}

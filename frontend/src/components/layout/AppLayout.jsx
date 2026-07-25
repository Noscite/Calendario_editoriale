import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import Sidebar from './Sidebar';
import TopBar from './TopBar';
import SupportChat from '../SupportChat';

export default function AppLayout() {
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Sidebar: fissa su desktop, drawer off-canvas su mobile */}
      <Sidebar mobileOpen={mobileOpen} onClose={() => setMobileOpen(false)} />

      {/* Backdrop del drawer (solo mobile, quando aperto) */}
      {mobileOpen && (
        <div
          className="fixed inset-0 bg-black/40 z-[65] md:hidden"
          onClick={() => setMobileOpen(false)}
          aria-hidden="true"
        />
      )}

      {/* Contenuto: nessun offset su mobile, offset sidebar su desktop */}
      <div className="ml-0 md:ml-64 transition-all duration-300">
        <TopBar onMenuClick={() => setMobileOpen(true)} />
        <main className="p-4 md:p-6">
          <Outlet />
        </main>
      </div>
      <SupportChat />
    </div>
  );
}

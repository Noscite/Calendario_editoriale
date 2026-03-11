import { useState } from 'react';
import { Settings, Bell, Palette, Globe, Shield, Save, Loader2 } from 'lucide-react';

export default function SettingsPage() {
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

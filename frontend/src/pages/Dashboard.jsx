import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuthStore } from '../store/authStore';
import { useDataStore } from '../store/dataStore';
import { Plus, Calendar, Building2, LogOut, Sparkles, Trash2, X, Shield, User } from 'lucide-react';

export default function Dashboard() {
  const { user, logout } = useAuthStore();
  const { brands, fetchBrands, createBrand, deleteBrand, isLoading } = useDataStore();
  const [showNewBrand, setShowNewBrand] = useState(false);
  const [newBrand, setNewBrand] = useState({ name: '', sector: '', tone_of_voice: '' });
  const [deleteConfirm, setDeleteConfirm] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    fetchBrands();
  }, []);

  const handleCreateBrand = async (e) => {
    e.preventDefault();
    const result = await createBrand(newBrand);
    if (result.success) {
      setShowNewBrand(false);
      setNewBrand({ name: '', sector: '', tone_of_voice: '' });
    }
  };

  const handleDelete = async (id) => {
    const result = await deleteBrand(id);
    if (result.success) {
      setDeleteConfirm(null);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <header className="bg-white shadow-sm">
        <div className="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-[#2C3E50]">Noscite Calendar</h1>
            <p className="text-sm text-gray-500">Benvenuto, {user?.full_name || user?.email}</p>
          </div>
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate('/profile')}
              className="flex items-center gap-2 text-gray-600 hover:text-[#3DAFA8]"
              title="Il mio Profilo"
            >
              <User size={20} />
            </button>
            <button
              onClick={() => navigate('/admin')}
              className="flex items-center gap-2 text-gray-600 hover:text-[#3DAFA8]"
              title="Admin Dashboard"
            >
              <Shield size={20} />
            </button>
            <button
              onClick={() => { logout(); navigate('/login'); }}
              className="flex items-center gap-2 text-gray-600 hover:text-red-600"
            >
              <LogOut size={20} /> Esci
            </button>
          </div>
        </div>
      </header>

      <main className="max-w-7xl mx-auto px-4 py-8">
        {/* Brands Section */}
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-semibold text-[#2C3E50] flex items-center gap-2">
            <Building2 className="text-[#3DAFA8]" /> I tuoi Brand
          </h2>
          <button
            onClick={() => setShowNewBrand(true)}
            className="flex items-center gap-2 bg-[#3DAFA8] text-white px-4 py-2 rounded-lg hover:bg-[#2C3E50] transition-colors"
          >
            <Plus size={20} /> Nuovo Brand
          </button>
        </div>

        {/* New Brand Form */}
        {showNewBrand && (
          <div className="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 className="text-lg font-semibold mb-4">Crea nuovo Brand</h3>
            <form onSubmit={handleCreateBrand} className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <input
                type="text"
                placeholder="Nome brand *"
                value={newBrand.name}
                onChange={(e) => setNewBrand({...newBrand, name: e.target.value})}
                className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
                required
              />
              <input
                type="text"
                placeholder="Settore"
                value={newBrand.sector}
                onChange={(e) => setNewBrand({...newBrand, sector: e.target.value})}
                className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
              />
              <select
                value={newBrand.tone_of_voice}
                onChange={(e) => setNewBrand({...newBrand, tone_of_voice: e.target.value})}
                className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8]"
              >
                <option value="">Tono di voce</option>
                <option value="formale">Formale</option>
                <option value="informale">Informale</option>
                <option value="tecnico">Tecnico</option>
                <option value="amichevole">Amichevole</option>
              </select>
              <div className="md:col-span-3 flex gap-2">
                <button type="submit" className="bg-[#3DAFA8] text-white px-6 py-2 rounded-lg hover:bg-[#2C3E50]">
                  Crea Brand
                </button>
                <button type="button" onClick={() => setShowNewBrand(false)} className="px-6 py-2 border rounded-lg">
                  Annulla
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Delete Confirmation Modal */}
        {deleteConfirm && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4">
              <h3 className="text-lg font-semibold text-red-600 mb-2">Conferma eliminazione</h3>
              <p className="text-gray-600 mb-4">
                Sei sicuro di voler eliminare questo brand? Verranno eliminati anche tutti i progetti e i post associati.
              </p>
              <div className="flex gap-2 justify-end">
                <button
                  onClick={() => setDeleteConfirm(null)}
                  className="px-4 py-2 border rounded-lg"
                >
                  Annulla
                </button>
                <button
                  onClick={() => handleDelete(deleteConfirm)}
                  className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                >
                  Elimina
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Brands Grid */}
        {isLoading ? (
          <div className="text-center py-12 text-gray-500">Caricamento...</div>
        ) : brands.length === 0 ? (
          <div className="bg-white rounded-xl shadow-sm p-12 text-center">
            <Building2 size={48} className="mx-auto text-gray-300 mb-4" />
            <h3 className="text-lg font-medium text-gray-600">Nessun brand</h3>
            <p className="text-gray-400 mt-1">Crea il tuo primo brand per iniziare</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {brands.map((brand) => (
              <div
                key={brand.id}
                className="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow border-l-4 border-[#3DAFA8] relative group"
              >
                <button
                  onClick={(e) => { e.stopPropagation(); setDeleteConfirm(brand.id); }}
                  className="absolute top-3 right-3 p-2 text-gray-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <Trash2 size={18} />
                </button>
                <div onClick={() => navigate(`/brand/${brand.id}`)} className="cursor-pointer">
                  <h3 className="text-lg font-semibold text-[#2C3E50]">{brand.name}</h3>
                  <p className="text-sm text-gray-500 mt-1">{brand.sector || 'Settore non specificato'}</p>
                  <div className="flex items-center gap-4 mt-4 text-sm text-gray-400">
                    <span className="flex items-center gap-1">
                      <Calendar size={16} /> Progetti
                    </span>
                    <span className="flex items-center gap-1">
                      <Sparkles size={16} /> {brand.tone_of_voice || 'Standard'}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </main>
    </div>
  );
}

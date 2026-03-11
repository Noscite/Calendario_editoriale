import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { Building2, Plus, Trash2, Calendar, Loader2, ExternalLink } from "lucide-react";
import { brands as brandsApi } from "../services/api";

export default function BrandsPage() {
  const navigate = useNavigate();
  const [brands, setBrands] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showNewBrand, setShowNewBrand] = useState(false);
  const [newBrand, setNewBrand] = useState({ name: "", sector: "", website: "" });
  const [creating, setCreating] = useState(false);

  useEffect(() => {
    loadBrands();
  }, []);

  const loadBrands = async () => {
    try {
      const res = await brandsApi.list();
      setBrands(res.data);
    } catch (err) {
      console.error("Error loading brands:", err);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBrand = async (e) => {
    e.preventDefault();
    if (!newBrand.name.trim()) return;
    setCreating(true);
    try {
      const res = await brandsApi.create(newBrand);
      setBrands(prev => [...prev, res.data]);
      setNewBrand({ name: "", sector: "", website: "" });
      setShowNewBrand(false);
    } catch (err) {
      alert("Errore nella creazione del brand");
    } finally {
      setCreating(false);
    }
  };

  const handleDeleteBrand = async (e, brandId) => {
    e.stopPropagation();
    if (!confirm("Eliminare questo brand e tutti i suoi calendari?")) return;
    try {
      await brandsApi.delete(brandId);
      setBrands(prev => prev.filter(b => b.id !== brandId));
    } catch (err) {
      alert("Errore nell eliminazione");
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
    <div className="max-w-6xl mx-auto">
      <div className="flex justify-between items-center mb-6">
        <div>
          <p className="text-gray-500">Gestisci i tuoi brand e i loro calendari editoriali</p>
        </div>
        <button
          onClick={() => setShowNewBrand(true)}
          className="flex items-center gap-2 bg-[#3DAFA8] text-white px-4 py-2 rounded-lg hover:bg-[#2C3E50] transition-colors"
        >
          <Plus size={20} /> Nuovo Brand
        </button>
      </div>

      {showNewBrand && (
        <div className="bg-white rounded-xl shadow-sm p-6 mb-6 border-l-4 border-[#3DAFA8]">
          <h3 className="text-lg font-semibold mb-4 flex items-center gap-2">
            <Building2 className="text-[#3DAFA8]" /> Crea nuovo Brand
          </h3>
          <form onSubmit={handleCreateBrand} className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input
              type="text"
              placeholder="Nome brand *"
              value={newBrand.name}
              onChange={(e) => setNewBrand({...newBrand, name: e.target.value})}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
              required
            />
            <input
              type="text"
              placeholder="Settore"
              value={newBrand.sector}
              onChange={(e) => setNewBrand({...newBrand, sector: e.target.value})}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
            <input
              type="url"
              placeholder="Website"
              value={newBrand.website}
              onChange={(e) => setNewBrand({...newBrand, website: e.target.value})}
              className="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-[#3DAFA8] focus:border-transparent"
            />
            <div className="flex gap-2">
              <button
                type="submit"
                disabled={creating}
                className="flex-1 bg-[#3DAFA8] text-white px-4 py-2 rounded-lg hover:bg-[#2C3E50] disabled:opacity-50 transition-colors"
              >
                {creating ? <Loader2 className="animate-spin mx-auto" size={20} /> : "Crea"}
              </button>
              <button
                type="button"
                onClick={() => setShowNewBrand(false)}
                className="px-4 py-2 border rounded-lg hover:bg-gray-50 transition-colors"
              >
                Annulla
              </button>
            </div>
          </form>
        </div>
      )}

      {brands.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center shadow-sm">
          <Building2 size={48} className="mx-auto text-gray-300 mb-4" />
          <h3 className="text-lg font-medium text-gray-700 mb-2">Nessun brand</h3>
          <p className="text-gray-500 mb-6">Crea il tuo primo brand per iniziare</p>
          <button
            onClick={() => setShowNewBrand(true)}
            className="inline-flex items-center gap-2 bg-[#3DAFA8] text-white px-6 py-3 rounded-lg hover:bg-[#2C3E50] transition-colors"
          >
            <Plus size={20} /> Crea Brand
          </button>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {brands.map(brand => (
            <div
              key={brand.id}
              onClick={() => navigate("/brand/" + brand.id)}
              className="bg-white rounded-xl p-6 shadow-sm hover:shadow-md transition-all cursor-pointer group border border-transparent hover:border-[#3DAFA8]/30"
            >
              <div className="flex items-start justify-between mb-4">
                <div className="w-12 h-12 bg-gradient-to-br from-[#3DAFA8] to-[#2C3E50] rounded-xl flex items-center justify-center">
                  <Building2 className="text-white" size={24} />
                </div>
                <button
                  onClick={(e) => handleDeleteBrand(e, brand.id)}
                  className="p-2 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
                >
                  <Trash2 size={18} />
                </button>
              </div>
              <h3 className="font-semibold text-[#2C3E50] text-lg mb-1">{brand.name}</h3>
              <p className="text-sm text-gray-500 mb-4">{brand.sector || "Nessun settore"}</p>
              <div className="flex items-center justify-between pt-4 border-t">
                <div className="flex items-center gap-2 text-sm text-gray-500">
                  <Calendar size={16} />
                  <span>{brand.projects_count || 0} calendari</span>
                </div>
                {brand.website && (
                  <a href={brand.website}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                    className="text-[#3DAFA8] hover:text-[#2C3E50] transition-colors"
                  >
                    <ExternalLink size={16} />
                  </a>
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

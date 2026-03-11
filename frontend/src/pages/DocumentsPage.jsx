import { useState, useEffect } from 'react';
import { FileText, Upload, Trash2, Loader2, File, Building2 } from 'lucide-react';
import { brands as brandsApi, documents as docsApi } from '../services/api';

export default function DocumentsPage() {
  const [brands, setBrands] = useState([]);
  const [selectedBrand, setSelectedBrand] = useState(null);
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    loadBrands();
  }, []);

  useEffect(() => {
    if (selectedBrand) {
      loadDocuments(selectedBrand);
    }
  }, [selectedBrand]);

  const loadBrands = async () => {
    try {
      const res = await brandsApi.list();
      setBrands(res.data);
      if (res.data.length > 0) {
        setSelectedBrand(res.data[0].id);
      }
    } catch (err) {
      console.error('Error loading brands:', err);
    } finally {
      setLoading(false);
    }
  };

  const loadDocuments = async (brandId) => {
    try {
      const res = await docsApi.list(brandId);
      setDocuments(res.data);
    } catch (err) {
      console.error('Error loading documents:', err);
      setDocuments([]);
    }
  };

  const handleUpload = async (e) => {
    const files = e.target.files;
    if (!files || !selectedBrand) return;

    setUploading(true);
    try {
      for (const file of files) {
        const formData = new FormData();
        formData.append('file', file);
        await docsApi.upload(selectedBrand, formData);
      }
      loadDocuments(selectedBrand);
    } catch (err) {
      alert('Errore nel caricamento');
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (docId) => {
    if (!confirm('Eliminare questo documento?')) return;
    try {
      await docsApi.delete(docId);
      setDocuments(prev => prev.filter(d => d.id !== docId));
    } catch (err) {
      alert('Errore nell\'eliminazione');
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
      <div className="mb-6">
        <p className="text-gray-500">Knowledge base per migliorare la generazione AI</p>
      </div>

      {brands.length === 0 ? (
        <div className="bg-white rounded-xl p-12 text-center shadow-sm">
          <FileText size={48} className="mx-auto text-gray-300 mb-4" />
          <h3 className="text-lg font-medium text-gray-700 mb-2">Nessun brand</h3>
          <p className="text-gray-500">Crea prima un brand per caricare documenti</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
          {/* Brand Selector */}
          <div className="lg:col-span-1">
            <div className="bg-white rounded-xl shadow-sm p-4">
              <h3 className="font-semibold text-sm text-gray-500 mb-3">SELEZIONA BRAND</h3>
              <div className="space-y-2">
                {brands.map(brand => (
                  <button
                    key={brand.id}
                    onClick={() => setSelectedBrand(brand.id)}
                    className={`w-full text-left px-3 py-2 rounded-lg transition-all flex items-center gap-2 ${
                      selectedBrand === brand.id
                        ? 'bg-[#3DAFA8] text-white'
                        : 'hover:bg-gray-100'
                    }`}
                  >
                    <Building2 size={16} />
                    {brand.name}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Documents */}
          <div className="lg:col-span-3">
            <div className="bg-white rounded-xl shadow-sm p-6">
              {/* Upload */}
              <div className="mb-6">
                <label className="flex items-center justify-center gap-2 px-4 py-8 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-[#3DAFA8] hover:bg-[#3DAFA8]/5 transition-all">
                  <input
                    type="file"
                    multiple
                    onChange={handleUpload}
                    className="hidden"
                    accept=".pdf,.doc,.docx,.txt"
                  />
                  {uploading ? (
                    <Loader2 className="animate-spin text-[#3DAFA8]" />
                  ) : (
                    <>
                      <Upload className="text-gray-400" />
                      <span className="text-gray-500">Carica documenti (PDF, DOC, TXT)</span>
                    </>
                  )}
                </label>
              </div>

              {/* Documents List */}
              {documents.length === 0 ? (
                <p className="text-center text-gray-500 py-8">Nessun documento caricato</p>
              ) : (
                <div className="space-y-2">
                  {documents.map(doc => (
                    <div
                      key={doc.id}
                      className="flex items-center justify-between p-3 bg-gray-50 rounded-lg group"
                    >
                      <div className="flex items-center gap-3">
                        <File className="text-[#3DAFA8]" size={20} />
                        <div>
                          <p className="font-medium text-sm">{doc.filename}</p>
                          <p className="text-xs text-gray-400">
                            {new Date(doc.created_at).toLocaleDateString('it-IT')}
                          </p>
                        </div>
                      </div>
                      <button
                        onClick={() => handleDelete(doc.id)}
                        className="p-2 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
                      >
                        <Trash2 size={16} />
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

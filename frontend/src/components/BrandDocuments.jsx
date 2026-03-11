import { useState, useEffect, useCallback } from 'react';
import { 
  Upload, FileText, Trash2, RefreshCw, Search, 
  CheckCircle, AlertCircle, Loader2, File, X,
  BookOpen, Presentation, FileSpreadsheet, Image
} from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || '';

const DOCUMENT_TYPES = {
  brand_guidelines: { label: 'Linee Guida Brand', color: 'bg-purple-100 text-purple-700', icon: BookOpen },
  company_presentation: { label: 'Presentazione Aziendale', color: 'bg-blue-100 text-blue-700', icon: Presentation },
  product_info: { label: 'Info Prodotto', color: 'bg-green-100 text-green-700', icon: FileSpreadsheet },
  case_study: { label: 'Case Study', color: 'bg-orange-100 text-orange-700', icon: FileText },
  marketing_material: { label: 'Materiale Marketing', color: 'bg-pink-100 text-pink-700', icon: Image },
  internal_docs: { label: 'Documenti Interni', color: 'bg-gray-100 text-gray-700', icon: File },
  blog_content: { label: 'Contenuti Blog', color: 'bg-teal-100 text-teal-700', icon: FileText },
  press_release: { label: 'Comunicati Stampa', color: 'bg-yellow-100 text-yellow-700', icon: FileText },
  other: { label: 'Altro', color: 'bg-gray-100 text-gray-600', icon: File }
};

const FILE_ICONS = {
  pdf: '📄',
  docx: '📝',
  doc: '📝',
  pptx: '📊',
  ppt: '📊',
  txt: '📃',
  md: '📃'
};

export default function BrandDocuments({ brandId }) {
  const [documents, setDocuments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState(null);
  const [searching, setSearching] = useState(false);
  const [dragOver, setDragOver] = useState(false);
  const [message, setMessage] = useState(null);

  const token = localStorage.getItem('token');

  const loadDocuments = async () => {
    setMessage(null);
    try {
      const response = await fetch(`${API_URL}/api/documents/list/${brandId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
      });
      if (response.ok) {
        const data = await response.json();
        setDocuments(data);
      }
    } catch (error) {
      console.error('Error loading documents:', error);
      setMessage({ type: 'error', text: 'Errore caricamento documenti' });
    }
    setLoading(false);
  };

  useEffect(() => {
    if (brandId) loadDocuments();
  }, [brandId]);

  const handleUpload = async (files) => {
    if (!files || files.length === 0) return;
    
    setUploading(true);
    setMessage(null);
    
    for (const file of files) {
      const formData = new FormData();
      formData.append('file', file);
      
      try {
        const response = await fetch(`${API_URL}/api/documents/upload/${brandId}`, {
          method: 'POST',
          headers: { 'Authorization': `Bearer ${token}` },
          body: formData
        });
        
        if (response.ok) {
          setMessage({ type: 'success', text: `${file.name} caricato! Elaborazione in corso...` });
        } else {
          const err = await response.json();
          setMessage({ type: 'error', text: err.detail || 'Errore upload' });
        }
      } catch (error) {
        setMessage({ type: 'error', text: 'Errore di connessione' });
      }
    }
    
    setUploading(false);
    setTimeout(() => loadDocuments(), 2000);
  };

  const handleDelete = async (docId, filename) => {
    if (!window.confirm(`Eliminare "${filename}"?`)) return;
    
    try {
      await fetch(`${API_URL}/api/documents/${docId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      loadDocuments();
    } catch (error) {
      console.error('Error deleting:', error);
    }
  };

  const handleReprocess = async (docId) => {
    try {
      await fetch(`${API_URL}/api/documents/reprocess/${docId}`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` }
      });
      setMessage({ type: 'success', text: 'Riprocessamento avviato...' });
      setTimeout(() => loadDocuments(), 3000);
    } catch (error) {
      console.error('Error reprocessing:', error);
    }
  };

  const handleSearch = async () => {
    if (!searchQuery.trim()) return;
    
    setSearching(true);
    try {
      const response = await fetch(`${API_URL}/api/documents/search/${brandId}`, {
        method: 'POST',
        headers: { 
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ query: searchQuery, limit: 5 })
      });
      
      if (response.ok) {
        const data = await response.json();
        setSearchResults(data.results);
      }
    } catch (error) {
      console.error('Error searching:', error);
    }
    setSearching(false);
  };

  const handleDrop = useCallback((e) => {
    e.preventDefault();
    setDragOver(false);
    const files = Array.from(e.dataTransfer.files);
    handleUpload(files);
  }, [brandId]);

  const handleDragOver = (e) => {
    e.preventDefault();
    setDragOver(true);
  };

  const handleDragLeave = () => {
    setDragOver(false);
  };

  const getDocTypeInfo = (doc) => {
    const type = doc.key_topics?.document_type || 'other';
    return DOCUMENT_TYPES[type] || DOCUMENT_TYPES.other;
  };

  const formatFileSize = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-[#2C3E50] flex items-center gap-2">
            <FileText className="text-[#3DAFA8]" size={20} />
            Knowledge Base
          </h3>
          <p className="text-sm text-gray-500">
            Carica documenti aziendali per migliorare la generazione dei contenuti
          </p>
        </div>
        <span className="text-sm text-gray-400">
          {documents.length} documenti
        </span>
      </div>

      {/* Message */}
      {message && (
        <div className={`p-3 rounded-lg flex items-center gap-2 ${
          message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
        }`}>
          {message.type === 'success' ? <CheckCircle size={18} /> : <AlertCircle size={18} />}
          {message.text}
          <button onClick={() => setMessage(null)} className="ml-auto">
            <X size={16} />
          </button>
        </div>
      )}

      {/* Upload Area */}
      <div
        onDrop={handleDrop}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors ${
          dragOver 
            ? 'border-[#3DAFA8] bg-[#3DAFA8]/5' 
            : 'border-gray-300 hover:border-gray-400'
        }`}
      >
        {uploading ? (
          <div className="flex flex-col items-center gap-2">
            <Loader2 className="animate-spin text-[#3DAFA8]" size={32} />
            <span className="text-gray-600">Caricamento in corso...</span>
          </div>
        ) : (
          <>
            <Upload className="mx-auto text-gray-400 mb-3" size={32} />
            <p className="text-gray-600 mb-2">
              Trascina qui i tuoi documenti o{' '}
              <label className="text-[#3DAFA8] cursor-pointer hover:underline">
                sfoglia
                <input
                  type="file"
                  multiple
                  accept=".pdf,.docx,.doc,.pptx,.ppt,.txt,.md"
                  className="hidden"
                  onChange={(e) => handleUpload(Array.from(e.target.files))}
                />
              </label>
            </p>
            <p className="text-xs text-gray-400">
              PDF, DOCX, PPTX, TXT, MD • Max 20MB per file
            </p>
          </>
        )}
      </div>

      {/* Search */}
      <div className="flex gap-2">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyPress={(e) => e.key === 'Enter' && handleSearch()}
            placeholder="Cerca nella knowledge base..."
            className="w-full pl-10 pr-4 py-2 border rounded-lg focus:border-[#3DAFA8] focus:outline-none"
          />
        </div>
        <button
          onClick={handleSearch}
          disabled={searching || !searchQuery.trim()}
          className="px-4 py-2 bg-[#3DAFA8] text-white rounded-lg hover:bg-[#2C3E50] disabled:bg-gray-300 flex items-center gap-2"
        >
          {searching ? <Loader2 className="animate-spin" size={18} /> : <Search size={18} />}
          Cerca
        </button>
      </div>

      {/* Search Results */}
      {searchResults && (
        <div className="bg-blue-50 rounded-xl p-4">
          <div className="flex justify-between items-center mb-3">
            <h4 className="font-medium text-blue-800">Risultati ricerca</h4>
            <button onClick={() => setSearchResults(null)} className="text-blue-600 hover:text-blue-800">
              <X size={18} />
            </button>
          </div>
          {searchResults.length === 0 ? (
            <p className="text-blue-600 text-sm">Nessun risultato trovato</p>
          ) : (
            <div className="space-y-3">
              {searchResults.map((result, idx) => (
                <div key={idx} className="bg-white rounded-lg p-3 text-sm">
                  <div className="flex justify-between items-start mb-1">
                    <span className="font-medium text-gray-700">{result.filename}</span>
                    <span className="text-xs text-green-600 bg-green-50 px-2 py-0.5 rounded">
                      {(result.similarity * 100).toFixed(0)}% match
                    </span>
                  </div>
                  <p className="text-gray-600 line-clamp-3">{result.content}</p>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Documents List */}
      {loading ? (
        <div className="text-center py-8">
          <Loader2 className="animate-spin mx-auto text-gray-400" size={32} />
        </div>
      ) : documents.length === 0 ? (
        <div className="text-center py-8 text-gray-500">
          <FileText className="mx-auto mb-2 text-gray-300" size={40} />
          <p>Nessun documento caricato</p>
        </div>
      ) : (
        <div className="space-y-3">
          {documents.map((doc) => {
            const typeInfo = getDocTypeInfo(doc);
            const TypeIcon = typeInfo.icon;
            const isProcessing = doc.extraction_status === 'processing' || doc.analysis_status === 'processing';
            const hasFailed = doc.extraction_status === 'failed' || doc.analysis_status === 'failed';
            
            return (
              <div 
                key={doc.id} 
                className={`bg-white rounded-xl p-4 border ${hasFailed ? 'border-red-200' : 'border-gray-100'} hover:shadow-md transition-shadow`}
              >
                <div className="flex gap-4">
                  {/* Icon */}
                  <div className="text-3xl">
                    {FILE_ICONS[doc.file_type] || '📄'}
                  </div>
                  
                  {/* Content */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <h4 className="font-medium text-[#2C3E50] truncate">
                          {doc.original_filename}
                        </h4>
                        <div className="flex items-center gap-2 mt-1 flex-wrap">
                          <span className={`text-xs px-2 py-0.5 rounded-full ${typeInfo.color}`}>
                            {typeInfo.label}
                          </span>
                          <span className="text-xs text-gray-400">
                            {formatFileSize(doc.file_size)}
                          </span>
                          {doc.chunks_count > 0 && (
                            <span className="text-xs text-gray-400">
                              {doc.chunks_count} chunks
                            </span>
                          )}
                        </div>
                      </div>
                      
                      {/* Actions */}
                      <div className="flex items-center gap-1">
                        {isProcessing && (
                          <Loader2 className="animate-spin text-[#3DAFA8]" size={18} />
                        )}
                        {hasFailed && (
                          <button
                            onClick={() => handleReprocess(doc.id)}
                            className="p-1.5 text-orange-500 hover:bg-orange-50 rounded"
                            title="Riprova elaborazione"
                          >
                            <RefreshCw size={16} />
                          </button>
                        )}
                        <button
                          onClick={() => handleDelete(doc.id, doc.original_filename)}
                          className="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded"
                        >
                          <Trash2 size={16} />
                        </button>
                      </div>
                    </div>
                    
                    {/* Summary */}
                    {doc.summary && (
                      <p className="text-sm text-gray-600 mt-2 line-clamp-2">
                        {doc.summary}
                      </p>
                    )}
                    
                    {/* Topics */}
                    {doc.key_topics?.topics && doc.key_topics.topics.length > 0 && (
                      <div className="flex gap-1 mt-2 flex-wrap">
                        {doc.key_topics.topics.map((topic, idx) => (
                          <span key={idx} className="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">
                            {topic}
                          </span>
                        ))}
                      </div>
                    )}
                    
                    {/* Status */}
                    {isProcessing && (
                      <p className="text-xs text-[#3DAFA8] mt-2 flex items-center gap-1">
                        <Loader2 className="animate-spin" size={12} />
                        Elaborazione in corso...
                      </p>
                    )}
                    {hasFailed && (
                      <p className="text-xs text-red-500 mt-2 flex items-center gap-1">
                        <AlertCircle size={12} />
                        Elaborazione fallita - clicca per riprovare
                      </p>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}

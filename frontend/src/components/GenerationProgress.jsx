import { useState, useEffect } from 'react';
import { Loader2 } from 'lucide-react';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8001/api';

export default function GenerationProgress({ projectId, isGenerating, onComplete }) {
  const [status, setStatus] = useState({ status: 'idle', percent: 0, current_batch: 0, total_batches: 0 });

  useEffect(() => {
    if (!isGenerating) {
      setStatus({ status: 'idle', percent: 0, current_batch: 0, total_batches: 0 });
      return;
    }

    const interval = setInterval(async () => {
      try {
        const token = localStorage.getItem('token');
        const res = await fetch(`${API_URL}/generate/status/${projectId}`, {
          headers: { 'Authorization': `Bearer ${token}` }
        });
        const data = await res.json();
        setStatus(data);
        
        if (data.status === 'completed' || data.status === 'review') {
          clearInterval(interval);
          if (onComplete) onComplete();
        }
      } catch (err) {
        console.error('Error fetching status:', err);
      }
    }, 2000);

    return () => clearInterval(interval);
  }, [projectId, isGenerating, onComplete]);

  if (!isGenerating && status.status === 'idle') return null;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-xl p-8 max-w-md w-full mx-4 shadow-2xl">
        <div className="flex items-center gap-3 mb-4">
          <Loader2 className="w-6 h-6 animate-spin text-teal-500" />
          <h3 className="text-lg font-semibold">Generazione in corso...</h3>
        </div>
        
        <div className="mb-4">
          <div className="flex justify-between text-sm text-gray-600 mb-2">
            <span>Batch {status.current_batch} di {status.total_batches}</span>
            <span>{status.percent}%</span>
          </div>
          <div className="w-full bg-gray-200 rounded-full h-3">
            <div 
              className="bg-teal-500 h-3 rounded-full transition-all duration-500"
              style={{ width: `${status.percent}%` }}
            />
          </div>
        </div>
        
        <p className="text-sm text-gray-500 text-center">
          Generazione contenuti con AI. Non chiudere questa finestra.
        </p>
      </div>
    </div>
  );
}

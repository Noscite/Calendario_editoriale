import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft, Shield } from 'lucide-react';

export default function Privacy() {
  useEffect(() => {
    // Carica script iubenda
    const script = document.createElement('script');
    script.src = 'https://cdn.iubenda.com/iubenda.js';
    script.async = true;
    document.body.appendChild(script);
    
    return () => {
      document.body.removeChild(script);
    };
  }, []);

  return (
    <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8]">
      <div className="max-w-4xl mx-auto px-4 py-8">
        <Link 
          to="/login" 
          className="inline-flex items-center gap-2 text-white/80 hover:text-white mb-8"
        >
          <ArrowLeft className="w-4 h-4" />
          Torna alla home
        </Link>
        
        <div className="bg-white rounded-2xl shadow-xl p-8">
          <div className="flex items-center gap-3 mb-6">
            <Shield className="w-8 h-8 text-[#3DAFA8]" />
            <h1 className="text-3xl font-bold text-[#2C3E50]">Privacy Policy</h1>
          </div>
          
          <div className="prose max-w-none">
            <p className="text-gray-600 mb-6">
              La presente Privacy Policy descrive le modalità di gestione del sito 
              in riferimento al trattamento dei dati personali degli utenti che lo consultano.
            </p>
            
            <div className="border rounded-lg p-6 bg-gray-50">
              <a 
                href="https://www.iubenda.com/privacy-policy/63014802" 
                className="iubenda-white iubenda-noiframe iubenda-embed iubenda-noiframe text-[#3DAFA8] hover:underline font-medium" 
                title="Privacy Policy"
              >
                Visualizza la Privacy Policy completa
              </a>
            </div>
            
            <div className="mt-8 pt-6 border-t">
              <h2 className="text-xl font-semibold text-[#2C3E50] mb-4">Cookie Policy</h2>
              <a 
                href="https://www.iubenda.com/privacy-policy/63014802/cookie-policy" 
                className="iubenda-white iubenda-noiframe iubenda-embed iubenda-noiframe text-[#3DAFA8] hover:underline font-medium" 
                title="Cookie Policy"
              >
                Visualizza la Cookie Policy completa
              </a>
            </div>
          </div>
          
          <div className="mt-8 pt-6 border-t text-sm text-gray-500">
            <p><strong>Titolare del Trattamento:</strong> Noscite di Stefano Andrello</p>
            <p><strong>Email:</strong> privacy@noscite.it</p>
          </div>
        </div>
      </div>
    </div>
  );
}

import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Mic, MicOff, Send, SkipForward, X, Volume2, Loader2, CheckCircle } from 'lucide-react';
import api from '../services/api';

export default function VoiceProfilingInterview() {
  const { brandId } = useParams();
  const navigate = useNavigate();

  const [step, setStep] = useState('loading'); // loading, ready, interview, completed
  const [brandName, setBrandName] = useState('');
  const [progress, setProgress] = useState(null);
  const [messages, setMessages] = useState([]);
  const [profile, setProfile] = useState({});
  const [isConnected, setIsConnected] = useState(false);
  const [isRecording, setIsRecording] = useState(false);
  const [isSpeaking, setIsSpeaking] = useState(false);
  const [textInput, setTextInput] = useState('');
  const [error, setError] = useState(null);

  const wsRef = useRef(null);
  const audioContextRef = useRef(null);
  const mediaRecorderRef = useRef(null);
  const messagesEndRef = useRef(null);
  const audioQueueRef = useRef([]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const addMessage = useCallback((role, text) => {
    setMessages(prev => [...prev, { role, text, timestamp: new Date() }]);
  }, []);

  // Inizializza sessione
  useEffect(() => {
    const init = async () => {
      try {
        const res = await api.post(`/voice-profiling/brand/${brandId}/init`);
        setBrandName(res.data.brand_name);
        setStep('ready');
      } catch (err) {
        setError(err.response?.data?.detail || 'Errore inizializzazione');
        setStep('error');
      }
    };
    init();
  }, [brandId]);

  // Setup audio
  const initAudio = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: { sampleRate: 24000, channelCount: 1, echoCancellation: true, noiseSuppression: true }
      });

      const audioCtx = new (window.AudioContext || window.webkitAudioContext)({ sampleRate: 24000 });
      audioContextRef.current = audioCtx;

      const source = audioCtx.createMediaStreamSource(stream);
      const processor = audioCtx.createScriptProcessor(4096, 1, 1);

      let sendingAudio = false;

      processor.onaudioprocess = (e) => {
        if (!sendingAudio || !wsRef.current || wsRef.current.readyState !== WebSocket.OPEN) return;

        const inputData = e.inputBuffer.getChannelData(0);
        const pcm16 = new Int16Array(inputData.length);
        for (let i = 0; i < inputData.length; i++) {
          const s = Math.max(-1, Math.min(1, inputData[i]));
          pcm16[i] = s < 0 ? s * 0x8000 : s * 0x7FFF;
        }

        const bytes = new Uint8Array(pcm16.buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
          binary += String.fromCharCode(bytes[i]);
        }
        const base64 = btoa(binary);

        wsRef.current.send(JSON.stringify({ type: 'audio', data: base64 }));
      };

      source.connect(processor);
      processor.connect(audioCtx.destination);

      mediaRecorderRef.current = {
        start: () => { sendingAudio = true; },
        stop: () => { sendingAudio = false; },
        stream,
        processor,
        source
      };

      return true;
    } catch (err) {
      setError('Impossibile accedere al microfono');
      return false;
    }
  };

  // Play audio chunks
  const playAudioChunk = (base64) => {
    try {
      const binary = atob(base64);
      const bytes = new Uint8Array(binary.length);
      for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
      }
      audioQueueRef.current.push(bytes);
    } catch (err) {
      console.error('Errore audio:', err);
    }
  };

  const playAccumulatedAudio = async () => {
    if (audioQueueRef.current.length === 0) return;

    const totalLength = audioQueueRef.current.reduce((sum, chunk) => sum + chunk.length, 0);
    const combined = new Uint8Array(totalLength);
    let offset = 0;
    for (const chunk of audioQueueRef.current) {
      combined.set(chunk, offset);
      offset += chunk.length;
    }
    audioQueueRef.current = [];

    // PCM16 to Float32
    const pcm16 = new Int16Array(combined.buffer);
    const float32 = new Float32Array(pcm16.length);
    for (let i = 0; i < pcm16.length; i++) {
      float32[i] = pcm16[i] / 32768.0;
    }

    if (!audioContextRef.current) return;
    const ctx = audioContextRef.current;
    const audioBuffer = ctx.createBuffer(1, float32.length, 24000);
    audioBuffer.getChannelData(0).set(float32);

    const source = ctx.createBufferSource();
    source.buffer = audioBuffer;
    source.connect(ctx.destination);
    source.start();
  };

  // Connetti WebSocket
  const connect = async () => {
    const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/api/voice-profiling/ws/${brandId}`;
    const ws = new WebSocket(wsUrl);

    ws.onopen = () => {
      setIsConnected(true);
    };

    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);

      switch (data.type) {
        case 'connected':
          setProgress(data.progress);
          break;
        case 'audio':
          setIsSpeaking(true);
          playAudioChunk(data.data);
          break;
        case 'audio_done':
          playAccumulatedAudio();
          setTimeout(() => setIsSpeaking(false), 500);
          break;
        case 'transcript':
          addMessage(data.role, data.text);
          break;
        case 'progress':
          setProgress(data.data);
          break;
        case 'profile_updated':
          setProfile(prev => ({ ...prev, ...data.extracted }));
          break;
        case 'completed':
          setProfile(data.profile);
          setStep('completed');
          break;
        case 'error':
          setError(data.message);
          break;
      }
    };

    ws.onclose = () => {
      setIsConnected(false);
    };

    wsRef.current = ws;
  };

  // Avvia intervista
  const startInterview = async () => {
    const audioOk = await initAudio();
    if (!audioOk) return;
    await connect();
    setStep('interview');
    setTimeout(() => {
      wsRef.current?.send(JSON.stringify({ type: 'start' }));
    }, 1000);
  };

  // Toggle registrazione
  const toggleRecording = () => {
    const recorder = mediaRecorderRef.current;
    if (!recorder) return;
    if (isRecording) {
      recorder.stop();
      wsRef.current?.send(JSON.stringify({ type: 'audio_end' }));
    } else {
      recorder.start();
    }
    setIsRecording(!isRecording);
  };

  // Invia testo
  const sendText = () => {
    if (textInput.trim() && wsRef.current?.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify({ type: 'text', text: textInput }));
      setTextInput('');
    }
  };

  // Salta domanda
  const skipQuestion = () => {
    wsRef.current?.send(JSON.stringify({ type: 'skip' }));
  };

  // Salva profilo
  const saveProfile = async () => {
    try {
      await api.post(`/voice-profiling/brand/${brandId}/save`);
      navigate(`/brand/${brandId}/new-project?from=voice`);
    } catch (err) {
      setError(err.response?.data?.detail || 'Errore salvataggio');
    }
  };

  // Cleanup
  useEffect(() => {
    return () => {
      wsRef.current?.close();
      const recorder = mediaRecorderRef.current;
      if (recorder) {
        recorder.stop?.();
        recorder.processor?.disconnect();
        recorder.source?.disconnect();
        recorder.stream?.getTracks().forEach(t => t.stop());
      }
      audioContextRef.current?.close();
    };
  }, []);

  // RENDER
  if (step === 'loading') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center">
        <div className="bg-white rounded-2xl p-8 text-center">
          <Loader2 className="w-12 h-12 animate-spin text-[#3DAFA8] mx-auto mb-4" />
          <p>Preparazione intervista...</p>
        </div>
      </div>
    );
  }

  if (step === 'error') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl p-8 max-w-md text-center">
          <X className="w-12 h-12 text-red-500 mx-auto mb-4" />
          <h2 className="text-xl font-bold mb-2">Errore</h2>
          <p className="text-gray-600 mb-4">{error}</p>
          <button onClick={() => navigate(-1)} className="px-6 py-2 bg-[#3DAFA8] text-white rounded-lg">
            Torna indietro
          </button>
        </div>
      </div>
    );
  }

  if (step === 'ready') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl p-8 max-w-lg w-full">
          <div className="text-center mb-8">
            <div className="w-20 h-20 bg-[#3DAFA8]/10 rounded-full flex items-center justify-center mx-auto mb-4">
              <Mic className="w-10 h-10 text-[#3DAFA8]" />
            </div>
            <h1 className="text-2xl font-bold text-[#2C3E50]">Intervista AI</h1>
            <p className="text-gray-500 mt-2">Profilazione brand: {brandName}</p>
          </div>

          <div className="bg-gray-50 rounded-xl p-4 mb-6">
            <h3 className="font-semibold mb-2">Come funziona:</h3>
            <ul className="text-sm text-gray-600 space-y-2">
              <li>🎤 L'AI ti farà alcune domande sulla tua azienda</li>
              <li>💬 Puoi rispondere a voce o scrivendo</li>
              <li>⏭️ Puoi saltare domande se preferisci</li>
              <li>📋 Alla fine vedrai un riepilogo modificabile</li>
            </ul>
          </div>

          <div className="text-center text-sm text-gray-500 mb-4">
            Durata stimata: ~5 minuti
          </div>

          <button
            onClick={startInterview}
            className="w-full py-4 bg-gradient-to-r from-[#3DAFA8] to-[#2C3E50] text-white text-lg font-bold rounded-xl hover:opacity-90 transition-opacity"
          >
            🎙️ Inizia Intervista
          </button>

          <button
            onClick={() => navigate(`/brand/${brandId}/new-project`)}
            className="w-full mt-3 py-3 text-gray-500 hover:text-gray-700"
          >
            Preferisco compilare manualmente
          </button>
        </div>
      </div>
    );
  }

  if (step === 'interview') {
    return (
      <div className="min-h-screen bg-[#1a1a2e] text-white flex flex-col">
        {/* Header */}
        <div className="bg-[#16213e] p-4 flex justify-between items-center">
          <div className="flex items-center gap-3">
            <div className={`w-3 h-3 rounded-full ${isConnected ? 'bg-green-500' : 'bg-red-500'}`} />
            <span className="font-semibold">{brandName}</span>
          </div>
          {progress && (
            <div className="flex items-center gap-4">
              <span className="text-sm text-gray-400">
                Domanda {progress.current_question}/{progress.total_questions}
              </span>
              <div className="w-32 h-2 bg-gray-700 rounded-full overflow-hidden">
                <div
                  className="h-full bg-[#3DAFA8] transition-all"
                  style={{ width: `${progress.percent_complete}%` }}
                />
              </div>
            </div>
          )}
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {messages.map((m, i) => (
            <div key={i} className={`flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
              <div
                className={`max-w-[80%] p-4 rounded-2xl ${
                  m.role === 'user'
                    ? 'bg-[#3DAFA8] text-white'
                    : 'bg-[#16213e] text-gray-100'
                }`}
              >
                {m.text}
              </div>
            </div>
          ))}
          {isSpeaking && (
            <div className="flex justify-start">
              <div className="bg-[#16213e] p-4 rounded-2xl flex items-center gap-2">
                <Volume2 className="w-5 h-5 text-[#3DAFA8] animate-pulse" />
                <span className="text-gray-400">Sta parlando...</span>
              </div>
            </div>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Controls */}
        <div className="bg-[#16213e] p-4">
          <div className="flex items-center gap-3 max-w-3xl mx-auto">
            <button
              onClick={skipQuestion}
              className="p-3 bg-gray-700 hover:bg-gray-600 rounded-xl"
              title="Salta domanda"
            >
              <SkipForward className="w-5 h-5" />
            </button>

            <button
              onClick={toggleRecording}
              disabled={isSpeaking}
              className={`w-14 h-14 rounded-full flex items-center justify-center transition-all ${
                isRecording
                  ? 'bg-red-500 animate-pulse'
                  : 'bg-[#3DAFA8] hover:bg-[#2C3E50]'
              } ${isSpeaking ? 'opacity-50 cursor-not-allowed' : ''}`}
            >
              {isRecording ? <MicOff className="w-6 h-6" /> : <Mic className="w-6 h-6" />}
            </button>

            <input
              type="text"
              value={textInput}
              onChange={(e) => setTextInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && sendText()}
              placeholder="Oppure scrivi qui..."
              className="flex-1 px-4 py-3 bg-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#3DAFA8]"
            />

            <button
              onClick={sendText}
              disabled={!textInput.trim()}
              className="p-3 bg-[#3DAFA8] hover:bg-[#2C3E50] rounded-xl disabled:opacity-50"
            >
              <Send className="w-5 h-5" />
            </button>
          </div>

          {isRecording && (
            <div className="text-center mt-3 text-red-400 animate-pulse">
              🔴 Registrazione in corso... Clicca per terminare
            </div>
          )}
        </div>
      </div>
    );
  }

  if (step === 'completed') {
    return (
      <div className="min-h-screen bg-gradient-to-br from-[#2C3E50] to-[#3DAFA8] p-4">
        <div className="max-w-2xl mx-auto">
          <div className="bg-white rounded-2xl shadow-xl p-8">
            <div className="text-center mb-8">
              <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
              <h1 className="text-2xl font-bold text-[#2C3E50]">Intervista Completata!</h1>
              <p className="text-gray-500">Ecco il profilo estratto per {brandName}</p>
            </div>

            <div className="space-y-4 mb-8">
              {profile.company_description && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-1">Descrizione Azienda</h3>
                  <p>{profile.company_description}</p>
                </div>
              )}

              {profile.target_audience && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-1">Target Audience</h3>
                  <p>{profile.target_audience}</p>
                </div>
              )}

              {profile.tone_of_voice && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-1">Tone of Voice</h3>
                  <p>{profile.tone_of_voice}</p>
                </div>
              )}

              {profile.key_topics?.length > 0 && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-2">Argomenti Chiave</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.key_topics.map((topic, i) => (
                      <span key={i} className="px-3 py-1 bg-[#3DAFA8]/10 text-[#3DAFA8] rounded-full text-sm">
                        {topic}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {profile.platforms?.length > 0 && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-2">Piattaforme</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.platforms.map((p, i) => (
                      <span key={i} className="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                        {p}
                      </span>
                    ))}
                  </div>
                </div>
              )}

              {profile.posts_per_week && (
                <div className="bg-gray-50 p-4 rounded-xl">
                  <h3 className="font-semibold text-sm text-gray-500 mb-1">Frequenza</h3>
                  <p>{profile.posts_per_week} post a settimana</p>
                </div>
              )}
            </div>

            <div className="flex gap-4">
              <button
                onClick={() => navigate(`/brand/${brandId}`)}
                className="flex-1 py-3 border border-gray-300 rounded-xl font-semibold hover:bg-gray-50"
              >
                Modifica Manualmente
              </button>
              <button
                onClick={saveProfile}
                className="flex-1 py-3 bg-[#3DAFA8] text-white rounded-xl font-semibold hover:bg-[#2C3E50]"
              >
                Salva e Crea Progetto →
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return null;
}

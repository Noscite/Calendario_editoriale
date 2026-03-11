import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { MessageCircle, X, Send, Bot, FileText, Loader2 } from 'lucide-react';
import { support } from '../services/api';
import { useAuthStore } from '../store/authStore';

const detectSection = (pathname) => {
  if (pathname.startsWith('/brand')) return 'brand';
  if (pathname.startsWith('/project')) return 'calendario';
  if (pathname.startsWith('/settings')) return 'impostazioni';
  if (pathname.startsWith('/help')) return 'documentazione';
  return 'dashboard';
};

const renderMessageContent = (text) => {
  const paragraphs = text.split(/\n\n+/);

  return paragraphs.map((paragraph, pIdx) => {
    const lines = paragraph.split('\n');

    // Check if all lines are bullet items
    const isBulletList = lines.every((l) => l.trimStart().startsWith('- '));
    if (isBulletList && lines.length > 0) {
      return (
        <ul key={pIdx} className="list-disc pl-4 my-1 space-y-0.5">
          {lines.map((line, i) => (
            <li key={i}>{renderInline(line.replace(/^\s*-\s*/, ''))}</li>
          ))}
        </ul>
      );
    }

    // Check if all lines are numbered items
    const isNumberedList = lines.every((l) => /^\s*\d+\.\s/.test(l));
    if (isNumberedList && lines.length > 0) {
      return (
        <ol key={pIdx} className="list-decimal pl-4 my-1 space-y-0.5">
          {lines.map((line, i) => (
            <li key={i}>{renderInline(line.replace(/^\s*\d+\.\s*/, ''))}</li>
          ))}
        </ol>
      );
    }

    // Regular paragraph with <br /> for single newlines
    return (
      <p key={pIdx} className={pIdx > 0 ? 'mt-2' : ''}>
        {lines.map((line, i) => (
          <span key={i}>
            {i > 0 && <br />}
            {renderInline(line)}
          </span>
        ))}
      </p>
    );
  });
};

const renderInline = (text) => {
  // Process bold, italic, code inline markers
  const parts = [];
  let remaining = text;
  let key = 0;

  while (remaining.length > 0) {
    // Code: `text`
    const codeMatch = remaining.match(/^(.*?)`([^`]+)`/);
    // Bold: **text**
    const boldMatch = remaining.match(/^(.*?)\*\*([^*]+)\*\*/);
    // Italic: *text*
    const italicMatch = remaining.match(/^(.*?)\*([^*]+)\*/);

    // Find earliest match
    let earliest = null;
    let earliestIndex = Infinity;

    if (codeMatch && codeMatch.index + codeMatch[1].length < earliestIndex) {
      earliest = 'code';
      earliestIndex = codeMatch.index + codeMatch[1].length;
    }
    if (boldMatch && boldMatch.index + boldMatch[1].length < earliestIndex) {
      earliest = 'bold';
      earliestIndex = boldMatch.index + boldMatch[1].length;
    }
    if (
      italicMatch &&
      italicMatch.index + italicMatch[1].length < earliestIndex &&
      earliest !== 'bold'
    ) {
      earliest = 'italic';
      earliestIndex = italicMatch.index + italicMatch[1].length;
    }

    if (earliest === 'code') {
      if (codeMatch[1]) parts.push(<span key={key++}>{codeMatch[1]}</span>);
      parts.push(
        <code key={key++} className="bg-gray-100 px-1 rounded text-xs">
          {codeMatch[2]}
        </code>
      );
      remaining = remaining.slice(codeMatch[0].length);
    } else if (earliest === 'bold') {
      if (boldMatch[1]) parts.push(<span key={key++}>{boldMatch[1]}</span>);
      parts.push(<strong key={key++}>{boldMatch[2]}</strong>);
      remaining = remaining.slice(boldMatch[0].length);
    } else if (earliest === 'italic') {
      if (italicMatch[1]) parts.push(<span key={key++}>{italicMatch[1]}</span>);
      parts.push(<em key={key++}>{italicMatch[2]}</em>);
      remaining = remaining.slice(italicMatch[0].length);
    } else {
      parts.push(<span key={key++}>{remaining}</span>);
      remaining = '';
    }
  }

  return parts;
};

const TypingIndicator = () => (
  <div className="flex items-center gap-1 max-w-[80%] bg-white border border-gray-200 rounded-2xl rounded-tl-sm p-3">
    <style>{`
      @keyframes typingBounce {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-4px); }
      }
      .typing-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #9ca3af;
        animation: typingBounce 1.2s infinite;
      }
      .typing-dot:nth-child(2) { animation-delay: 0.15s; }
      .typing-dot:nth-child(3) { animation-delay: 0.3s; }
    `}</style>
    <div className="typing-dot" />
    <div className="typing-dot" />
    <div className="typing-dot" />
  </div>
);

const quickChips = [
  'Cosa puoi fare?',
  'Come genero il calendario?',
  'Come collego Instagram?',
  'Come funzionano le Edizioni?',
];

export default function SupportChat() {
  const [isOpen, setIsOpen] = useState(false);
  const [messages, setMessages] = useState([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [history, setHistory] = useState([]);

  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);
  const navigate = useNavigate();
  const user = useAuthStore((s) => s.user);

  const hasBeenOpened = useRef(!!localStorage.getItem('noscite_support_opened'));
  const [showPulse, setShowPulse] = useState(!hasBeenOpened.current);

  // Welcome message on mount
  useEffect(() => {
    const name = user?.full_name || '';
    setMessages([
      {
        role: 'assistant',
        content: `Ciao ${name}! 👋 Sono Kalen, l'assistente di Noscite Calendar. Come posso aiutarti oggi?`,
      },
    ]);
  }, [user]);

  // Listen for programmatic open event
  useEffect(() => {
    const handler = () => setIsOpen(true);
    window.addEventListener('open-support', handler);
    return () => window.removeEventListener('open-support', handler);
  }, []);

  // Auto-scroll on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, loading]);

  // Focus input when opened
  useEffect(() => {
    if (isOpen) {
      localStorage.setItem('noscite_support_opened', 'true');
      setShowPulse(false);
      setTimeout(() => inputRef.current?.focus(), 100);
    }
  }, [isOpen]);

  const context = {
    current_page: window.location.pathname,
    current_section: detectSection(window.location.pathname),
  };

  const sendMessage = async (text) => {
    const trimmed = (text || input).trim();
    if (!trimmed || loading) return;

    const userMsg = { role: 'user', content: trimmed };
    setMessages((prev) => [...prev, userMsg]);
    setInput('');
    setLoading(true);

    try {
      const response = await support.chat(trimmed, history, context);
      const data = response.data;
      const assistantMsg = {
        role: 'assistant',
        content: data.message || data.response || '',
        articles: data.suggested_articles || null,
      };
      setMessages((prev) => [...prev, assistantMsg]);
      setHistory((prev) => [
        ...prev,
        { role: 'user', content: trimmed },
        { role: 'assistant', content: assistantMsg.content },
      ]);
    } catch {
      setMessages((prev) => [
        ...prev,
        {
          role: 'assistant',
          content: 'Mi dispiace, si è verificato un errore. Riprova tra poco.',
        },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  };

  const isWelcomeOnly = messages.length === 1 && messages[0].role === 'assistant';

  // Closed state: floating button
  if (!isOpen) {
    return (
      <button
        onClick={() => setIsOpen(true)}
        className={`fixed bottom-6 right-6 z-50 bg-[#3DAFA8] text-white rounded-full shadow-lg px-4 py-3 flex items-center gap-2 hover:bg-[#35a099] transition-all duration-300 ${
          showPulse ? 'animate-pulse' : ''
        }`}
      >
        <MessageCircle size={20} />
        <span className="text-sm font-medium">Aiuto</span>
      </button>
    );
  }

  // Open state: chat window
  return (
    <div
      className={`fixed z-50 flex flex-col bg-white overflow-hidden transition-all duration-300 ease-out
        md:bottom-6 md:right-6 md:w-96 md:h-[520px] md:rounded-2xl md:shadow-2xl
        max-md:inset-0`}
      style={{ animation: 'supportSlideUp 0.3s ease-out' }}
    >
      <style>{`
        @keyframes supportSlideUp {
          from {
            opacity: 0;
            transform: translateY(16px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>

      {/* Header */}
      <div className="bg-[#1a1f2e] text-white p-4 flex items-center gap-3 shrink-0">
        <div className="w-10 h-10 rounded-full bg-[#3DAFA8]/20 flex items-center justify-center">
          <Bot size={20} className="text-[#3DAFA8]" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="font-semibold text-sm">Kalen &middot; Assistente Noscite</div>
          <div className="text-xs text-gray-400">Rispondo in pochi secondi</div>
        </div>
        <button
          onClick={() => setIsOpen(false)}
          className="text-gray-400 hover:text-white transition-colors"
        >
          <X size={20} />
        </button>
      </div>

      {/* Messages area */}
      <div className="flex-1 overflow-y-auto p-4 space-y-3">
        {messages.map((msg, idx) => (
          <div key={idx}>
            {msg.role === 'assistant' ? (
              <div className="max-w-[80%] bg-white border border-gray-200 rounded-2xl rounded-tl-sm p-3 text-sm text-gray-800">
                {renderMessageContent(msg.content)}
                {/* Suggested articles */}
                {msg.articles && msg.articles.length > 0 && (
                  <div className="mt-2 space-y-1.5">
                    {msg.articles.map((article, aIdx) => (
                      <div
                        key={aIdx}
                        className="border-l-2 border-[#3DAFA8] pl-3 py-1 bg-gray-50 rounded-r-lg cursor-pointer text-xs flex items-center gap-2 hover:bg-gray-100 transition-colors"
                        onClick={() =>
                          window.open(`/help/article/${article.slug}`, '_blank')
                        }
                      >
                        <FileText size={12} className="text-[#3DAFA8] shrink-0" />
                        <span className="text-gray-700">{article.title}</span>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            ) : (
              <div className="max-w-[80%] bg-[#3DAFA8] text-white rounded-2xl rounded-tr-sm p-3 text-sm ml-auto">
                {msg.content}
              </div>
            )}
          </div>
        ))}

        {loading && <TypingIndicator />}

        {/* Quick chips */}
        {isWelcomeOnly && !loading && (
          <div className="flex flex-wrap gap-2 pt-1">
            {quickChips.map((chip) => (
              <button
                key={chip}
                onClick={() => sendMessage(chip)}
                className="border border-[#3DAFA8] text-[#3DAFA8] text-xs px-3 py-1.5 rounded-full cursor-pointer hover:bg-[#3DAFA8]/10 transition-colors"
              >
                {chip}
              </button>
            ))}
          </div>
        )}

        <div ref={messagesEndRef} />
      </div>

      {/* Input footer */}
      <div className="bg-gray-50 border-t p-3 flex gap-2 shrink-0">
        <input
          ref={inputRef}
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Scrivi un messaggio..."
          disabled={loading}
          className="flex-1 text-sm border rounded-full px-4 py-2 outline-none focus:border-[#3DAFA8] focus:ring-1 focus:ring-[#3DAFA8]/30 disabled:opacity-50 transition-colors"
        />
        <button
          onClick={() => sendMessage()}
          disabled={!input.trim() || loading}
          className="w-9 h-9 rounded-full bg-[#3DAFA8] text-white flex items-center justify-center hover:bg-[#35a099] disabled:opacity-40 disabled:cursor-not-allowed transition-colors shrink-0"
        >
          {loading ? <Loader2 size={16} className="animate-spin" /> : <Send size={16} />}
        </button>
      </div>

      {/* Bottom link */}
      <div className="text-center py-2 text-xs border-t bg-white shrink-0">
        <button
          onClick={() => {
            setIsOpen(false);
            navigate('/help');
          }}
          className="text-gray-500 hover:text-[#3DAFA8] transition-colors"
        >
          Sfoglia il Centro Assistenza &rarr;
        </button>
      </div>
    </div>
  );
}

import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import {
  ArrowLeft,
  FileText,
  ThumbsUp,
  ThumbsDown,
  MessageCircle,
  Loader2,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';
import { help } from '../services/api';

function slugify(text) {
  return text
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim();
}

function applyInlineFormatting(text) {
  const parts = [];
  let remaining = text;
  let keyIdx = 0;

  const inlineRegex = /(`[^`]+`)|(\*\*[^*]+\*\*)|(\*[^*]+\*)/g;
  let lastIndex = 0;
  let match;

  while ((match = inlineRegex.exec(remaining)) !== null) {
    if (match.index > lastIndex) {
      parts.push(remaining.slice(lastIndex, match.index));
    }

    if (match[1]) {
      const code = match[1].slice(1, -1);
      parts.push(
        <code key={keyIdx++} className="bg-gray-100 px-1.5 py-0.5 rounded text-sm">
          {code}
        </code>
      );
    } else if (match[2]) {
      const bold = match[2].slice(2, -2);
      parts.push(<strong key={keyIdx++}>{bold}</strong>);
    } else if (match[3]) {
      const italic = match[3].slice(1, -1);
      parts.push(<em key={keyIdx++}>{italic}</em>);
    }

    lastIndex = match.index + match[0].length;
  }

  if (lastIndex < remaining.length) {
    parts.push(remaining.slice(lastIndex));
  }

  return parts.length === 0 ? [text] : parts;
}

function renderMarkdown(content) {
  if (!content) return null;

  const lines = content.split('\n');
  const elements = [];
  let i = 0;
  let key = 0;

  while (i < lines.length) {
    const line = lines[i];

    // Empty line
    if (line.trim() === '') {
      i++;
      continue;
    }

    // H1
    if (/^# /.test(line)) {
      const text = line.replace(/^# /, '');
      elements.push(
        <h1 key={key++} className="text-3xl font-bold text-[#2C3E50] mb-6">
          {applyInlineFormatting(text)}
        </h1>
      );
      i++;
      continue;
    }

    // H2
    if (/^## /.test(line)) {
      const text = line.replace(/^## /, '');
      const id = slugify(text);
      elements.push(
        <h2
          key={key++}
          id={id}
          className="text-2xl font-semibold text-[#2C3E50] mt-8 mb-4"
        >
          {applyInlineFormatting(text)}
        </h2>
      );
      i++;
      continue;
    }

    // H3
    if (/^### /.test(line)) {
      const text = line.replace(/^### /, '');
      const id = slugify(text);
      elements.push(
        <h3
          key={key++}
          id={id}
          className="text-xl font-medium text-[#2C3E50] mt-6 mb-3"
        >
          {applyInlineFormatting(text)}
        </h3>
      );
      i++;
      continue;
    }

    // Table
    if (line.includes('|') && line.trim().startsWith('|')) {
      const tableRows = [];
      while (i < lines.length && lines[i].trim().startsWith('|')) {
        tableRows.push(lines[i]);
        i++;
      }

      if (tableRows.length >= 2) {
        const parseRow = (row) =>
          row
            .split('|')
            .filter((_, idx, arr) => idx > 0 && idx < arr.length - 1)
            .map((cell) => cell.trim());

        const headerCells = parseRow(tableRows[0]);
        const isSeparator = (row) => /^[\s|:-]+$/.test(row);
        const bodyStartIdx = isSeparator(tableRows[1]) ? 2 : 1;

        elements.push(
          <table key={key++} className="w-full border-collapse my-4">
            <thead>
              <tr>
                {headerCells.map((cell, ci) => (
                  <th
                    key={ci}
                    className="border border-gray-200 bg-gray-50 px-4 py-2 text-left font-semibold"
                  >
                    {applyInlineFormatting(cell)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {tableRows.slice(bodyStartIdx).map((row, ri) => (
                <tr key={ri}>
                  {parseRow(row).map((cell, ci) => (
                    <td key={ci} className="border border-gray-200 px-4 py-2">
                      {applyInlineFormatting(cell)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        );
      }
      continue;
    }

    // Blockquote
    if (/^> /.test(line)) {
      const quoteLines = [];
      while (i < lines.length && /^> /.test(lines[i])) {
        quoteLines.push(lines[i].replace(/^> /, ''));
        i++;
      }
      elements.push(
        <blockquote
          key={key++}
          className="border-l-4 border-[#3DAFA8] pl-4 my-4 text-gray-600 italic"
        >
          {quoteLines.map((ql, qi) => (
            <p key={qi}>{applyInlineFormatting(ql)}</p>
          ))}
        </blockquote>
      );
      continue;
    }

    // Unordered list
    if (/^- /.test(line)) {
      const items = [];
      while (i < lines.length && /^- /.test(lines[i])) {
        items.push(lines[i].replace(/^- /, ''));
        i++;
      }
      elements.push(
        <ul key={key++} className="list-disc pl-6 my-4 space-y-1">
          {items.map((item, ii) => (
            <li key={ii} className="text-gray-700">
              {applyInlineFormatting(item)}
            </li>
          ))}
        </ul>
      );
      continue;
    }

    // Ordered list
    if (/^\d+\. /.test(line)) {
      const items = [];
      while (i < lines.length && /^\d+\. /.test(lines[i])) {
        items.push(lines[i].replace(/^\d+\. /, ''));
        i++;
      }
      elements.push(
        <ol key={key++} className="list-decimal pl-6 my-4 space-y-1">
          {items.map((item, ii) => (
            <li key={ii} className="text-gray-700">
              {applyInlineFormatting(item)}
            </li>
          ))}
        </ol>
      );
      continue;
    }

    // Paragraph
    elements.push(
      <p key={key++} className="text-gray-700 leading-relaxed mb-4">
        {applyInlineFormatting(line)}
      </p>
    );
    i++;
  }

  return elements;
}

function extractHeadings(content) {
  if (!content) return [];
  const headings = [];
  const lines = content.split('\n');
  for (const line of lines) {
    const h2Match = line.match(/^## (.+)/);
    const h3Match = line.match(/^### (.+)/);
    if (h3Match) {
      headings.push({ level: 3, text: h3Match[1], id: slugify(h3Match[1]) });
    } else if (h2Match) {
      headings.push({ level: 2, text: h2Match[1], id: slugify(h2Match[1]) });
    }
  }
  return headings;
}

export default function HelpArticlePage() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const [article, setArticle] = useState(null);
  const [loading, setLoading] = useState(true);
  const [activeHeading, setActiveHeading] = useState('');
  const [feedback, setFeedback] = useState(null);
  const [tocOpen, setTocOpen] = useState(false);
  const contentRef = useRef(null);

  useEffect(() => {
    setLoading(true);
    setFeedback(null);
    help
      .article(slug)
      .then((res) => {
        setArticle(res.data);
      })
      .catch(() => {
        navigate('/help');
      })
      .finally(() => {
        setLoading(false);
      });
  }, [slug, navigate]);

  const headings = extractHeadings(article?.content);

  // Scroll-spy with IntersectionObserver
  useEffect(() => {
    if (!headings.length || !contentRef.current) return;

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setActiveHeading(entry.target.id);
          }
        }
      },
      { rootMargin: '-80px 0px -60% 0px', threshold: 0 }
    );

    const elements = headings
      .map((h) => document.getElementById(h.id))
      .filter(Boolean);

    elements.forEach((el) => observer.observe(el));

    return () => {
      elements.forEach((el) => observer.unobserve(el));
    };
  }, [headings]);

  const scrollToHeading = (id) => {
    const el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setTocOpen(false);
    }
  };

  const handleOpenSupport = () => {
    window.dispatchEvent(new Event('open-support'));
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <Loader2 className="w-8 h-8 animate-spin text-[#3DAFA8]" />
      </div>
    );
  }

  if (!article) return null;

  const TOCContent = ({ mobile = false }) => (
    <nav className={mobile ? '' : ''}>
      <h4 className="text-sm font-semibold text-[#2C3E50] uppercase tracking-wide mb-3">
        Indice
      </h4>
      <ul className="space-y-1">
        {headings.map((heading) => (
          <li
            key={heading.id}
            className={heading.level === 3 ? 'pl-4' : ''}
          >
            <button
              onClick={() => scrollToHeading(heading.id)}
              className={`text-sm text-left w-full py-1 px-2 rounded transition-colors ${
                activeHeading === heading.id
                  ? 'text-[#3DAFA8] font-medium bg-[#3DAFA8]/10'
                  : 'text-gray-600 hover:text-[#2C3E50] hover:bg-gray-50'
              }`}
            >
              {heading.text}
            </button>
          </li>
        ))}
      </ul>
    </nav>
  );

  return (
    <div className="max-w-7xl mx-auto px-4 py-8">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <Link to="/help" className="hover:text-[#3DAFA8] transition-colors">
          Help Center
        </Link>
        <span>/</span>
        {article.category && (
          <>
            <Link
              to={`/help?category=${article.category.slug || article.category.id}`}
              className="hover:text-[#3DAFA8] transition-colors"
            >
              {article.category.name || article.category}
            </Link>
            <span>/</span>
          </>
        )}
        <span className="text-[#2C3E50] font-medium">{article.title}</span>
      </nav>

      {/* Back button */}
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-2 text-gray-600 hover:text-[#3DAFA8] transition-colors mb-6"
      >
        <ArrowLeft className="w-4 h-4" />
        <span className="text-sm">Torna indietro</span>
      </button>

      {/* Mobile TOC */}
      {headings.length > 0 && (
        <div className="lg:hidden mb-6">
          <button
            onClick={() => setTocOpen(!tocOpen)}
            className="flex items-center justify-between w-full bg-gray-50 border border-gray-200 rounded-lg px-4 py-3 text-sm font-medium text-[#2C3E50]"
          >
            <span>Indice dei contenuti</span>
            {tocOpen ? (
              <ChevronUp className="w-4 h-4" />
            ) : (
              <ChevronDown className="w-4 h-4" />
            )}
          </button>
          {tocOpen && (
            <div className="bg-white border border-gray-200 border-t-0 rounded-b-lg px-4 py-3">
              <TOCContent mobile />
            </div>
          )}
        </div>
      )}

      {/* 3-column layout */}
      <div className="flex gap-8">
        {/* Left column - TOC (desktop) */}
        {headings.length > 0 && (
          <aside className="hidden lg:block w-64 flex-shrink-0">
            <div className="sticky top-24">
              <TOCContent />
            </div>
          </aside>
        )}

        {/* Center column - Article content */}
        <main className="flex-1 min-w-0" ref={contentRef}>
          <article className="bg-white rounded-xl border border-gray-200 p-8">
            {renderMarkdown(article.content)}

            {/* Feedback section */}
            <div className="mt-12 pt-8 border-t border-gray-200">
              <p className="text-[#2C3E50] font-medium mb-4">
                Questo articolo ti è stato utile?
              </p>
              <div className="flex gap-3">
                <button
                  onClick={() => setFeedback(feedback === 'up' ? null : 'up')}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors ${
                    feedback === 'up'
                      ? 'bg-[#3DAFA8] text-white border-[#3DAFA8]'
                      : 'border-gray-200 text-gray-600 hover:border-[#3DAFA8] hover:text-[#3DAFA8]'
                  }`}
                >
                  <ThumbsUp className="w-4 h-4" />
                  <span className="text-sm">Si</span>
                </button>
                <button
                  onClick={() => setFeedback(feedback === 'down' ? null : 'down')}
                  className={`flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors ${
                    feedback === 'down'
                      ? 'bg-red-500 text-white border-red-500'
                      : 'border-gray-200 text-gray-600 hover:border-red-400 hover:text-red-400'
                  }`}
                >
                  <ThumbsDown className="w-4 h-4" />
                  <span className="text-sm">No</span>
                </button>
              </div>
            </div>

            {/* Support section */}
            <div className="mt-8 p-6 bg-gray-50 rounded-lg">
              <p className="text-[#2C3E50] font-medium mb-3">
                Hai ancora bisogno di aiuto?
              </p>
              <button
                onClick={handleOpenSupport}
                className="flex items-center gap-2 bg-[#3DAFA8] text-white px-5 py-2.5 rounded-lg hover:bg-[#369d97] transition-colors"
              >
                <MessageCircle className="w-4 h-4" />
                Chatta con il supporto
              </button>
            </div>
          </article>
        </main>

        {/* Right column - Related articles */}
        <aside className="hidden lg:block w-64 flex-shrink-0">
          <div className="sticky top-24">
            {article.related_articles && article.related_articles.length > 0 && (
              <div className="bg-white rounded-xl border border-gray-200 p-5">
                <h4 className="text-sm font-semibold text-[#2C3E50] uppercase tracking-wide mb-4">
                  Articoli correlati
                </h4>
                <ul className="space-y-3">
                  {article.related_articles.map((related) => (
                    <li key={related.slug || related.id}>
                      <Link
                        to={`/help/article/${related.slug}`}
                        className="flex items-start gap-2 text-sm text-gray-600 hover:text-[#3DAFA8] transition-colors group"
                      >
                        <FileText className="w-4 h-4 mt-0.5 flex-shrink-0 text-gray-400 group-hover:text-[#3DAFA8]" />
                        <span>{related.title}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        </aside>
      </div>
    </div>
  );
}

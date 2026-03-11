import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Search,
  Rocket,
  Calendar,
  Send,
  Image as ImageIcon,
  CreditCard,
  FileText,
  ArrowRight,
  Loader2,
  X,
} from 'lucide-react';
import { help } from '../services/api';

const iconMap = { Rocket, Calendar, Send, Image: ImageIcon, CreditCard };
const getIcon = (name) => iconMap[name] || FileText;

export default function HelpCenterPage() {
  const navigate = useNavigate();

  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);

  const [featuredArticles, setFeaturedArticles] = useState([]);
  const [featuredLoading, setFeaturedLoading] = useState(true);

  const [categories, setCategories] = useState([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);

  const [categoryArticles, setCategoryArticles] = useState({});
  const [categoryArticlesLoading, setCategoryArticlesLoading] = useState({});

  // Load featured articles and categories on mount
  useEffect(() => {
    help
      .articles({ featured: true })
      .then((res) => setFeaturedArticles(res.data))
      .catch(() => setFeaturedArticles([]))
      .finally(() => setFeaturedLoading(false));

    help
      .categories()
      .then((res) => setCategories(res.data))
      .catch(() => setCategories([]))
      .finally(() => setCategoriesLoading(false));
  }, []);

  // Debounced search
  useEffect(() => {
    if (searchQuery.trim().length < 2) {
      setSearchResults([]);
      setShowDropdown(false);
      return;
    }

    setSearchLoading(true);
    setShowDropdown(true);

    const timer = setTimeout(() => {
      help
        .search(searchQuery.trim())
        .then((res) => setSearchResults(res.data))
        .catch(() => setSearchResults([]))
        .finally(() => setSearchLoading(false));
    }, 400);

    return () => clearTimeout(timer);
  }, [searchQuery]);

  // Load articles for a category when categories are available
  useEffect(() => {
    if (categories.length === 0) return;

    categories.forEach((cat) => {
      setCategoryArticlesLoading((prev) => ({ ...prev, [cat.slug]: true }));
      help
        .articles({ category: cat.slug })
        .then((res) =>
          setCategoryArticles((prev) => ({ ...prev, [cat.slug]: res.data }))
        )
        .catch(() =>
          setCategoryArticles((prev) => ({ ...prev, [cat.slug]: [] }))
        )
        .finally(() =>
          setCategoryArticlesLoading((prev) => ({
            ...prev,
            [cat.slug]: false,
          }))
        );
    });
  }, [categories]);

  const scrollToCategory = (slug) => {
    const el = document.getElementById(slug);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Header */}
      <div
        className="py-16 px-4 text-center"
        style={{
          background: 'linear-gradient(135deg, #2C3E50 0%, #3DAFA8 100%)',
        }}
      >
        <h1 className="text-4xl font-bold text-white mb-3">
          Centro Assistenza
        </h1>
        <p className="text-lg text-white/80 mb-8">
          Come possiamo aiutarti?
        </p>

        {/* Search bar */}
        <div className="relative max-w-2xl mx-auto">
          <div className="relative">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" />
            <input
              type="text"
              placeholder="Cerca nella documentazione..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-12 pr-10 py-3 rounded-xl shadow-lg text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#3DAFA8]"
            />
            {searchQuery && (
              <button
                onClick={() => {
                  setSearchQuery('');
                  setShowDropdown(false);
                }}
                className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
              >
                <X className="w-4 h-4" />
              </button>
            )}
          </div>

          {/* Search dropdown */}
          {showDropdown && (
            <div className="absolute z-50 mt-2 w-full bg-white rounded-xl shadow-xl border border-gray-100 max-h-96 overflow-y-auto text-left">
              {searchLoading ? (
                <div className="flex items-center justify-center py-8">
                  <Loader2 className="w-5 h-5 animate-spin text-[#3DAFA8]" />
                </div>
              ) : searchResults.length > 0 ? (
                searchResults.map((result) => (
                  <button
                    key={result.slug}
                    onClick={() => {
                      setShowDropdown(false);
                      navigate(`/help/article/${result.slug}`);
                    }}
                    className="w-full px-4 py-3 hover:bg-gray-50 border-b border-gray-50 last:border-0 transition-colors"
                  >
                    <div className="flex items-center gap-2 mb-1">
                      <span className="font-medium text-gray-800">
                        {result.title}
                      </span>
                      {result.category && (
                        <span className="text-xs px-2 py-0.5 rounded-full bg-[#3DAFA8]/10 text-[#3DAFA8] font-medium">
                          {result.category}
                        </span>
                      )}
                    </div>
                    {result.snippet && (
                      <p className="text-sm text-gray-500 line-clamp-1">
                        {result.snippet}
                      </p>
                    )}
                  </button>
                ))
              ) : (
                <div className="px-4 py-6 text-center text-gray-500">
                  <p className="mb-2">
                    Nessun risultato per &ldquo;{searchQuery}&rdquo;
                  </p>
                  <button
                    onClick={() =>
                      window.dispatchEvent(new Event('open-support'))
                    }
                    className="text-[#3DAFA8] hover:underline font-medium"
                  >
                    Apri la chat di supporto
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-4 py-12 space-y-16">
        {/* Featured articles */}
        <section>
          <h2 className="text-2xl font-bold text-[#2C3E50] mb-6">
            Guide consigliate
          </h2>

          {featuredLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="w-6 h-6 animate-spin text-[#3DAFA8]" />
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {featuredArticles.map((article) => (
                <button
                  key={article.slug}
                  onClick={() => navigate(`/help/article/${article.slug}`)}
                  className="text-left p-5 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-[#3DAFA8]/30 transition-all group"
                >
                  <div className="flex items-center gap-2 mb-2">
                    <h3 className="font-semibold text-[#2C3E50] group-hover:text-[#3DAFA8] transition-colors">
                      {article.title}
                    </h3>
                    {article.category && (
                      <span className="text-xs px-2 py-0.5 rounded-full bg-[#E89548]/10 text-[#E89548] font-medium whitespace-nowrap">
                        {article.category}
                      </span>
                    )}
                  </div>
                  {article.excerpt && (
                    <p className="text-sm text-gray-500 line-clamp-2">
                      {article.excerpt}
                    </p>
                  )}
                </button>
              ))}
            </div>
          )}
        </section>

        {/* Categories grid */}
        <section>
          <h2 className="text-2xl font-bold text-[#2C3E50] mb-6">
            Sfoglia per argomento
          </h2>

          {categoriesLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="w-6 h-6 animate-spin text-[#3DAFA8]" />
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {categories.map((cat) => {
                const Icon = getIcon(cat.icon);
                return (
                  <button
                    key={cat.slug}
                    onClick={() => scrollToCategory(cat.slug)}
                    className="text-left p-6 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-[#3DAFA8]/30 transition-all group"
                  >
                    <div className="w-10 h-10 rounded-lg bg-[#3DAFA8]/10 flex items-center justify-center mb-3">
                      <Icon className="w-5 h-5 text-[#3DAFA8]" />
                    </div>
                    <h3 className="font-semibold text-[#2C3E50] group-hover:text-[#3DAFA8] transition-colors mb-1">
                      {cat.title}
                    </h3>
                    {cat.description && (
                      <p className="text-sm text-gray-500 mb-2">
                        {cat.description}
                      </p>
                    )}
                    <span className="text-xs text-[#E89548] font-medium">
                      {cat.articles_count ?? 0} articoli
                    </span>
                  </button>
                );
              })}
            </div>
          )}
        </section>

        {/* Category sections */}
        {categories.map((cat) => {
          const articles = categoryArticles[cat.slug] || [];
          const loading = categoryArticlesLoading[cat.slug];

          return (
            <section key={cat.slug} id={cat.slug}>
              <h2 className="text-2xl font-bold text-[#2C3E50] mb-6">
                {cat.title}
              </h2>

              {loading ? (
                <div className="flex justify-center py-8">
                  <Loader2 className="w-5 h-5 animate-spin text-[#3DAFA8]" />
                </div>
              ) : articles.length > 0 ? (
                <div className="space-y-3">
                  {articles.map((article) => (
                    <button
                      key={article.slug}
                      onClick={() =>
                        navigate(`/help/article/${article.slug}`)
                      }
                      className="w-full text-left p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-[#3DAFA8]/30 transition-all group flex items-center justify-between gap-4"
                    >
                      <div className="min-w-0">
                        <h3 className="font-medium text-[#2C3E50] group-hover:text-[#3DAFA8] transition-colors">
                          {article.title}
                        </h3>
                        {article.excerpt && (
                          <p className="text-sm text-gray-500 line-clamp-1 mt-1">
                            {article.excerpt}
                          </p>
                        )}
                      </div>
                      <ArrowRight className="w-4 h-4 text-gray-300 group-hover:text-[#3DAFA8] transition-colors flex-shrink-0" />
                    </button>
                  ))}
                </div>
              ) : (
                <p className="text-gray-400 text-sm">
                  Nessun articolo in questa categoria.
                </p>
              )}
            </section>
          );
        })}
      </div>
    </div>
  );
}

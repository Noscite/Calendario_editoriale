import { useState, useMemo } from 'react';
import { format, startOfMonth, endOfMonth, eachDayOfInterval, isSameMonth, isToday, getDay, addMonths, subMonths, parseISO } from 'date-fns';
import { it } from 'date-fns/locale';
import PostEditModal from './PostEditModal';

const platformColors = {
  linkedin: 'bg-[#0077b5]',
  instagram: 'bg-gradient-to-r from-[#f09433] via-[#dc2743] to-[#bc1888]',
  facebook: 'bg-[#1877f2]',
  google: 'bg-[#34a853]',
  blog: 'bg-[#9b59b6]',
};

const platformIcons = {
  linkedin: '💼',
  instagram: '📸',
  facebook: '👥',
  google: '📍',
  blog: '📝',
};

export default function CalendarGridView({ posts, onPostsUpdate, apiUrl }) {
  const [currentDate, setCurrentDate] = useState(new Date());
  const [selectedPost, setSelectedPost] = useState(null);
  const [isEditModalOpen, setIsEditModalOpen] = useState(false);

  const postsByDate = useMemo(() => {
    const grouped = {};
    posts.forEach(post => {
      const dateKey = post.scheduled_date;
      if (!grouped[dateKey]) grouped[dateKey] = [];
      grouped[dateKey].push(post);
    });
    Object.keys(grouped).forEach(key => {
      grouped[key].sort((a, b) => (a.scheduled_time || '09:00').localeCompare(b.scheduled_time || '09:00'));
    });
    return grouped;
  }, [posts]);

  const calendarDays = useMemo(() => {
    const start = startOfMonth(currentDate);
    const end = endOfMonth(currentDate);
    const days = eachDayOfInterval({ start, end });
    
    const startDay = getDay(start);
    const paddingStart = startDay === 0 ? 6 : startDay - 1;
    const paddedStart = Array(paddingStart).fill(null).map((_, i) => {
      const date = new Date(start);
      date.setDate(date.getDate() - paddingStart + i);
      return date;
    });
    
    const totalDays = paddedStart.length + days.length;
    const paddingEnd = totalDays % 7 === 0 ? 0 : 7 - (totalDays % 7);
    const paddedEnd = Array(paddingEnd).fill(null).map((_, i) => {
      const date = new Date(end);
      date.setDate(date.getDate() + i + 1);
      return date;
    });
    
    return [...paddedStart, ...days, ...paddedEnd];
  }, [currentDate]);

  const stats = useMemo(() => {
    const platformCounts = {};
    posts.forEach(post => {
      platformCounts[post.platform] = (platformCounts[post.platform] || 0) + 1;
    });
    return platformCounts;
  }, [posts]);

  const openEditModal = (post) => {
    setSelectedPost(post);
    setIsEditModalOpen(true);
  };

  const closeEditModal = () => {
    setIsEditModalOpen(false);
    setSelectedPost(null);
  };

  const handlePostSave = (updatedPost) => {
    if (onPostsUpdate) {
      const newPosts = posts.map(p => p.id === updatedPost.id ? updatedPost : p);
      onPostsUpdate(newPosts);
    }
  };

  const handlePostRegenerate = (regeneratedPost) => {
    if (onPostsUpdate) {
      const newPosts = posts.map(p => p.id === regeneratedPost.id ? regeneratedPost : p);
      onPostsUpdate(newPosts);
    }
  };

  return (
    <div className="max-w-7xl mx-auto">
      {/* Stats Bar */}
      <div className="flex justify-center gap-6 mb-6 flex-wrap">
        {Object.entries(stats).map(([platform, count]) => (
          <div key={platform} className="text-center">
            <div className="text-3xl font-bold text-teal-600">{count}</div>
            <div className="text-sm text-gray-500 capitalize">Post {platform}</div>
          </div>
        ))}
        <div className="text-center">
          <div className="text-3xl font-bold text-orange-500">{posts.length}</div>
          <div className="text-sm text-gray-500">Totale</div>
        </div>
      </div>

      {/* Legend */}
      <div className="flex justify-center gap-4 mb-6 flex-wrap">
        {Object.entries(platformColors).map(([platform, color]) => (
          <div key={platform} className="flex items-center gap-2">
            <div className={`w-5 h-5 rounded ${color}`}></div>
            <span className="text-sm capitalize">{platform}</span>
          </div>
        ))}
      </div>

      {/* Month Navigation */}
      <div className="flex justify-center gap-3 mb-6">
        <button
          onClick={() => setCurrentDate(subMonths(currentDate, 1))}
          className="px-6 py-2 border-2 border-teal-500 text-teal-500 rounded-full font-semibold hover:bg-teal-500 hover:text-white transition-all"
        >
          ← Mese prec.
        </button>
        <div className="px-6 py-2 bg-teal-500 text-white rounded-full font-semibold">
          {format(currentDate, 'MMMM yyyy', { locale: it })}
        </div>
        <button
          onClick={() => setCurrentDate(addMonths(currentDate, 1))}
          className="px-6 py-2 border-2 border-teal-500 text-teal-500 rounded-full font-semibold hover:bg-teal-500 hover:text-white transition-all"
        >
          Mese succ. →
        </button>
      </div>

      {/* Calendar Grid */}
      <div className="bg-white rounded-2xl shadow-lg overflow-hidden">
        {/* Header */}
        <div className="grid grid-cols-7 bg-slate-700 text-white">
          {['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'].map(day => (
            <div key={day} className="py-3 text-center font-semibold text-sm">
              {day}
            </div>
          ))}
        </div>

        {/* Days Grid */}
        <div className="grid grid-cols-7">
          {calendarDays.map((date, index) => {
            const dateStr = format(date, 'yyyy-MM-dd');
            const dayPosts = postsByDate[dateStr] || [];
            const isCurrentMonth = isSameMonth(date, currentDate);
            const isTodayDate = isToday(date);

            return (
              <div
                key={index}
                className={`min-h-[120px] border border-gray-100 p-2 transition-colors ${
                  !isCurrentMonth ? 'bg-gray-50 opacity-50' : 'bg-white hover:bg-gray-50'
                } ${isTodayDate ? 'bg-green-50' : ''}`}
              >
                <div className={`font-bold text-lg mb-2 ${!isCurrentMonth ? 'text-gray-400' : 'text-slate-700'}`}>
                  {format(date, 'd')}
                </div>
                <div className="space-y-1">
                  {dayPosts.slice(0, 4).map((post, i) => (
                    <div
                      key={post.id || i}
                      onClick={() => openEditModal(post)}
                      className={`${platformColors[post.platform]} text-white text-xs px-2 py-1 rounded cursor-pointer truncate hover:opacity-90 hover:translate-y-[-1px] transition-all shadow-sm`} style={post.publication_status === "scheduled" ? {boxShadow: "0 0 0 3px #facc15"} : post.publication_status === "published" ? {boxShadow: "0 0 0 3px #22c55e"} : {}}
                    >
                      {post.publication_status === 'scheduled' && '📅 '}{post.publication_status === 'published' && '✅ '}{platformIcons[post.platform]} {post.scheduled_time?.slice(0,5)} {post.pillar?.slice(0, 12)}
                    </div>
                  ))}
                  {dayPosts.length > 4 && (
                    <div className="text-xs text-gray-500 text-center">
                      +{dayPosts.length - 4} altri
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* Edit Modal */}
      <PostEditModal
        post={selectedPost}
        isOpen={isEditModalOpen}
        onClose={closeEditModal}
        onSave={handlePostSave}
        onRegenerate={handlePostRegenerate}
        apiUrl={apiUrl}
      />
    </div>
  );
}

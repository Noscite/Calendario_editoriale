import { useState, useEffect, useRef } from 'react';
import { Bell, Check, CheckCheck, Trash2, X } from 'lucide-react';
import { format } from 'date-fns';
import { it } from 'date-fns/locale';

const API_URL = import.meta.env.VITE_API_URL || '';

const typeIcons = {
  post_published: '✅',
  post_failed: '❌',
  post_scheduled: '📅',
  system: '🔔'
};

const typeColors = {
  post_published: 'bg-green-50 border-green-200',
  post_failed: 'bg-red-50 border-red-200',
  post_scheduled: 'bg-yellow-50 border-yellow-200',
  system: 'bg-blue-50 border-blue-200'
};

export default function NotificationBell() {
  const [isOpen, setIsOpen] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [loading, setLoading] = useState(false);
  const dropdownRef = useRef(null);
  const token = localStorage.getItem('token');

  useEffect(() => {
    function handleClickOutside(event) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    fetchUnreadCount();
    const interval = setInterval(fetchUnreadCount, 30000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    if (isOpen) fetchNotifications();
  }, [isOpen]);

  const fetchUnreadCount = async () => {
    try {
      const res = await fetch(API_URL + '/api/notifications/unread-count', {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      if (res.ok) {
        const data = await res.json();
        setUnreadCount(data.count);
      }
    } catch (err) {
      console.error('Error fetching unread count:', err);
    }
  };

  const fetchNotifications = async () => {
    setLoading(true);
    try {
      const res = await fetch(API_URL + '/api/notifications?limit=20', {
        headers: { 'Authorization': 'Bearer ' + token }
      });
      if (res.ok) {
        const data = await res.json();
        setNotifications(data);
      }
    } catch (err) {
      console.error('Error fetching notifications:', err);
    }
    setLoading(false);
  };

  const markAsRead = async (id) => {
    try {
      await fetch(API_URL + '/api/notifications/' + id + '/read', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token }
      });
      setNotifications(prev => prev.map(n => n.id === id ? { ...n, is_read: true } : n));
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (err) {
      console.error('Error marking as read:', err);
    }
  };

  const markAllAsRead = async () => {
    try {
      await fetch(API_URL + '/api/notifications/read-all', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token }
      });
      setNotifications(prev => prev.map(n => ({ ...n, is_read: true })));
      setUnreadCount(0);
    } catch (err) {
      console.error('Error marking all as read:', err);
    }
  };

  const deleteNotification = async (id) => {
    try {
      await fetch(API_URL + '/api/notifications/' + id, {
        method: 'DELETE',
        headers: { 'Authorization': 'Bearer ' + token }
      });
      setNotifications(prev => prev.filter(n => n.id !== id));
      fetchUnreadCount();
    } catch (err) {
      console.error('Error deleting notification:', err);
    }
  };

  const formatTime = (dateStr) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    if (diffMins < 1) return 'Ora';
    if (diffMins < 60) return diffMins + 'm fa';
    if (diffHours < 24) return diffHours + 'h fa';
    if (diffDays < 7) return diffDays + 'g fa';
    return format(date, 'd MMM', { locale: it });
  };

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative p-2 hover:bg-gray-100 rounded-lg transition-colors"
        title="Notifiche"
      >
        <Bell size={22} className="text-gray-600" />
        {unreadCount > 0 && (
          <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {isOpen && (
        <div className="fixed right-4 top-16 mt-2 w-96 bg-white rounded-xl shadow-2xl border border-gray-200 z-[100] overflow-hidden">
          <div className="px-4 py-3 bg-gradient-to-r from-[#3DAFA8] to-[#2C3E50] text-white flex justify-between items-center">
            <h3 className="font-semibold">🔔 Notifiche</h3>
            <div className="flex items-center gap-2">
              {unreadCount > 0 && (
                <button onClick={markAllAsRead} className="text-xs bg-white/20 hover:bg-white/30 px-2 py-1 rounded flex items-center gap-1">
                  <CheckCheck size={14} /> Leggi tutte
                </button>
              )}
              <button onClick={() => setIsOpen(false)} className="hover:bg-white/20 p-1 rounded">
                <X size={16} />
              </button>
            </div>
          </div>

          <div className="max-h-[400px] overflow-y-auto">
            {loading ? (
              <div className="p-8 text-center text-gray-500">
                <div className="animate-spin w-6 h-6 border-2 border-[#3DAFA8] border-t-transparent rounded-full mx-auto mb-2"></div>
                Caricamento...
              </div>
            ) : notifications.length === 0 ? (
              <div className="p-8 text-center text-gray-500">
                <Bell size={40} className="mx-auto mb-2 opacity-30" />
                <p>Nessuna notifica</p>
              </div>
            ) : (
              notifications.map(notification => (
                <div
                  key={notification.id}
                  className={'px-4 py-3 border-b border-gray-100 hover:bg-gray-50 transition-colors ' + (!notification.is_read ? (typeColors[notification.type] || 'bg-gray-50') : '')}
                >
                  <div className="flex gap-3">
                    <div className="text-xl">{typeIcons[notification.type] || '🔔'}</div>
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between items-start gap-2">
                        <p className={'text-sm ' + (!notification.is_read ? 'font-semibold' : '') + ' text-gray-800'}>
                          {notification.title}
                        </p>
                        <span className="text-xs text-gray-400 whitespace-nowrap">{formatTime(notification.created_at)}</span>
                      </div>
                      {notification.message && (
                        <p className="text-xs text-gray-500 mt-1 line-clamp-2">{notification.message}</p>
                      )}
                      <div className="flex gap-2 mt-2">
                        {!notification.is_read && (
                          <button onClick={() => markAsRead(notification.id)} className="text-xs text-[#3DAFA8] hover:underline flex items-center gap-1">
                            <Check size={12} /> Segna letta
                          </button>
                        )}
                        <button onClick={() => deleteNotification(notification.id)} className="text-xs text-red-500 hover:underline flex items-center gap-1">
                          <Trash2 size={12} /> Elimina
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          {notifications.length > 0 && (
            <div className="px-4 py-2 bg-gray-50 text-center">
              <span className="text-xs text-gray-500">{notifications.length} notifiche - {unreadCount} non lette</span>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

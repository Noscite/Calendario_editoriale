import { create } from 'zustand';
import { auth } from '../services/api';

export const useAuthStore = create((set) => ({
  user: null,
  token: localStorage.getItem('token'),
  isAuthenticated: !!localStorage.getItem('token'),
  isLoading: false,

  login: async (email, password) => {
    set({ isLoading: true });
    try {
      const response = await auth.login(email, password);
      const { access_token } = response.data;
      localStorage.setItem('token', access_token);
      const userResponse = await auth.me();
      set({ token: access_token, user: userResponse.data, isAuthenticated: true, isLoading: false });
      return { success: true };
    } catch (error) {
      set({ isLoading: false });
      return { success: false, error: error.response?.data?.detail || 'Errore login' };
    }
  },

  register: async (data) => {
    set({ isLoading: true });
    try {
      await auth.register(data);
      set({ isLoading: false });
      return { success: true };
    } catch (error) {
      set({ isLoading: false });
      return { success: false, error: error.response?.data?.detail || 'Errore registrazione' };
    }
  },

  loginWithToken: async (token) => {
    set({ isLoading: true });
    try {
      localStorage.setItem('token', token);
      const userResponse = await auth.me();
      set({ token, user: userResponse.data, isAuthenticated: true, isLoading: false });
      return { success: true };
    } catch (error) {
      localStorage.removeItem('token');
      set({ isLoading: false });
      return { success: false, error: 'Errore validazione token' };
    }
  },

  logout: () => {
    localStorage.removeItem('token');
    set({ user: null, token: null, isAuthenticated: false });
  },

  checkAuth: async () => {
    const token = localStorage.getItem('token');
    if (!token) return set({ isAuthenticated: false });
    try {
      const response = await auth.me();
      set({ user: response.data, isAuthenticated: true });
    } catch {
      localStorage.removeItem('token');
      set({ user: null, token: null, isAuthenticated: false });
    }
  },
}));

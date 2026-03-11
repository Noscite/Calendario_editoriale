import { create } from 'zustand';
import { brands as brandsApi, projects as projectsApi, posts as postsApi } from '../services/api';

export const useDataStore = create((set, get) => ({
  brands: [],
  projects: [],
  posts: [],
  isLoading: false,
  error: null,

  fetchBrands: async () => {
    set({ isLoading: true });
    try {
      const response = await brandsApi.list();
      set({ brands: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.message, isLoading: false });
    }
  },

  createBrand: async (brandData) => {
    try {
      const response = await brandsApi.create(brandData);
      set({ brands: [...get().brands, response.data] });
      return { success: true, data: response.data };
    } catch (error) {
      return { success: false, error: error.message };
    }
  },

  deleteBrand: async (brandId) => {
    try {
      await brandsApi.delete(brandId);
      set({ brands: get().brands.filter(b => b.id !== brandId) });
      return { success: true };
    } catch (error) {
      return { success: false, error: error.message };
    }
  },

  fetchProjects: async (brandId) => {
    set({ isLoading: true });
    try {
      const response = await projectsApi.list(brandId);
      set({ projects: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.message, isLoading: false });
    }
  },

  createProject: async (projectData) => {
    try {
      const response = await projectsApi.create(projectData);
      set({ projects: [...get().projects, response.data] });
      return { success: true, data: response.data };
    } catch (error) {
      return { success: false, error: error.message };
    }
  },

  deleteProject: async (projectId) => {
    try {
      await projectsApi.delete(projectId);
      set({ projects: get().projects.filter(p => p.id !== projectId) });
      return { success: true };
    } catch (error) {
      return { success: false, error: error.message };
    }
  },

  fetchPosts: async (projectId) => {
    set({ isLoading: true });
    try {
      const response = await postsApi.listByProject(projectId);
      set({ posts: response.data, isLoading: false });
    } catch (error) {
      set({ error: error.message, isLoading: false });
    }
  },

  updatePost: async (postId, data) => {
    try {
      const response = await postsApi.update(postId, data);
      set({ posts: get().posts.map(p => p.id === postId ? response.data : p) });
      return { success: true, data: response.data };
    } catch (error) {
      return { success: false, error: error.message };
    }
  },
}));

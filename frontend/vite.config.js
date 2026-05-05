import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: (id) => {
          if (!id.includes('node_modules')) {
            return
          }

          // React core
          if (id.includes('react-dom') || /node_modules\/react\//.test(id)) {
            return 'vendor-react'
          }
          // Router
          if (id.includes('react-router')) {
            return 'vendor-router'
          }
          // Icons (lucide-react è l'unica libreria icone installata)
          if (id.includes('lucide-react')) {
            return 'vendor-icons'
          }
          // State & data fetching
          if (id.includes('zustand') || id.includes('@tanstack')) {
            return 'vendor-state'
          }
          // HTTP
          if (id.includes('axios')) {
            return 'vendor-http'
          }
          // Date utilities
          if (id.includes('date-fns')) {
            return 'vendor-utils'
          }
          // Catchall vendor
          return 'vendor-misc'
        },
      },
    },
    chunkSizeWarningLimit: 600,
  },
})

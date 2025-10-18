import {
  defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/atom.css',
        'resources/css/editor.css',
        'resources/css/calendar.css',
        'resources/js/atom.js',
      ],
      refresh: true,
    }),
  ],
  build: {
    outDir: 'dist',
    rollupOptions: {
      output: {
        manualChunks: {
          'calendar': ['@event-calendar/core']
        }
      }
    }
  },
});
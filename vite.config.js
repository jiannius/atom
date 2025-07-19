import {
  defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/atom.css', 'resources/js/atom.js'],
      refresh: true,
    }),
  ],
  server: {
    cors: true,
  },
  build: {
    outDir: 'dist',
  },
});
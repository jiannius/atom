import {
  defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/atom.css',
        'resources/css/tiptap.css',
        'resources/js/atom.js',
      ],
      refresh: true,
    }),
  ],
  build: {
    outDir: 'dist',
  },
  experimental: {
    // A font named in a stylesheet is served from /atom/ next to it, so its url is relative to the stylesheet
    renderBuiltUrl(filename, { hostType }) {
      if (hostType === 'css') {
        return { relative: true }
      }
    },
  },
});
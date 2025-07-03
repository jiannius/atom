import { defineConfig } from 'vite'
import { resolve } from 'path'
import { writeFileSync, readFileSync, readdirSync } from 'fs'
import { createHash } from 'crypto'

// --- MANUALLY ADD ALL ENTRY POINTS HERE ---
// Add new JS or CSS files as needed
const input = {
  'atom.js': resolve(__dirname, 'resources/js/atom.js'),
  'atom.css': resolve(__dirname, 'resources/css/atom.css'),
  // Add more entries as needed:
  // 'another.js': resolve(__dirname, 'resources/js/another.js'),
  // 'style.css': resolve(__dirname, 'resources/css/style.css'),
}

export default defineConfig({
  root: '.',
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    rollupOptions: {
      input,
      output: {
        entryFileNames: (chunkInfo) => {
          const name = chunkInfo.name.replace('.js', '').replace('.css', '')
          return `${name}.min.js`
        },
        chunkFileNames: '[name].min.js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name.replace('.css', '')
          return `${name}.min.css`
        }
      }
    }
  },
  plugins: [
    {
      name: 'generate-non-minified',
      writeBundle(options, bundle) {
        // Generate non-minified JS from bundled minified output
        Object.keys(bundle).forEach(fileName => {
          if (fileName.endsWith('.min.js') && bundle[fileName].type === 'chunk') {
            const minifiedContent = bundle[fileName].code
            const nonMinifiedName = fileName.replace('.min.js', '.js')
            // Simple unminification (basic formatting)
            const unminifiedContent = minifiedContent
              .replace(/;/g, ';\n')
              .replace(/{/g, ' {\n')
              .replace(/}/g, '\n}')
              .replace(/\n\s*\n/g, '\n') // Remove extra blank lines
            writeFileSync(resolve(options.dir, nonMinifiedName), unminifiedContent)
          }
        })
        // Generate non-minified CSS by copying from source
        Object.keys(input).forEach(key => {
          if (key.endsWith('.css')) {
            const src = input[key]
            const dest = resolve(options.dir, key)
            const content = readFileSync(src, 'utf8')
            writeFileSync(dest, content)
          }
        })
      }
    },
    {
      name: 'custom-manifest',
      writeBundle(options, bundle) {
        const manifest = {}
        // Only add non-minified JS and CSS to manifest
        Object.keys(input).forEach(key => {
          const outPath = resolve(options.dir, key)
          try {
            const content = readFileSync(outPath, 'utf8')
            manifest[`/${key}`] = createHash('md5').update(content).digest('hex').substring(0, 8)
          } catch (e) {}
        })
        writeFileSync(
          resolve(options.dir, 'manifest.json'),
          JSON.stringify(manifest, null, 2)
        )
      }
    }
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/js')
    }
  }
}) 
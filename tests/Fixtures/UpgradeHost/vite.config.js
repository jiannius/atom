import { defineConfig } from 'vite'
import { bunny } from 'vite-plugin-bunny-fonts'

export default defineConfig({ plugins: [bunny('inter')] })

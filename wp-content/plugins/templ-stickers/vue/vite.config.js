import { fileURLToPath, URL } from 'node:url'
import https from 'node:https'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

const WP_URL = 'https://etiketter.local/etiketter/'

// Fetches the <head> from the live WordPress page and injects it into the
// Vite dev server HTML, so wp_localize_script data and WP styles are available.
function wpHeadPlugin() {
  return {
    name: 'wp-head',
    async transformIndexHtml(html) {
      try {
        const wpHtml = await new Promise((resolve, reject) => {
          const req = https.get(WP_URL, { rejectUnauthorized: false }, (res) => {
            let data = ''
            res.on('data', chunk => data += chunk)
            res.on('end', () => resolve(data))
          })
          req.on('error', reject)
        })

        const headContent = wpHtml.match(/<head[^>]*>([\s\S]*?)<\/head>/i)?.[1] ?? ''

        // Strip the Vue bundle itself to avoid double-loading in dev
        const filtered = headContent
          .split('\n')
          .filter(line => !line.includes('templ-stickers'))
          .join('\n')

        return html.replace('</head>', filtered + '\n</head>')
      } catch (e) {
        console.warn('[wp-head] Could not fetch WordPress head:', e.message)
        return html
      }
    }
  }
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    wpHeadPlugin(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  }
})

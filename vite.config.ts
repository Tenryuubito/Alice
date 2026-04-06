import { defineConfig } from 'vite';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

import { readFileSync, existsSync } from 'fs';

const __dirname = dirname(fileURLToPath(import.meta.url));

// Read dynamic entries from TYPO3 Bridge
let manifest = {
  publicPath: '',
  entries: {}
};
const entriesPath = resolve(__dirname, 'vite.entries.json');
if (existsSync(entriesPath)) {
  try {
    manifest = JSON.parse(readFileSync(entriesPath, 'utf8'));
  } catch (e) {
    console.error('Alice: Failed to parse vite.entries.json', e);
  }
}

export default defineConfig({
  server: {
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173',
    cors: true,
    hmr: {
        protocol: 'ws',
        host: 'localhost',
        port: 5173
    }
  },
  plugins: [
    {
      name: 'alice-watcher-log',
      watchChange(id) {
          const file = id.split('/').pop()?.split('\\').pop() || id;
          console.log(`\n\x1b[33m[Alice] Change detected: ${file} (HMR Processing...)\x1b[0m`);
      }
    }
  ],
  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler'
      }
    }
  },
  build: {
    // Use absolute public path from TYPO3 Environment
    outDir: manifest.publicPath || '../../../web',
    emptyOutDir: false,
    rollupOptions: {
      input: {
        // Use absolute entries resolved by TYPO3
        ...manifest.entries
      },
      output: {
        entryFileNames: '[name].js',
        // CRITICAL: Disable code splitting for Alice scripts to ensure they are self-contained.
        manualChunks: (id) => {
            if (id.includes('web-vitals')) {
                return 'packages/alice/Resources/Public/Build/JavaScript/AuditRunner';
            }
        },
        chunkFileNames: (chunkInfo) => {
            const name = chunkInfo.name || '';
            if (name.includes('alice')) {
                return 'packages/alice/Resources/Public/Build/JavaScript/[name].js';
            }
            // Dynamic fallback for other extensions
            return '[name]-[hash].js';
        },
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          
          // Determine the extension part from the original file path
          const originalPath = assetInfo.originalFileNames?.[0] || '';
          // Extension might be in web/packages/ or vendor/
          const match = originalPath.match(/(packages|vendor)\/([^\/]+)\//);
          const extName = match ? match[2] : 'alice';

          if (name.endsWith('.css')) {
            if (extName === 'alice') {
                return 'packages/alice/Resources/Public/Build/Css/Backend.css';
            }
            return `packages/${extName}/Resources/Public/Build/Css/[name].[ext]`;
          }
          return 'Resources/Public/Build/Assets/[name].[ext]';
        }
      }
    }
  }
});

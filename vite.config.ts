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
    // Force output into the extension directory to ensure TYPO3 resource resolution works
    outDir: resolve(__dirname),
    emptyOutDir: false,
    rollupOptions: {
      input: {
        ...manifest.entries
      },
      output: {
        entryFileNames: 'Resources/Public/Build/JavaScript/[name].js',
        // Disable code splitting for Alice scripts to ensure they are self-contained.
        manualChunks: (id) => {
            if (id.includes('web-vitals')) {
                return 'Resources/Public/Build/JavaScript/AuditRunner';
            }
        },
        chunkFileNames: 'Resources/Public/Build/JavaScript/[name].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'Resources/Public/Build/Css/Backend.css';
          }
          return 'Resources/Public/Build/Assets/[name].[ext]';
        }
      }
    }
  }
});

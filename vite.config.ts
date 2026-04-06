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

// Transform entries to use clean base names for output files
const cleanEntries: Record<string, string> = {};
for (const [key, value] of Object.entries(manifest.entries)) {
    const parts = key.split('/');
    const name = parts[parts.length - 1];
    cleanEntries[name] = value as string;
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
    // Set outDir to the specific Build folder within Resources/Public
    outDir: resolve(__dirname, 'Resources/Public/Build'),
    emptyOutDir: true,
    rollupOptions: {
      input: cleanEntries,
      output: {
        entryFileNames: 'JavaScript/[name].js',
        chunkFileNames: 'JavaScript/[name].js',
        assetFileNames: (assetInfo) => {
          const name = assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'Css/Backend.css';
          }
          return 'Assets/[name].[ext]';
        }
      }
    }
  }
});

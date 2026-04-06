# Alice Extension (tenryuubito/alice)

**Alice** is a high-performance TYPO3 bridge for modern asset processing (Vite) and a centralized performance analytics dashboard. It is designed to decouple asset compilation from individual extensions while providing deep integration into the TYPO3 ecosystem.

## Features

- ⚡ **High-Performance Vite Bridge**: Sub-millisecond rebuilds and Hot Module Replacement (HMR).
- 📊 **Performance Analytics**: Integrated backend module for tracking Core Web Vitals (LCP, CLS, INP).
- 🖼️ **Auto-LazyLoading**: Middleware that automatically adds `loading="lazy"` to images.
- 🏗️ **Centralized Build Engine**: A single Vite configuration that discovers entries across multiple extensions.

---

## Developer Guide

### Prerequisites

- TYPO3 v13 or v14
- Node.js (v18+)
- `php` available in the command line

### 1. Installation

The extension is installed via Composer. To set up the Node.js dependencies for asset processing, run the following command from your project root:

```bash
./vendor/bin/typo3 alice:bundle --setup
```

### 2. Registering Asset Entries

Alice discovers entry points (SCSS/TypeScript) via TYPO3 configuration. Add your files to the `additional_entries` registry in your `ext_localconf.php` or `settings.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice']['additional_entries'][] = 'EXT:your_extension/Resources/Private/Scss/main.scss';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice']['additional_entries'][] = 'EXT:your_extension/Resources/Private/TypeScript/App.ts';
```

### 3. Development Mode (HMR)

For the best development experience with instant updates (Hot Module Replacement), start the Vite dev server from the project root:

```bash
./vendor/bin/typo3 alice:bundle --dev
```

**Important**: In `Development` context, Alice automatically redirects asset URLs to `http://localhost:5173`. Make sure the dev server is running!

### 4. Build for Production

To generate optimized, static files for deployment, run the build command from your project root:

```bash
./vendor/bin/typo3 alice:bundle --build
```

This will output the compiled assets into the `Resources/Public/Build/` directory of the respective extensions.

---

## Editor Guide

### Performance Dashboard

Alice provides a top-level backend module located in the primary sidebar under **Alice**.

- **Dashboard**: View the current vitals of your site (LCP, CLS, INP).
- **Thresholds**: The module highlights metrics that exceed the configured targets.
- **Site Configuration**: Admins can manage global performance thresholds directly within the module.

---

## Configuration

Settings can be changed via **System > Settings > Extension Configuration > Alice**:

- **Auto LazyLoading**: Toggle the automatic `loading="lazy"` middleware.
- **LCP Target**: Set the target value for Largest Contentful Paint (default: 2.5s).
- **CLS Target**: Set the target value for Cumulative Layout Shift (default: 0.1).
- **INP Target**: Set the target value for Interaction to Next Paint (default: 200ms).

---

## Technical Details

### Vite Bridge Logic

1.  The `alice:export-vite-config` command reads all registered entries from TYPO3.
2.  It generates a `vite.entries.json` containing the mapping.
3.  The `vite.config.ts` uses this manifest to define the Rollup input.
4.  The `ViteAssetMiddleware` rewrites URLs in `Development` context to point to the source files on the dev server.

### Middlewares

Alice registers two middlewares in the `frontend` stack:
1.  `tenryuubito/alice/vite-bridge`: Rewrites asset URLs during development.
2.  `tenryuubito/alice/lazy-loading`: Post-processes the HTML to optimize image loading.

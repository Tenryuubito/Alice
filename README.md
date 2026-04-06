# Alice Extension (tenryuubito/alice)

**Alice** is a high-performance TYPO3 bridge for modern asset processing (Vite) and a centralized performance & SEO analytics dashboard. It is designed to decouple asset compilation from individual extensions while providing deep integration into the TYPO3 ecosystem.

## Features

- ⚡ **High-Performance Vite Bridge**: Sub-millisecond rebuilds and Hot Module Replacement (HMR).
- 📊 **Performance Analytics**: Integrated dashboard for tracking Core Web Vitals (LCP, CLS, INP).
- 🔍 **SEO & Link Audit**: Automated server-side checks for meta tags, image accessibility, and link reachability.
- 🔗 **Link Reachability**: Monitors status codes and load times for both internal and external links.
- 🖼️ **Image Optimization**: Automated audit for missing alt-tags, dimensions, and lazy-loading compliance.
- 🏗️ **Centralized Build Engine**: A single Vite configuration that discovers entries across multiple extensions.

---

## Developer Guide

### Prerequisites

- TYPO3 v13 or v14
- Node.js (v18+)
- Local PHP environment (or DDEV)

### 1. Installation

The extension is installed via Composer. To set up the Node.js dependencies for asset processing, run the following command (using DDEV as example):

```bash
ddev typo3 alice:bundle --setup
```

### 2. Registering Asset Entries

Alice discovers entry points (SCSS/TypeScript) via TYPO3 configuration. Add your files to the `additional_entries` registry in your `ext_localconf.php` or `settings.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice']['additional_entries'][] = 'EXT:your_extension/Resources/Private/Scss/main.scss';
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice']['additional_entries'][] = 'EXT:your_extension/Resources/Private/TypeScript/App.ts';
```

### 3. Development Mode (HMR)

For the best development experience with instant updates, start the Vite dev server:

```bash
ddev typo3 alice:bundle --dev
```

**Important**: In `Development` context, Alice automatically redirects asset URLs to the dev server. The `ViteAssetMiddleware` handles connectivity checks to prevent errors if the server is offline.

### 4. Build for Production

To generate optimized, static files for deployment:

```bash
ddev typo3 alice:bundle --build
```

---

## Editor Guide

### Performance & Audit Dashboard

Alice provides a top-level backend module located in the primary sidebar.

- **Vitals Dashboard**: Real-time measurements of LCP, CLS, and INP performed directly in an isolated iframe.
- **SEO Check**: Validates meta titles, descriptions, and robots tags.
- **Bilder-Check**: Lists all images on the page, highlighting missing Alt-tags or missing width/height attributes.
- **Link-Check**: Scans all links on the page, categorizes them (Internal/External), and verifies reachability with a 3s timeout.

---

## Configuration

Settings can be managed via **Site Configuration** (for Site Roots) or **Extension Configuration**:

- **Auto LazyLoading**: Toggle the automatic `loading="lazy"` middleware for all frontend images.
- **Thresholds**: Define custom "Good/Poor" targets for LCP, CLS, and INP.
- **Audit Rules**: Enable/Disable specific audit components across the site.

---

## Technical Details

### Audit Engine

The Alice Audit engine uses a hybrid approach:
- **Client-Side**: Injected scripts measure real-user metrics (Core Web Vitals) within a sandboxed iframe.
- **Server-Side**: The `BackendController` performs deep HTML analysis and HTTP reachability tests for assets and links.

### Middlewares

Alice registers two middlewares in the `frontend` stack:
1. `tenryuubito/alice/vite-bridge`: Manages asset URL rewriting and dev server proxies.
2. `tenryuubito/alice/lazy-loading`: Post-processes HTML to optimize image loading attributes.

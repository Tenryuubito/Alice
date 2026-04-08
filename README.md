# Alice - TYPO3 Asset Bridge & Performance Dashboard

**Alice** is a comprehensive TYPO3 extension designed to streamline modern frontend development and provide deep insights into website performance and SEO health. It acts as a bridge for Vite-based asset processing while offering a centralized Command Center for performance audits and technical SEO monitoring.

## Key Features

### ⚡ Vite Build System
*   **Infrastructure Decoupling**: Build assets from any extension using a centralized build engine.
*   **HMR Support**: Instant Hot Module Replacement for Stylesheets and TypeScript during development.
*   **Environment Aware**: Automatic switching between production bundles and development source files.

### 📊 Performance Analytics (Core Web Vitals)
*   **Real-User Metrics**: Measures LCP (Largest Contentful Paint), CLS (Layout Shift), and INP (Interaction to Next Paint).
*   **Iframe Sandbox**: Audits are performed in an isolated, technically visible environment to ensure accurate paint metrics.
*   **Batch Auditing**: Analyze entire site trees with a single click from the Global Dashboard.

### 🔍 Technical SEO & Accessibility
*   **Meta Discovery**: Automated validation of Title tags, Meta Descriptions, and Robots directives.
*   **Image Health**: Comprehensive audit of Alt-text availability, dimension attributes, and lazy-loading compliance.
*   **Link Reachability**: Server-side verification of internal and external link status codes with detailed response time reporting.

---

## Installation & Setup

### 1. Requirements
*   TYPO3 v12.4+ or v14.2+
*   Node.js v18.0+
*   Composer-based TYPO3 installation

### 2. Node.js Environment
Alice manages its own Node dependencies. Initialize or build the environment via the TYPO3 CLI:

```bash
# Setup dependencies
./vendor/bin/typo3 alice:bundle --setup

# Start development server (HMR)
./vendor/bin/typo3 alice:bundle --dev

# Production build
./vendor/bin/typo3 alice:bundle --build
```

### 3. Registering Entry Points
Register your extension's assets in `ext_localconf.php` or `Configuration/Services.yaml`:

```php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['alice']['additional_entries'][] = 'EXT:my_ext/Resources/Private/TypeScript/Main.ts';
```

---

## Dashboard Usage

### Commands & Overview (UID 0)
The Alice module at the root node (UID 0) serves as a **Global Command Center**.
*   **Site Overview**: View the performance status of all recognized sites.
*   **Batch Run**: Trigger performance audits for all pages across all domains.
*   **Issue Aggregation**: A centralized list of all SEO and performance issues found across the entire TYPO3 instance.

### Page-Specific Audit (UID > 0)
Selecting a specific page in the page tree provides a detailed drill-down into its metrics.
*   **Live Preview Audit**: Trigger an audit that renders the page in the background and reports real-time vitals.
*   **Detailed Findings**: Accordions for Images, Links, and SEO provide actionable feedback.

---

## Configuration

### Site Configuration
Configure audit behavior per site in the Site Management module:
*   **Performance Thresholds**: Define targets for LCP, CLS, and INP.
*   **Auto Lazy-Loading**: Toggle the automatic `loading="lazy"` middleware.

### Extension Settings
Manage global defaults via the Extension Configuration:
*   **Asset Paths**: Customize the build output directories.
*   **Audit Runner**: Configure the background measurement timeout.

---

## Technical Architecture

### Audit Service
The audit workflow is split into two phases:
1.  **Frontend Capture**: An iframe renders the target page and executes the `AuditRunner.ts` (using Google's Web-Vitals library) to capture technical performance.
2.  **Backend Analysis**: The `BackendController` parses the HTML server-side to identify SEO flaws, accessibility issues, and link brokenness.

### Asset Middleware
Alice utilizes PSR-15 middlewares to intercept frontend requests:
*   `ViteAssetMiddleware`: Maps production assets to the Vite Dev Server in development context.
*   `LazyLoadingMiddleware`: Injects loading attributes into image tags to improve initial paint time.

---

(c) 2026 Tenryuubito

# Amordad Architecture Studio — Website

Portfolio website for Amordad Architecture Studio, published in Persian (`fa`, RTL), English (`en`) and Turkish (`tr`).

- **Public site:** plain PHP templates (no framework, no build step) in the project root.
- **CMS / admin:** [Cockpit CMS](https://getcockpit.com) 2.14.1, installed in `vorud/` and served at `/vorud` (renamed from `/cockpit`).
- **Production:** https://amordad.backends.ir (cPanel, Apache + PHP).

The public pages read content directly from Cockpit through its PHP API (`Cockpit::instance()`). There is no REST call between the site and the CMS.

---

## Contents

- [Requirements](#requirements)
- [Project structure](#project-structure)
- [Local development](#local-development)
- [Configuration and secrets](#configuration-and-secrets)
- [Content model](#content-model)
- [How the public site works](#how-the-public-site-works)
- [AI translation](#ai-translation)
- [Deployment](#deployment)
- [Security notes](#security-notes)
- [Changes to Cockpit core](#changes-to-cockpit-core)
- [Knowledge graph (graphify)](#knowledge-graph-graphify)
- [Troubleshooting](#troubleshooting)

---

## Requirements

| | |
|---|---|
| PHP | 8.3+ (Cockpit's requirement; 8.2 works locally) with `pdo_sqlite`, `sqlite3`, `gd`, `curl`, `mbstring`, `fileinfo`, `openssl`, `dom` (and `exif`, `intl` recommended) |
| Web server | Apache with `mod_rewrite` and `mod_headers` (production); PHP's built-in server for local development |
| Database | SQLite (bundled with Cockpit, files in `vorud/storage/data/`) |

---

## Project structure

```
.
├── index.php              # "/" → redirects to /fa/, /en/ or /tr/ (Accept-Language)
├── page-home.php          # /{locale}/                    2×2 tile grid
├── page-category.php      # /{locale}/category/{slug}     project grid
├── page-project.php       # /{locale}/project/{slug}      project detail + gallery + tabs
├── page-404.php           # Not-found page
├── sitemap.php            # /sitemap.xml
├── thumb.php              # Legacy & cropped image resizer (300×300 square thumbs, og:image)
├── img.php                # Dynamic on-demand responsive WebP variant generator & redirect
├── deploy-warmup.php      # Secure single-use webhook for post-deploy cache warmup
├── check.php              # Server diagnostics — CLI only: php check.php
├── router.php             # Router for PHP's built-in server (mirrors .htaccess)
├── .htaccess              # Routes, HTTPS redirect, security and cache headers
├── robots.txt
├── build-deploy-zip.sh    # Builds amordad-deploy.zip & auto-deploys to cPanel + warm-up signal
├── pull-server-backup.sh  # Pulls full production backup & syncs locally
├── package.json           # NPM task runner scripts (start, check, lint, build, deploy, pull)
├── includes/
│   ├── config.php         # Site constants: locales, SITE_URL, image presets, UI strings
│   ├── helpers.php        # Content access, URLs, media, SEO helpers
│   ├── images.php         # Image optimization engine, WebP variants & auto-heal sweeper
│   ├── richtext.php       # HTML allow-list sanitizer (used by pages and the AI addon)
│   ├── bootstrap.php      # Per-page setup ($locale, $settings, $categories…)
│   ├── header.php
│   └── footer.php
├── tools/                 # CLI maintenance tools (dry-run & execute)
│   ├── optimize-masters.php # Scans and downscales master images to 1920px ceiling
│   └── warm-images.php      # Pre-generates WebP variants for all active content
├── docs/
│   └── IMAGE_OPTIMIZATION.md # Full architecture reference for image optimization pipeline
├── assets/
│   ├── css/styles.css
│   ├── js/app.js          # Tiles slideshow, tabs, lightbox, mobile menu, language select
│   ├── fonts/             # Vazirmatn variable font (single file)
│   └── images/flags/
├── vorud/                 # Cockpit CMS
│   ├── config/
│   │   ├── config.php     # Cockpit config (versioned, no secrets)
│   │   ├── bootstrap.php  # Upload hook (assets.asset.upload) & Image Optimization admin routes
│   │   ├── theme.css      # Cockpit admin styles (dark theme & RTL support)
│   │   ├── controllers/   # Custom admin controllers (ImageOptimization.php)
│   │   └── views/         # Custom admin views (image-optimization.php)
│   ├── .env               # Secrets — NOT in git (see .env.example)
│   ├── addons/
│   │   ├── AdminRtl/      # Direction control: Persian fields RTL, EN/TR LTR
│   │   ├── AiTranslate/   # AI translation of content (OpenRouter + Gemini)
│   │   ├── Hardening/     # Login throttling and 2FA guard
│   │   └── ModelSync/     # Refreshes Cockpit's model cache when *.model.php files change
│   └── storage/
│       ├── content/       # Content model definitions (*.model.php) — in git
│       ├── data/          # SQLite databases — NOT in git
│       └── uploads/       # Uploaded images (and _v/ WebP variants cache)
├── reference/             # Design reference screenshots
└── TODO.md                # Audit report: known issues and their status
```

---

## Local development

```bash
npm start   # or: php -S localhost:8000 router.php
```

- Site: http://localhost:8000/fa/
- Admin: http://localhost:8000/vorud

`router.php` reproduces the `.htaccess` rules for the built-in server. Locally, `SITE_URL` follows the request host (`localhost:8000`). In production it is always `https://amordad.backends.ir`.

### NPM Scripts (Task Runner)

The project includes a lightweight `package.json` with zero runtime dependencies as a task runner:

| Command | Action |
|---|---|
| `npm start` / `npm run dev` | Runs the local PHP development server (`localhost:8000`) |
| `npm run check` | Diagnostic health check for PHP, extensions, and Cockpit storage |
| `npm run lint` | Syntax check (`php -l`) across site templates and Cockpit config |
| `npm run build` | Builds production deployment archive locally (`--no-upload`) |
| `npm run build:code` | Builds code-only deployment archive locally (`--code-only --no-upload`) |
| `npm run deploy` | Builds, uploads via FTP, cleans old files, extracts, and sets permissions |
| `npm run deploy:code` | Builds and deploys code only (preserves server databases & uploads) |
| `npm run pull` | Pulls full production backup (databases, uploads, code) & syncs locally |
| `npm run graph:update` | Updates the Graphify structural knowledge graph |

> The SQLite databases are **not in git**. To sync the latest databases and uploads from the live server at any time, simply run `npm run pull`.

The project also includes `.claude/launch.json` with the same command, for running the dev server from the Claude desktop app.

---

## Configuration and secrets

| File | In git | Purpose |
|---|---|---|
| `includes/config.php` | yes | Site constants: `PRODUCTION_URL`, locales, RTL locales, image size presets (`IMAGE_SIZES`), interface strings (`UI_STRINGS`) |
| `vorud/config/config.php` | yes | Cockpit config: `sec-key` (read from `.env`), strict session cookie, allowed upload types |
| `vorud/.env` | **no** | `COCKPIT_SEC_KEY` — random secret that signs Cockpit's JWTs. Template: `vorud/.env.example` |
| `vorud/storage/data/.sec-key` | **no** | Generated automatically **only if** `COCKPIT_SEC_KEY` is missing |
| Site Settings (admin) | in DB | OpenRouter and Gemini API keys, OpenRouter model |

Create a new `sec-key`:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Changing the `sec-key` signs admins out (or forces a page reload).

---

## Content model

Models are defined in `vorud/storage/content/*.model.php` and edited in the Cockpit admin. Translatable fields use a locale suffix: `{field}_fa`, `{field}_en`, `{field}_tr`.

| Model | Type | Fields |
|---|---|---|
| `projects` | collection | `title_*`, `slug` (auto-generated number), `featuredImage`, `categories` (checkbox selector), `projectInformation_*`, `description_*`, `content_*` (rich text), `projectGallery` (multiple assets) |
| `categories` | collection | `name_*`, `slug` (normalized to `a-z0-9-`, unique), `showInNavigation`, `navigationOrder` |
| `homePage` | singleton | `tiles[]`: `label_*`, `category` (link), `images[]` (rotating slideshow) |
| `siteSettings` | singleton | `siteName_*`, `headerLogo`, `footerLogo`, `footerLogoLabel_*`, `copyright_*`, `defaultMetaTitle_*`, `defaultMetaDescription_*`, `defaultOgImage`, AI keys, `openrouter_model` |

**Publishing:** projects and categories use Cockpit's own **State** control only (sidebar of the edit page, and the colored dot in lists). An item appears on the site and in the sitemap only when its State is *Published* (`_state = 1`). *Unpublished* and *Archive* hide it. New items start as *Unpublished*.

**Slugs:** project slugs are incremental numbers (`/en/project/4`). A persistent counter makes sure a deleted project's number is never reused.

**Copyright:** `copyright_*` can contain `{year}` (Gregorian) or `{jyear}` (Solar Hijri). Without a placeholder, `© {year}` is added automatically.

---

## How the public site works

**Routing** (`.htaccess`, mirrored by `router.php`):

| URL | File |
|---|---|
| `/` | `index.php` → 302 to the visitor's language (default `fa`) |
| `/{fa\|en\|tr}/` | `page-home.php` |
| `/{locale}/category/{slug}` | `page-category.php` |
| `/{locale}/project/{slug}` | `page-project.php` |
| `/sitemap.xml` | `sitemap.php` |
| anything else | `page-404.php` (status 404) |

**Languages:** `localized()` falls back to Persian when a translation is missing (for visible content). `localizedStrict()` does not fall back, and is used for meta tags so en/tr pages never get Persian descriptions.

**Images & Responsive WebP Pipeline:**
- **Master Image Ceiling (1920px):** Any raw photo or 3D rendering uploaded via Cockpit Admin or API is intercepted by `assets.asset.upload` in `vorud/config/bootstrap.php` and scaled in-place to fit a 1920px bounding box (`IMG_MAX_DIMENSION = 1920`), auto-rotated via EXIF orientation, and re-compressed to clean web standards.
- **Responsive WebP Delivery:** Proportional image requests automatically resolve to pre-calculated WebP variants (`300`, `600`, `800`, `1200`, `1600`, `1920`) stored in `vorud/storage/uploads/_v/` via `mediaUrl($asset, 'preset')` or `img_variant_url()`.
- **Dynamic On-Demand Fallback:** If a requested variant has not been pre-generated yet, the page links to `/img/{id}/{width}` (`img.php`), which generates the static WebP file and 302-redirects to it; subsequent visits hit the static file directly with zero PHP execution.
- **Cropped Presets:** Aspect-ratio-cropped presets (e.g. square `thumbnail` 300×300 in the project info tab gallery, or `og` 1200×630) are handled by `thumb.php`, reading the already-downscaled masters.
- **Cockpit Admin Dashboard:** Navigate to *Settings → System → Image Optimization* for real-time storage metrics, cache purge, and batch variant rebuilding with progress bar.
- **Automated Warmup:** Post-deploy webhook `deploy-warmup.php` is called automatically by `build-deploy-zip.sh` with a single-use token to warm all active variants on the server.
- See [docs/IMAGE_OPTIMIZATION.md](docs/IMAGE_OPTIMIZATION.md) for the complete architecture and CLI reference (`tools/optimize-masters.php` and `tools/warm-images.php`).

**Rich text** from the admin and from AI translation is passed through `sanitizeRichText()` (allow-list: paragraphs, emphasis, lists, headings, links, tables, `dir`) before it is printed.

**SEO:** canonical URL, `hreflang` for all locales plus `x-default`, Open Graph and Twitter tags, Organization JSON-LD, and a sitemap with real `lastmod` dates.

**Static assets:** CSS and JS URLs carry `?v=<mtime>`, and `/assets/` is served with a one-year immutable cache.

---

## AI translation

The `AiTranslate` addon (`vorud/addons/AiTranslate/`) translates Persian content into English and Turkish:

- **Project edit page →** «ترجمه هوشمند با AI» button: translates title, project information, description and content in two requests and saves them. If one part fails, the other is still saved and the error is shown.
- **Site Settings →** «ترجمه مابقی متون سایت» button: translates category names, footer label, copyright, default meta tags and home tile labels (never the site name).

**Providers** (keys are entered in *Site Settings*):

1. **OpenRouter (primary).** The model is chosen in *Site Settings → OpenRouter Model*. The list is fetched live from OpenRouter on every page load: free models first, then paid models (💲, with price per million tokens) after a divider. The default is `openrouter/free`, an automatic router that picks an available free model on each request. Reasoning is turned off for speed. If the chosen model no longer exists, `openrouter/free` is used instead.
2. **Google Gemini (fallback).** Tried in this order: `gemini-3.8-flash` → `gemini-3.7-flash` → `gemini-flash-latest` → `gemini-3.6-flash` → `gemini-3.5-flash`. "High demand" (503) moves on to the next model. Thinking is set to *low*.

Each provider has its own 60-second budget per request. A typical project translation takes 20–40 seconds.

**Limits:** the current OpenRouter key is free tier: **50 free requests per day**, and paid models fail without credit. Each project translation uses at least 2 requests.

---

## Deployment & Sync

Automated two-way deployment and synchronization between local development and the production cPanel server (`https://amordad.backends.ir`, path `/home/h355112/amordad`).

### Quick Commands

```bash
npm run deploy         # Full deploy: build zip, upload FTP, cPanel clean + extract + chmod
npm run deploy:code    # Code-only deploy: keeps production database and uploaded assets intact
npm run pull           # Pull production backup: compresses on cPanel, downloads & syncs locally
npm run build          # Local build test only (no upload, no remote changes)
```

### How Deployment Works

The deploy pipeline is handled by `build-deploy-zip.sh` (or `npm run deploy`):

1. **Safety & Health Checks:** Verifies syntax with `php -l`, runs diagnostic checks, and ensures local SQLite databases are in a consistent state without WAL locks.
2. **Archive Packaging:** Packs code, modules, addons, and (unless `--code-only`) clean SQLite snapshots and media uploads into `amordad-deploy.zip`. Excludes dev tools, tests, docs, and caches.
3. **Automated FTP Upload:** Authenticates using `~/.netrc` (no passwords stored in script or repository) and uploads `amordad-deploy.zip` directly to the server folder `/amordad/`.
4. **Remote cPanel Automation:** Uses the cPanel UAPI token in `~/.cpanel_token`:
   - Permanently deletes previous website files in `amordad/` (bypassing the `.trash` folder to protect quota).
   - Extracts `amordad-deploy.zip` directly in place into `/home/h355112/amordad`.
   - Sets secure permissions (`0775`) on `vorud/storage`.
   - Immediately deletes `amordad-deploy.zip` from the server to eliminate security risks.
   - **Post-Deploy Cache Warmup:** Sends an automated, authenticated HTTP POST request to `deploy-warmup.php` with a single-use token to pre-generate all responsive WebP variants directly on the server.

### Reverse-Sync (`npm run pull`)

If production content, new projects, or client images are updated in the live Cockpit admin:

1. Run `npm run pull` (or `./pull-server-backup.sh`).
2. cPanel compresses `/home/h355112/amordad` into a temporary zip.
3. Downloads the archive via FTP using `~/.netrc`.
4. Deletes the remote zip immediately from the server.
5. Extracts and syncs the latest SQLite databases (`content.sqlite`, `system.sqlite`, `app.sqlite`) and `vorud/storage/uploads/` locally (ignoring thumbnail caches).
6. Runs local health diagnostics (`php check.php`) to confirm all tables and media assets are intact.

### CLI Flags for `build-deploy-zip.sh`

```bash
./build-deploy-zip.sh [options]
  --code-only       Deploy code changes only (preserves server databases and uploads)
  --no-upload       Build the deployment zip locally without uploading or modifying the server
  --no-extract      Upload via FTP but skip the remote cPanel cleanup and extract step
  --cpanel-token T  Pass custom cPanel API token (defaults to ~/.cpanel_token)
  --domain URL      Override the production URL (default: https://amordad.backends.ir)
```

---

## Git & GitHub Repository

The source repository is private and hosted on GitHub:
- **Repository:** `https://github.com/naazeri/Amordad.git` (Private)

### GitHub Multi-Account Protocol (`gh`)

The workstation uses multiple GitHub accounts. When interacting with this repository via Git or GitHub CLI (`gh`):

1. **Switch active account to `naazeri`:**
   ```bash
   gh auth switch --user naazeri
   ```
2. **Perform the Git operation** (e.g. `git push origin main`).
3. **Immediately switch back to `enolightit`:**
   ```bash
   gh auth switch --user enolightit
   ```

---

## Security notes

- **Secrets:** never commit `vorud/.env`, `vorud/storage/data/*.sqlite` or `.sec-key`. All are in `.gitignore`.
- **Blocked over HTTP:** archives, databases (including `-wal`/`-shm`), logs, `*.md`, `includes/`, the old `/cockpit` path and the Cockpit installer.
- **Admin:** session cookie is `SameSite=Strict`. The `Hardening` addon limits login attempts to 10 per 15 minutes per IP, and rejects 2FA logins for users who don't have 2FA enabled. Enabling 2FA for every admin is recommended.
- **AI routes:** POST only, with Cockpit's CSRF token and ACL checks.
- **Uploads:** only `jpg, jpeg, png, webp, gif, pdf` (no SVG).
- **Git history:** commits before `94db377` contain the SQLite databases, including old API keys and password hashes. Before pushing this repository anywhere, rotate those secrets and/or rewrite the history.

See [TODO.md](TODO.md) for the full audit (60 issues), what was fixed and what is left.

---

## Changes to Cockpit core

Project-specific behavior lives in addons where possible. These core files are still **patched** and will be overwritten by Cockpit's built-in *Updater*. Re-apply or move them to an addon after any Cockpit update:

| File | Change |
|---|---|
| `vorud/modules/Content/views/collection/item.php` | Preview button for projects, hidden slug field |
| `vorud/modules/Content/views/collection/items.php` | Slug column hidden in the projects list |
| `vorud/modules/Content/Controller/Collection.php` | Supporting changes for the above |
| `vorud/modules/Assets/assets/vue-components/field-asset.js` | Gallery thumbnails for `{image: …}` values |

Use `git log -p -- <file>` to see each change.

---

## Knowledge graph (graphify)

`graphify-out/` (not in git) holds a graphify knowledge graph of the project's own code and docs, which AI coding agents query instead of reading every file.

- Only project files are graphed. Upstream Cockpit (`vorud/lib`, `vorud/modules`, Cockpit's root files), storage, design references and tooling folders are excluded in `.graphifyignore`.
- After changing code, refresh the graph (AST only, no API cost):

  ```bash
  graphify update .
  ```

  Add `--force` when the rebuild has fewer nodes than before (for example after deleting code or extending `.graphifyignore`).
- Example query:

  ```bash
  graphify query "how does AI translation fall back to Gemini"
  ```

---

## Troubleshooting

| Problem | Check |
|---|---|
| Thumbnails return 400 | The requested `w`/`h` pair is not in `IMAGE_SIZES` |
| A project does not appear on the site | Its State must be *Published* (sidebar of the edit page) |
| Admin model changes don't show up on production | `ModelSync` refreshes the cache on the next request. If the host keeps OPcache for a long time, wait a minute or restart PHP (cPanel → MultiPHP Manager / LiteSpeed). As a last resort use *Settings → System → Flush cache* in `/vorud` |
| A new addon doesn't load on production | `config.php` drops Cockpit's module list when the addon folders change; check that the addon folder was extracted to `vorud/addons/` |
| AI translation fails or is slow | Read the error toast (it shows which provider/model failed); check the OpenRouter daily quota and Gemini "high demand" |
| "Too many login attempts" (429) | Wait 15 minutes; the counter resets after a successful login |
| Admin pages break locally only | Always start the dev server with `router.php` as shown above |

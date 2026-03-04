# Etiketter — Project Guide for Claude

## What this project is

A WordPress + WooCommerce website where customers can design and order custom stickers. The site uses a custom WordPress plugin (`templ-stickers`) that provides a Vue 3 sticker designer embedded via shortcode, plus full WooCommerce cart/order integration.

## Repository structure

All custom code lives in:

```
wp-content/
  plugins/templ-stickers/     # Main custom plugin
    templ-stickers.php        # Plugin entry point (PHP backend)
    vue/                      # Vue 3 frontend app
      src/
        views/FormView.vue    # Sticker designer form
        components/StickerPreview.vue
        svgGenerator.ts       # SVG generation logic
        api.ts                # REST API calls
        cart.ts               # WooCommerce cart integration
        i18n.ts               # i18n helper (reads from wp_localize_script)
        textCalculations.ts   # Max lines/chars based on sticker dimensions
        types/index.ts        # TypeScript interfaces
      dist/                   # Compiled output (committed, loaded by PHP)
  mu-plugins/
    acf-local-json.php        # Routes ACF save/load to acf-json/
    enable-automatic-updates.php
  acf-json/                   # ACF field group definitions (version controlled)
deploy.sh                     # rsync deploy to production
```

## Tech stack

- **Backend:** PHP, WordPress, WooCommerce, ACF (Advanced Custom Fields)
- **Frontend:** Vue 3 (Composition API), TypeScript, Vite
- **Package manager:** pnpm (use `pnpm` not `npm`)
- **Build:** `pnpm run build` inside `vue/` — outputs to `vue/dist/`

## Key conventions

- The Vue app is loaded via the `[templ-stickers]` shortcode as an ES module
- Sticker designs are stored as a custom post type (`sticker`) with a UUID
- The UUID is passed as a URL query param (`?sticker-uuid=...`) for editing
- Routing is done via `history.replaceState()` — no Vue Router page navigation
- Translations are injected by PHP via `wp_localize_script()` into `window.templStickersI18n`; access them with the `__()` helper in `i18n.ts`
- ACF options page ("Inställningar") controls all configurable data: sizes, colors, symbols

## REST API endpoints (plugin)

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/wp-json/templ-stickers/v1/form-fields` | Sizes, colors, symbols config |
| POST | `/wp-json/templ-stickers/v1/submit-sticker` | Create new sticker |
| PUT | `/wp-json/templ-stickers/v1/sticker/{uuid}` | Update sticker |
| GET | `/wp-json/templ-stickers/v1/sticker/{uuid}` | Fetch sticker data |
| GET | `/wp-json/templ-stickers/v1/sticker/{uuid}/svg` | Serve SVG as image |

## Deployment

```sh
./deploy.sh
```

Rsyncs plugin, mu-plugins, and acf-json to production server (`etiketter:~/app_18882/`).

## Localization

Generate `.pot` file:
```sh
composer make-pot
```

Translations live in `templ-stickers/languages/`. Frontend strings are registered in PHP and accessed via `i18n.ts`.

# BFD Core

Functionality plugin. Each feature is a module in `includes/<Name>/<Name>_Module.php` implementing `Contracts\Module`.
Autoloading is PSR-4 (`BFD\Core\` => `includes/`). Add or remove modules via the `bfd_core_modules` filter.

| Module | What it does |
|---|---|
| Fields | `Sanitizer`: schema-driven sanitizing for every field type |
| Sections | Page section builder (17 sections, visual editor, drag-drop, live preview), Custom Sections post type, template engine |
| PostTypes | Services, Projects, Testimonials + taxonomies |
| Forms | Form builder (multi-step, conditional fields), submit handling (nonce, honeypot, timing, rate limit, Turnstile, uploads), auto-reply, submissions admin |
| Seo | Per-post SEO, Open Graph/Twitter, JSON-LD, breadcrumbs, sitemap tweaks, redirects, analytics/verification tags, WebP output |
| Security | Login limiter, two-factor (TOTP), activity log, XML-RPC/REST/author hardening, security headers, optional CSP |
| Admin | Options page (design, integrations, SEO defaults), pages use the section builder instead of Gutenberg |

Helpers for themes: `bfd_option()`, `bfd_render_sections()`, `bfd_has_sections()`, `bfd_breadcrumbs()`, `bfd_render_form()`.

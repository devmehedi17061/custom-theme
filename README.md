# BFD site framework

WordPress + a custom lightweight theme (`bfd-theme`) + a functionality plugin (`bfd-core`). No page builder dependency.

- **Section builder** for pages: 17 built-in sections (hero, features, pricing, team, testimonials, posts grid, stats, gallery, video, forms…), visual editor, drag-and-drop, duplicate, copy/paste, live desktop/tablet/mobile preview, per-section spacing, background image, anchor, classes, visibility
- Admin-defined **Custom Sections** (fields + HTML + CSS)
- **Forms** with spam protection (honeypot, timing, rate limit, optional Cloudflare Turnstile), uploads, email + auto-reply, stored submissions, CSV export
- **SEO**: meta, Open Graph, JSON-LD (Organization, WebSite, Article, FAQ, Breadcrumbs), sitemap tweaks, redirect manager, Analytics + Search Console tags
- **Design options**: colors, fonts, width, sticky header, additional CSS (Settings > BFD Options)
- **Security** hardening: login lockout, XML-RPC/REST/author enumeration blocked, security headers
- Post types: Services, Projects, Testimonials

```bash
docker compose up -d          # http://localhost:8081
./bin/lint.sh                 # syntax checks
docker compose exec -T wordpress php /var/www/html/bin/smoke-test.php
python3 bin/page-audit.py http://localhost:8081/
./bin/ui-test.sh              # real-browser admin tests (headless Chrome)
python3 bin/make-pot.py       # translation templates
bin/package.sh                # installable zips in dist/
```

Docs: [docs/NEW-SITE.md](docs/NEW-SITE.md) (start a client site, hooks) · [docs/LAUNCH.md](docs/LAUNCH.md) (checklist).

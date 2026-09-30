# BFD site framework

WordPress + a custom lightweight theme (`bfd-theme`) + a functionality plugin (`bfd-core`). No page builder dependency.

- **Section builder** for pages, plus admin-defined **Custom Sections** (fields + HTML + CSS)
- **Forms** with spam protection, uploads, email + stored submissions, CSV export
- **SEO**: meta, Open Graph, JSON-LD (Organization, WebSite, Article, FAQ, Breadcrumbs), sitemap tweaks
- **Security** hardening: login lockout, XML-RPC/REST/author enumeration blocked, security headers
- Post types: Services, Projects, Testimonials

```bash
docker compose up -d          # http://localhost:8081
./bin/lint.sh                 # syntax checks
docker compose exec -T wordpress php /var/www/html/bin/smoke-test.php
python3 bin/page-audit.py http://localhost:8081/
bin/package.sh                # installable zips in dist/
```

Docs: [docs/NEW-SITE.md](docs/NEW-SITE.md) (start a client site, hooks) · [docs/LAUNCH.md](docs/LAUNCH.md) (checklist).

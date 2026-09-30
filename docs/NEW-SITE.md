# Starting a new client site

The framework is two pieces that are versioned and reused for every site:

| Piece | Path | Role |
|---|---|---|
| Theme | `wp-content/themes/bfd-theme` | Presentation only: templates, CSS, section markup |
| Core plugin | `wp-content/plugins/bfd-core` | Everything else: fields, section builder, custom sections, post types, forms, SEO, security |

Client content and settings live in the database, so the same code serves every site.

## 1. Set up
```bash
bin/package.sh            # -> dist/bfd-theme-x.y.z.zip, dist/bfd-core-x.y.z.zip
```
On the new WordPress: install both zips, activate **BFD Core**, then **BFD Theme**.
(For local work, copy this repo and run `docker compose up -d`.)

## 2. Brand it (about an hour)
1. **Design:** Settings > BFD Options > *Design* (colors, fonts, content width, sticky header, additional CSS). No code needed. For deeper changes edit the `:root { --color-primary … }` tokens at the top of `assets/css/main.css`.
2. **Logo & menus:** Appearance > Customize (logo), Appearance > Menus (Primary, Footer).
3. **Settings > BFD Options:** header button, footer text, contact, socials, SEO defaults.
4. **Pages:** create Home / About / Contact with the **Page Sections** builder; set the homepage under Settings > Reading.
5. **Forms:** Forms > Add New, then add a "Form" section to the Contact page.
6. **Site-specific sections:** Custom Sections > Add New (fields + HTML + CSS). No code deploy needed.

## 3. Extending without forking
Use a small site plugin/child theme and these hooks instead of editing the framework:

| Hook | Purpose |
|---|---|
| `bfd_section_definitions` | add/change/remove built-in sections |
| `bfd_sections_post_types` | which post types get the section builder |
| `bfd_post_types`, `bfd_taxonomies` | change/remove the bundled CPTs and taxonomies |
| `bfd_core_modules` | remove or add whole modules |
| `bfd_security_features`, `bfd_security_headers` | switch off / extend hardening (e.g. a CSP header) |
| `bfd_schema_graph`, `bfd_breadcrumb_items` | adjust JSON-LD and breadcrumbs |
| `bfd_form_allowed_mimes`, `bfd_form_max_upload`, `bfd_form_rate_limit` | form upload/spam limits |
| `bfd_client_ip` | real client IP behind a proxy |
| `bfd_csp_directives` | adjust the Content-Security-Policy directives |
| `bfd_activity_retention_days` | how long the activity log is kept |

A new built-in section = one entry in `bfd_section_definitions` + `template-parts/sections/{type}.php` in the theme.

Translations: `python3 bin/make-pot.py` writes `languages/bfd-core.pot` and `bfd-theme.pot`.
Assets: edit `assets/css|js/*.css|js`, then `./bin/build-assets.sh` regenerates the `.min` files (served automatically; add `define( 'SCRIPT_DEBUG', true )` to use the sources).
Quality gates before a release: `./bin/lint.sh`, `./bin/phpcs.sh`, the smoke test, `./bin/ui-test.sh`.

## 4. Before launch
Work through [LAUNCH.md](LAUNCH.md).

## 5. Releasing framework changes
1. Bump `Version:` in `style.css` and `BFD_CORE_VERSION` in `bfd-core.php` (and `REWRITE_VERSION` in `Plugin.php` if post types/rewrites changed).
2. `./bin/lint.sh` and the smoke test must pass.
3. `git tag vX.Y.Z` then `bin/package.sh` and upload the zips to each site.

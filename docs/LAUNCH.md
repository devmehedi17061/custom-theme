# Launch checklist

Automated (run from the repo root, with `docker compose up -d`):

```bash
./bin/lint.sh                                                       # PHP + JS syntax
docker compose exec -T wordpress php /var/www/html/bin/smoke-test.php   # sanitizers, engine, forms, security
python3 bin/page-audit.py http://localhost:8081/ http://localhost:8081/about/   # SEO + accessibility basics
./bin/ui-test.sh                                                   # real-browser admin tests
```

## Before going live

**Server / WordPress**
- [ ] HTTPS on, and Settings > General URLs use `https://` (enables the HSTS header automatically)
- [ ] `wp-config.php`: unique salts, `DISALLOW_FILE_EDIT`, `WP_DEBUG` false, `WP_DEBUG_DISPLAY` false, non-default table prefix
- [ ] PHP 8.1+; `expose_php` off; uploads folder not writable by anything but the web user
- [ ] Real mail delivery (SMTP plugin or host mail). Form emails use `wp_mail`; with no mail server, submissions are still saved
- [ ] Behind a proxy/CDN? Add `add_filter( 'bfd_client_ip', ... )` to read the real client IP, or rate limits and the login lockout will treat everyone as one IP
- [ ] Backups (files + database) scheduled and a restore tested
- [ ] Admin accounts: strong unique passwords; **turn on two-factor** for every admin (Users > Two-factor); delete the default "admin" user; remove unused users
- [ ] Review Tools > Activity Log after launch (logins, lockouts, user/plugin changes)
- [ ] Update WordPress core, remove unused themes/plugins (Hello Dolly, Akismet if unused)

**Content / SEO**
- [ ] Settings > Reading: static homepage set, "Discourage search engines" **off**
- [ ] Settings > Permalinks: "Post name"
- [ ] Settings > BFD Options: organization name, default share image, social links, contact
- [ ] Every page: SEO title (< 60 chars), meta description (120-160), exactly one `<h1>`
- [ ] Images: alt text, sensible sizes; first image above the fold is not lazy-loaded (the hero section handles this)
- [ ] Submit `/wp-sitemap.xml` to Google Search Console and Bing Webmaster Tools (verification codes go in BFD Options > Integrations)
- [ ] Google Analytics ID (if used) is added only after your cookie/consent approach is decided; it is skipped for logged-in editors
- [ ] Test share previews (Facebook debugger / LinkedIn inspector) and schema (Google Rich Results Test)

**Forms**
- [ ] Send a real test submission; confirm the email arrives and the row appears in Forms > Submissions
- [ ] Confirm the recipients list is correct on each form
- [ ] Optional: add Cloudflare Turnstile keys (Settings > BFD Options > Integrations) for stronger spam protection
- [ ] Optional: enable the auto-reply per form
- [ ] If pages are served by a full-page cache: exclude pages with forms, or the nonce/timestamp goes stale (the form then shows "Security check failed")

**Performance**
- [ ] Lighthouse mobile >= 90 on home + a content page
- [ ] Run `./bin/build-assets.sh` after CSS/JS edits (the `.min` files are what visitors get)
- [ ] Page cache + object cache (host or plugin), gzip/brotli
- [ ] Optional: Settings > BFD Options > Design > *Inline the theme stylesheet* for small sites
- [ ] WebP: JPEG/PNG sizes are generated as WebP automatically; upload originals at <= 2000 px wide

**Security spot-checks** (should all already pass)
- [ ] `/xmlrpc.php` returns 403
- [ ] `/wp-json/wp/v2/users` and `/?author=1` return 404 when logged out
- [ ] 6 wrong logins from one IP lock that IP for 15 minutes
- [ ] Response headers include `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`
- [ ] Optional: Settings > BFD Options > Advanced > Content-Security-Policy: start with *Report only*, browse the site with the browser console open, then switch to *Enforce*

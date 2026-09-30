#!/usr/bin/env python3
"""Generate translation templates (.pot) for the theme and plugin without WP-CLI.
Extracts string literals from __(), _e(), _x(), _n(), esc_html__(), esc_html_e(), esc_attr__(), esc_attr_e().
Usage: python3 bin/make-pot.py
"""
import re, os, datetime

TARGETS = {
    'bfd-core':  ('wp-content/plugins/bfd-core', 'bfd-core'),
    'bfd-theme': ('wp-content/themes/bfd-theme', 'bfd-theme'),
}
STR = r"""(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")"""
SIMPLE = re.compile(r"\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*" + STR + r"\s*,\s*'(%s)'\s*\)")
PLURAL = re.compile(r"\b_n\(\s*" + STR + r"\s*,\s*" + STR + r"\s*,[^,]+,\s*'(%s)'\s*\)")

def unescape(m):
    s = m[0] if m[0] is not None and m[0] != '' else m[1]
    return s.replace("\\'", "'").replace('\\"', '"').replace('\\\\', '\\')

def esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')

for name, (path, domain) in TARGETS.items():
    entries = {}
    for root, _, files in os.walk(path):
        for f in sorted(files):
            if not f.endswith('.php'):
                continue
            full = os.path.join(root, f)
            text = open(full, encoding='utf8').read()
            for rx in (re.compile(SIMPLE.pattern % re.escape(domain)), re.compile(PLURAL.pattern % re.escape(domain))):
                for m in rx.finditer(text):
                    line = text.count('\n', 0, m.start()) + 1
                    msgid = unescape(m.groups()[0:2])
                    plural = unescape(m.groups()[2:4]) if rx.pattern.startswith(r"\b_n") else None
                    key = (msgid, plural)
                    entries.setdefault(key, []).append(f"{os.path.relpath(full, path)}:{line}")
    out_dir = os.path.join(path, 'languages')
    os.makedirs(out_dir, exist_ok=True)
    out = os.path.join(out_dir, f'{name}.pot')
    with open(out, 'w', encoding='utf8') as fh:
        fh.write(f'''# Translation template for {name}.
msgid ""
msgstr ""
"Project-Id-Version: {name}\\n"
"POT-Creation-Date: {datetime.datetime.now(datetime.UTC).strftime('%Y-%m-%d %H:%M+0000')}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"X-Domain: {domain}\\n"

''')
        for (msgid, plural), refs in sorted(entries.items(), key=lambda kv: kv[0][0].lower()):
            fh.write('#: ' + ' '.join(refs[:3]) + '\n')
            fh.write(f'msgid "{esc(msgid)}"\n')
            if plural is not None:
                fh.write(f'msgid_plural "{esc(plural)}"\nmsgstr[0] ""\nmsgstr[1] ""\n\n')
            else:
                fh.write('msgstr ""\n\n')
    print(f'{out}: {len(entries)} strings')

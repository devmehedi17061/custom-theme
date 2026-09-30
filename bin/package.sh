#!/usr/bin/env bash
# Build installable zips of the theme and plugin into dist/. Usage: bin/package.sh [version]
set -euo pipefail
cd "$(dirname "$0")/.."
version="${1:-$(grep -m1 '^Version:' wp-content/themes/bfd-theme/style.css | awk '{print $2}')}"
mkdir -p dist
python3 - "$version" <<'PY'
import sys, os, zipfile
version = sys.argv[1]
targets = {
    'bfd-theme': 'wp-content/themes/bfd-theme',
    'bfd-core':  'wp-content/plugins/bfd-core',
}
skip_names = {'.DS_Store', 'Thumbs.db'}
for name, src in targets.items():
    out = f'dist/{name}-{version}.zip'
    with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
        for root, dirs, files in os.walk(src):
            dirs[:] = [d for d in dirs if d not in ('node_modules', '.git', 'vendor')]
            for f in files:
                if f in skip_names or f.endswith(('.log', '.map')):
                    continue
                path = os.path.join(root, f)
                z.write(path, os.path.join(name, os.path.relpath(path, src)))
    print(f'built {out} ({os.path.getsize(out)//1024} KB)')
PY

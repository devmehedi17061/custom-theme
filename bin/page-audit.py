#!/usr/bin/env python3
"""Quick SEO/accessibility audit of a rendered page (stdlib only).
Usage: python3 bin/page-audit.py http://localhost:8081/ [more URLs...]
"""
import sys, re, urllib.request, urllib.error
from html.parser import HTMLParser

class P(HTMLParser):
    def __init__(s):
        super().__init__(); s.h={}; s.imgs=[]; s.labels=set(); s.inputs=[]; s.metas={}; s.links={}; s.title=''; s.in_title=False
        s.lang=None; s.jsonld=0; s.skip=False; s.landmarks=set(); s.h_order=[]
    def handle_starttag(s,t,a):
        a=dict(a)
        if t=='html': s.lang=a.get('lang')
        if t=='title': s.in_title=True
        if t=='meta': s.metas[a.get('name') or a.get('property') or a.get('charset','charset')]=a.get('content','')
        if t=='link' and a.get('rel'): s.links[a['rel']]=a.get('href')
        if t=='script' and a.get('type')=='application/ld+json': s.jsonld+=1
        if t in ('h1','h2','h3','h4','h5','h6'): s.h[t]=s.h.get(t,0)+1; s.h_order.append(int(t[1]))
        if t=='img': s.imgs.append(a)
        if t=='label' and a.get('for'): s.labels.add(a['for'])
        if t in('input','select','textarea') and a.get('type') not in('hidden','submit') and 'bfd-form__hp' not in a.get('class',''): s.inputs.append(a)
        if t=='a' and 'skip-link' in a.get('class',''): s.skip=True
        if t in('main','nav','header','footer'): s.landmarks.add(t)
    def handle_endtag(s,t):
        if t=='title': s.in_title=False
    def handle_data(s,d):
        if s.in_title: s.title+=d

def audit(url):
    status=200
    try:
        html=urllib.request.urlopen(url).read().decode('utf8','replace')
    except urllib.error.HTTPError as e:   # 404 pages are still worth auditing
        status=e.code; html=e.read().decode('utf8','replace')
    noindex=bool(re.search(r"name=.robots.[^>]*noindex",html))
    p=P(); p.feed(html); bad=[]; warn=[]
    if not p.lang: bad.append('<html> has no lang')
    if not p.title.strip(): bad.append('no <title>')
    elif len(p.title)>70: warn.append(f'title long ({len(p.title)} chars)')
    d=p.metas.get('description','')
    if not d: (warn if (noindex or status>=400) else bad).append('no meta description')
    elif not 50<=len(d)<=170: warn.append(f'meta description length {len(d)}')
    if 'viewport' not in p.metas: bad.append('no viewport meta')
    if 'canonical' not in p.links: bad.append('no canonical link')
    if not p.metas.get('og:title'): warn.append('no Open Graph tags')
    if not p.jsonld: warn.append('no JSON-LD')
    if p.h.get('h1',0)!=1: bad.append(f"expected exactly 1 <h1>, found {p.h.get('h1',0)}")
    for prev,cur in zip(p.h_order,p.h_order[1:]):
        if cur>prev+1: warn.append(f'heading level jumps h{prev} -> h{cur}'); break
    missing=[i.get('src','?')[-40:] for i in p.imgs if 'alt' not in i]
    if missing: bad.append(f'{len(missing)} image(s) without alt attribute: {missing[:2]}')
    unl=[i.get('name') for i in p.inputs if i.get('id') not in p.labels and not i.get('aria-label')]
    if unl: bad.append(f'form controls without label: {unl}')
    if not p.skip: warn.append('no skip link')
    for lm in ('main','nav','header','footer'):
        if lm not in p.landmarks: warn.append(f'no <{lm}> landmark')
    lazy_first=[i for i in p.imgs[:1] if i.get('loading')=='lazy']
    if lazy_first: warn.append('first image is lazy-loaded (hurts LCP)')
    print(f"\n{url}  ({len(html)//1024} KB HTML, {len(p.imgs)} images)")
    for b in bad: print('  FAIL', b)
    for w in warn: print('  warn', w)
    if not bad and not warn: print('  all checks passed')
    return not bad

if __name__=='__main__':
    ok=all([audit(u) for u in (sys.argv[1:] or ['http://localhost:8081/'])])
    sys.exit(0 if ok else 1)

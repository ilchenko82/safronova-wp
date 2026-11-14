#!/usr/bin/env python3
"""Конвертер контенту Vigbo -> блоки Gutenberg (WordPress).

Вхід:  data/site.json (знімок сайту safronova.com.ua)
Вихід: build/wp-content/plugins/safronova-migration/data/content.json
       build/wp-content/themes/safronova/parts/footer.html
       build/media-list.txt (список усіх фото для резервного скрипта)
"""
import json, re, os, sys
from bs4 import BeautifulSoup, NavigableString, Tag

ROOT = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(ROOT, 'data', 'site.json')
OUT_PLUGIN = os.path.join(ROOT, 'build', 'wp-content', 'plugins', 'safronova-migration', 'data')
OUT_THEME = os.path.join(ROOT, 'build', 'wp-content', 'themes', 'safronova')
CDN = 'https://static-cdn4-2.vigbo.tech/u44468/57218/'

MEDIA = []          # унікальні URL фото в порядку появи


def url(u):
    if u.startswith('~/'):
        u = CDN + u[2:]
    elif u.startswith('//'):
        u = 'https:' + u
    if u not in MEDIA:
        MEDIA.append(u)
    return u


def jattr(d):
    return json.dumps(d, ensure_ascii=False, separators=(',', ':')) if d else ''


def block(name, attrs, inner=None):
    a = jattr(attrs)
    a = (' ' + a) if a else ''
    if inner is None:
        return f'<!-- wp:{name}{a} /-->'
    return f'<!-- wp:{name}{a} -->\n{inner}\n<!-- /wp:{name} -->'


# ---------- текст ----------
FONT_MAP = [('playfair', "'Playfair Display', serif"), ('fira', "'Fira Sans', sans-serif"),
            ('open sans', "'Open Sans', sans-serif")]


def fix_style(style):
    out = []
    for part in style.split(';'):
        if ':' not in part:
            continue
        k, v = part.split(':', 1)
        k = k.strip().lower(); v = v.strip().strip('"\'').strip()
        if k == 'text-align':
            continue                      # вирівнювання переноситься в атрибут блока
        if k == 'letter-spacing' and v in ('0em', '0'):
            continue
        if k == 'font-family':
            low = v.lower()
            for key, fam in FONT_MAP:
                if key in low:
                    v = fam; break
        out.append(f'{k}:{v}')
    return ';'.join(out)


def clean_inline(tag):
    for t in tag.find_all(True):
        if t.has_attr('style'):
            s = fix_style(t['style'])
            if s:
                t['style'] = s
            else:
                del t['style']
        if t.name == 'font':            # залишки <font> без атрибутів
            t.unwrap()
    for t in tag.find_all('span'):
        if not t.attrs:
            t.unwrap()


def inner_html(tag):
    h = tag.decode_contents(formatter='minimal')
    return h.replace('\xa0', '&nbsp;').strip()


def align_of(tag):
    m = re.search(r'text-align\s*:\s*(\w+)', tag.get('style', '') or '')
    return m.group(1) if m and m.group(1) in ('center', 'right') else None


def para(tag):
    al = align_of(tag)
    clean_inline(tag)
    h = inner_html(tag)
    if not h:
        h = '&nbsp;'
    attrs = {'align': al} if al else {}
    cls = f' class="has-text-align-{al}"' if al else ''
    return block('paragraph', attrs, f'<p{cls}>{h}</p>')


def text_widget(lh, html):
    soup = BeautifulSoup(html, 'html.parser')
    kids = []
    for node in soup.contents:
        if isinstance(node, NavigableString):
            if node.strip():
                kids.append(block('paragraph', {}, f'<p>{node.strip()}</p>'))
            continue
        if node.name == 'p':
            kids.append(para(node))
        elif node.name in ('ul', 'ol'):
            items = []
            for li in node.find_all('li', recursive=False):
                clean_inline(li)
                items.append(block('list-item', {}, f'<li>{inner_html(li)}</li>'))
            tagn = node.name
            attrs = {'ordered': True} if tagn == 'ol' else {}
            kids.append(block('list', attrs, f'<{tagn} class="wp-block-list">' + '\n'.join(items) + f'</{tagn}>'))
        else:
            clean_inline(node)
            kids.append(block('html', {}, str(node)))
    attrs = {'className': 'sf-text', 'layout': {'type': 'default'}}
    style = ''
    if lh:
        attrs = {'className': 'sf-text', 'style': {'typography': {'lineHeight': lh}}, 'layout': {'type': 'default'}}
        style = f' style="line-height:{lh}"'
    return block('group', attrs, f'<div class="wp-block-group sf-text"{style}>' + '\n'.join(kids) + '</div>')


# ---------- інші віджети ----------
def spacer(h):
    return block('spacer', {'height': f'{h}px'},
                 f'<div style="height:{h}px" aria-hidden="true" class="wp-block-spacer"></div>')


def image(src, width, alt, circle, href, dims, align, extra_class=''):
    src = url(src)
    attrs = {}
    classes = ['wp-block-image']
    if width:
        attrs['width'] = width
    attrs['sizeSlug'] = 'full'
    attrs['linkDestination'] = 'custom' if href else 'none'
    if align == 'center' and width:
        attrs['align'] = 'center'; classes.append('aligncenter')
    classes.append('size-full')
    if width:
        classes.append('is-resized')
    cn = []
    if circle:
        cn.append('is-style-rounded')
    if extra_class:
        cn.append(extra_class)
    if cn:
        attrs['className'] = ' '.join(cn); classes += cn
    st = f' style="width:{width}"' if width else ''
    alt = alt.replace('"', '&quot;')
    img = f'<img src="{src}" alt="{alt}"{st}/>'
    if href:
        img = f'<a href="{href}">{img}</a>'
    return block('image', attrs, f'<figure class="{" ".join(classes)}">{img}</figure>')


def gallery(items, cls='sf-gallery'):
    imgs = []
    for src, alt, w, h in items:
        u = url(src)
        alt = (alt or '').replace('"', '&quot;')
        imgs.append(block('image', {'sizeSlug': 'full', 'linkDestination': 'none'},
                          f'<figure class="wp-block-image size-full"><img src="{u}" alt="{alt}"/></figure>'))
    attrs = {'columns': 1, 'imageCrop': False, 'linkTo': 'none', 'sizeSlug': 'full', 'className': cls}
    return block('gallery', attrs,
                 f'<figure class="wp-block-gallery has-nested-images columns-1 {cls}">' + '\n'.join(imgs) + '</figure>')


def social(urls, justify='center'):
    links = []
    for u in urls:
        svc = 'instagram' if 'instagram' in u else 'facebook' if 'facebook' in u else 'chain'
        links.append(block('social-link', {'url': u, 'service': svc}))
    attrs = {'openInNewTab': True, 'className': 'sf-social',
             'layout': {'type': 'flex', 'justifyContent': justify}}
    return block('social-links', attrs, '<ul class="wp-block-social-links sf-social">' + '\n'.join(links) + '</ul>')


def line(width):
    w = re.sub(r'[^0-9]', '', width) or '100'
    cls = f'sf-line sf-w-{w}'
    return block('separator', {'className': cls}, f'<hr class="wp-block-separator has-alpha-channel-opacity {cls}"/>')


def form():
    return block('safronova/contact-form', {})


def widget(w, ctx):
    t = w[0]
    if t == 'sp':  return spacer(w[1])
    if t == 'tx':  return text_widget(w[1], w[2])
    if t == 'im':  return image(w[1], w[2], w[3], w[4], w[5], w[6], w[7])
    if t == 'ga':  return gallery(w[2])
    if t == 'so':  return social(w[1], ctx.get('social', 'center'))
    if t == 'ln':  return line(w[1])
    if t == 'fm':  return form()
    raise ValueError(t)


def sections(secs, ctx=None):
    ctx = ctx or {}
    out = []
    for rows in secs:
        for row in rows:
            if len(row) == 1 and row[0][0] == 24:
                out += [widget(w, ctx) for w in row[0][1]]
                continue
            cols = []
            for width, widgets in row:
                pct = round(width / 24 * 100, 4)
                pct_s = (f'{pct:.4f}'.rstrip('0').rstrip('.')) + '%'
                only_sp = all(w[0] == 'sp' for w in widgets)
                attrs = {'width': pct_s}
                cls = 'wp-block-column'
                if only_sp:
                    attrs['className'] = 'sf-col-spacer'; cls += ' sf-col-spacer'
                    inner = ''
                else:
                    c2 = dict(ctx); c2['social'] = 'left'
                    inner = '\n'.join(widget(w, c2) for w in widgets)
                cols.append(block('column', attrs, f'<div class="{cls}" style="flex-basis:{pct_s}">{inner}</div>'))
            out.append(block('columns', {'className': 'sf-row'},
                             '<div class="wp-block-columns sf-row">' + '\n'.join(cols) + '</div>'))
    return '\n\n'.join(out)


# ---------- сторінки ----------
PAGES = {
    '/':                      ('home', 'Головна', 0),
    '/pregnancy':             ('pregnancy', 'Фотосесія вагітних', 1),
    '/mothersandkids':        ('mothersandkids', 'Фотосесія з малюком', 2),
    '/podvodnye-fotosessii':  ('podvodnye-fotosessii', 'Підводна фотосесія', 3),
    '/blog':                  ('blog', 'Блог', 4),
    '/tseny-new':             ('tseny-new', 'Ціни', 5),
    '/faq':                   ('faq', 'Питання/Відповідь', 6),
    '/reviews':               ('reviews', 'Відгуки', 7),
    '/contacts':              ('contacts', 'Контакти', 8),
}


def main():
    site = json.load(open(SRC, encoding='utf-8'))
    pages = {p['u']: p for p in site['pages']}
    out = {'pages': [], 'posts': [], 'blog_seo': {}, 'logo': url('//static-cdn4-2.vigbo.tech/u44468/57218/logo/u-03e3a38a7b9f56360ef1bfc1631507c1@2x.jpg')}

    for path, (slug, title, order) in PAGES.items():
        p = pages[path]
        if path == '/':
            items = []
            for src, pos in p['slides']:
                items.append([src, '', 1500, 1000])
            # слайдер = галерея з класом sf-hero; фокус кадру зберігаємо в класі
            imgs = []
            for src, pos in p['slides']:
                u = url(src)
                cls = ''
                m = re.findall(r'([\d.]+)%', pos or '')
                if len(m) == 2:
                    cls = f'sf-pos-{round(float(m[0]))}-{round(float(m[1]))}'
                attrs = {'sizeSlug': 'full', 'linkDestination': 'none'}
                fig = 'wp-block-image size-full'
                if cls:
                    attrs['className'] = cls; fig += ' ' + cls
                imgs.append(block('image', attrs, f'<figure class="{fig}"><img src="{u}" alt=""/></figure>'))
            content = block('gallery', {'columns': 1, 'imageCrop': False, 'linkTo': 'none', 'sizeSlug': 'full', 'className': 'sf-hero'},
                            '<figure class="wp-block-gallery has-nested-images columns-1 sf-hero">' + '\n'.join(imgs) + '</figure>')
        elif path == '/blog':
            content = ''
            out['blog_seo'] = {'title': p['t'], 'desc': p['d']}
        else:
            content = sections(p['s'])
        out['pages'].append({'slug': slug, 'title': title, 'order': order, 'content': content,
                             'seo_title': p['t'], 'seo_desc': p['d']})

    # порядок записів = порядок у стрічці блогу (зверху новіші)
    feed = pages['/blog']['items'] + pages['/blog/page/2']['items']
    for i, (href, prev, ttl) in enumerate(feed):
        p = pages[href]
        out['posts'].append({'slug': href.split('/')[-1], 'title': p['h1'] or ttl, 'content': sections(p['s']),
                             'featured': url(prev), 'seo_title': p['t'], 'seo_desc': p['d'],
                             'date_offset_days': i})
    out['media'] = MEDIA

    os.makedirs(OUT_PLUGIN, exist_ok=True)
    json.dump(out, open(os.path.join(OUT_PLUGIN, 'content.json'), 'w', encoding='utf-8'), ensure_ascii=False, indent=1)

    # підвал теми
    os.makedirs(os.path.join(OUT_THEME, 'parts'), exist_ok=True)
    footer = sections(site['footer'])
    with open(os.path.join(OUT_THEME, 'parts', 'footer.html'), 'w', encoding='utf-8') as f:
        f.write(block('group', {'tagName': 'div', 'className': 'sf-footer', 'layout': {'type': 'constrained'}},
                      '<div class="wp-block-group sf-footer">' + footer + '</div>') + '\n')

    with open(os.path.join(ROOT, 'build', 'media-list.txt'), 'w', encoding='utf-8') as f:
        f.write('\n'.join(MEDIA) + '\n')
    print('pages', len(out['pages']), 'posts', len(out['posts']), 'media', len(MEDIA))


if __name__ == '__main__':
    main()

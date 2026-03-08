/**
 * @param {{
 *   title?: string,
 *   description?: string,
 *   image?: string,
 *   url?: string,
 *   type?: string,
 *   publishedAt?: string,
 *   author?: string,
 *   tags?: string[],
 * }} options
 */
export function setSeoMeta({
    title,
    description,
    image = undefined,
    url = undefined,
    type = 'article',
    publishedAt = undefined,
    author = undefined,
    tags = undefined,
}) {
    if (typeof document === 'undefined') {
        return;
    }

    const siteName = 'Новостной портал';
    const fullTitle = title ? `${title} | ${siteName}` : siteName;

    document.title = fullTitle;

    function setMeta(name, content, attr = 'name') {
        let element = document.querySelector(`meta[${attr}="${name}"]`);

        if (!element) {
            element = document.createElement('meta');
            element.setAttribute(attr, name);
            document.head.appendChild(element);
        }

        element.setAttribute('content', content || '');
    }

    setMeta('description', description);
    setMeta('author', author);
    setMeta('keywords', Array.isArray(tags) ? tags.join(', ') : '');

    setMeta('og:title', fullTitle, 'property');
    setMeta('og:description', description, 'property');
    setMeta('og:image', image, 'property');
    setMeta('og:url', url || window.location.href, 'property');
    setMeta('og:type', type, 'property');
    setMeta('og:site_name', siteName, 'property');
    setMeta('og:locale', 'ru_RU', 'property');

    setMeta('twitter:card', image ? 'summary_large_image' : 'summary');
    setMeta('twitter:title', fullTitle);
    setMeta('twitter:description', description);
    setMeta('twitter:image', image);

    if (publishedAt) {
        setMeta('article:published_time', publishedAt, 'property');
    }

    let canonical = document.querySelector('link[rel="canonical"]');

    if (!canonical) {
        canonical = document.createElement('link');
        canonical.setAttribute('rel', 'canonical');
        document.head.appendChild(canonical);
    }

    canonical.setAttribute('href', url || window.location.href);
}

/**
 * @param {Record<string, unknown>} data
 */
export function injectJsonLd(data) {
    if (typeof document === 'undefined') {
        return;
    }

    const existing = document.getElementById('json-ld');

    if (existing) {
        existing.remove();
    }

    const script = document.createElement('script');
    script.id = 'json-ld';
    script.type = 'application/ld+json';
    script.text = JSON.stringify(data);
    document.head.appendChild(script);
}

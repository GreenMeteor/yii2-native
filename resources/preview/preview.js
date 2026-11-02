/**
 * Browser preview renderer for Yii2 Native frames (development tool).
 *
 * Draws the element tree as plain DOM so you can see screens and try actions
 * without a device. It approximates, and does not replicate, native rendering.
 * Only textContent and validated attributes are used; frame data is never
 * interpreted as HTML.
 */

const SAFE_IMAGE = /^(https?:\/\/|\/(?!\/)|data:image\/(png|jpe?g|gif|webp);base64,)/i;

const px = (v) => (typeof v === 'number' ? `${v}px` : v === 'fill' ? '100%' : null);

function box(v) {
    if (typeof v === 'number') return `${v}px`;
    if (Array.isArray(v) && v.every((n) => typeof n === 'number')) {
        const [a, b, c, d] = v;
        if (v.length === 2) return `${a}px ${b}px`;
        if (v.length === 4) return `${a}px ${b}px ${c}px ${d}px`;
    }
    return null;
}

function applyCommon(el, p) {
    const set = (k, v) => v !== null && v !== undefined && el.style.setProperty(k, String(v));
    set('padding', box(p.padding));
    set('margin', box(p.margin));
    if (typeof p.background === 'string') set('background', p.background);
    set('width', px(p.width));
    set('height', px(p.height));
    if (p.visible === false) el.hidden = true;
    if (typeof p.accessibilityLabel === 'string') el.setAttribute('aria-label', p.accessibilityLabel);
    if (typeof p.testId === 'string') el.dataset.testid = p.testId;
    if (typeof p.id === 'string') el.dataset.nativeId = p.id;
}

const ALIGN = { start: 'flex-start', center: 'center', end: 'flex-end', stretch: 'stretch' };
const JUSTIFY = { start: 'flex-start', center: 'center', end: 'flex-end', 'space-between': 'space-between', 'space-around': 'space-around' };

function stack(doc, dir, p) {
    const el = doc.createElement('div');
    el.style.display = 'flex';
    el.style.flexDirection = dir;
    if (typeof p.spacing === 'number') el.style.gap = `${p.spacing}px`;
    if (ALIGN[p.align]) el.style.alignItems = ALIGN[p.align];
    if (JUSTIFY[p.justify]) el.style.justifyContent = JUSTIFY[p.justify];
    return el;
}

function button(doc, p, ctx, cls) {
    const el = doc.createElement('button');
    el.type = 'button';
    el.className = cls;
    el.textContent = (p.icon ? `[${p.icon}] ` : '') + (p.text ?? p.label ?? '');
    if (p.disabled) el.disabled = true;
    if (p.action) el.addEventListener('click', () => ctx.onAction({ action: p.action, id: p.id, payload: p.payload }));
    return el;
}

const RENDERERS = {
    text(doc, p) {
        const el = doc.createElement('span');
        el.textContent = p.text ?? '';
        if (typeof p.size === 'number') el.style.fontSize = `${p.size}px`;
        if (p.weight) el.style.fontWeight = { normal: 400, medium: 500, semibold: 600, bold: 700 }[p.weight] ?? 400;
        if (typeof p.color === 'string') el.style.color = p.color;
        if (p.align) el.style.textAlign = p.align === 'start' ? 'left' : p.align === 'end' ? 'right' : p.align;
        if (typeof p.maxLines === 'number') {
            el.style.display = '-webkit-box';
            el.style.webkitLineClamp = String(p.maxLines);
            el.style.webkitBoxOrient = 'vertical';
            el.style.overflow = 'hidden';
        }
        return el;
    },
    button: (doc, p, ctx) => button(doc, p, ctx, `nv-button nv-${p.variant || 'filled'}`),
    image(doc, p) {
        const el = doc.createElement('img');
        const src = String(p.source ?? '');
        if (SAFE_IMAGE.test(src)) el.src = src;
        else el.alt = '[blocked image source]';
        if (p.alt) el.alt = p.alt;
        if (p.contentMode) el.style.objectFit = { fit: 'contain', fill: 'fill', cover: 'cover' }[p.contentMode] ?? 'contain';
        if (typeof p.cornerRadius === 'number') el.style.borderRadius = `${p.cornerRadius}px`;
        return el;
    },
    vstack: (doc, p) => stack(doc, 'column', p),
    hstack: (doc, p) => stack(doc, 'row', p),
    zstack(doc) {
        const el = doc.createElement('div');
        el.style.display = 'grid';
        el.className = 'nv-zstack';
        return el;
    },
    scroll(doc, p) {
        const el = doc.createElement('div');
        el.style.overflow = 'auto';
        el.style.display = 'flex';
        el.style.flexDirection = p.horizontal ? 'row' : 'column';
        return el;
    },
    spacer(doc, p) {
        const el = doc.createElement('div');
        if (typeof p.size === 'number') {
            el.style.flex = `0 0 ${p.size}px`;
        } else {
            el.style.flex = '1 1 auto';
        }
        return el;
    },
    divider(doc, p) {
        const el = doc.createElement('hr');
        el.style.borderTopWidth = `${typeof p.thickness === 'number' ? p.thickness : 1}px`;
        if (typeof p.color === 'string') el.style.borderColor = p.color;
        return el;
    },
    icon(doc, p) {
        const el = doc.createElement('span');
        el.className = 'nv-icon';
        el.textContent = `[${p.name ?? '?'}]`;
        if (typeof p.size === 'number') el.style.fontSize = `${p.size}px`;
        if (typeof p.color === 'string') el.style.color = p.color;
        return el;
    },
    input(doc, p, ctx) {
        const el = doc.createElement(p.multiline ? 'textarea' : 'input');
        if (!p.multiline) {
            el.type = p.secure ? 'password' : { email: 'email', number: 'number', phone: 'tel', url: 'url' }[p.keyboard] || 'text';
        }
        el.name = p.name ?? '';
        el.value = p.value ?? '';
        if (p.placeholder) el.placeholder = p.placeholder;
        if (p.onChange) el.addEventListener('input', () => ctx.onAction({ action: p.onChange, id: p.id, value: el.value }));
        if (p.action) {
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !p.multiline) ctx.onAction({ action: p.action, id: p.id, value: el.value });
            });
        }
        return el;
    },
    toggle(doc, p, ctx) {
        const label = doc.createElement('label');
        const input = doc.createElement('input');
        input.type = 'checkbox';
        input.name = p.name ?? '';
        input.checked = !!p.value;
        if (p.action) input.addEventListener('change', () => ctx.onAction({ action: p.action, id: p.id, value: input.checked }));
        label.append(input, doc.createTextNode(' ' + (p.label ?? '')));
        return label;
    },
    card(doc, p) {
        const el = doc.createElement('div');
        el.className = 'nv-card';
        if (typeof p.cornerRadius === 'number') el.style.borderRadius = `${p.cornerRadius}px`;
        if (typeof p.elevation === 'number') el.style.boxShadow = `0 ${p.elevation}px ${p.elevation * 3}px rgba(0,0,0,.2)`;
        return el;
    },
    list(doc, p) {
        const el = doc.createElement('div');
        el.style.display = 'flex';
        el.style.flexDirection = 'column';
        el.style.overflow = 'auto';
        if (typeof p.itemSpacing === 'number') el.style.gap = `${p.itemSpacing}px`;
        return el;
    },
    topbar(doc, p, ctx, node, render) {
        const el = doc.createElement('header');
        el.className = 'nv-topbar';
        const title = doc.createElement('div');
        title.className = 'nv-title';
        title.textContent = (p.showBack ? '< ' : '') + (p.title ?? '');
        if (p.subtitle) {
            const sub = doc.createElement('small');
            sub.textContent = p.subtitle;
            title.append(doc.createElement('br'), sub);
        }
        el.append(title);
        for (const a of Array.isArray(p.actions) ? p.actions : []) {
            el.append(button(doc, { text: a.label ?? a.icon ?? a.action, icon: a.label ? a.icon : null, action: a.action, id: p.id }, ctx, 'nv-button nv-text'));
        }
        return el;
    },
    bottomnav(doc) {
        const el = doc.createElement('nav');
        el.className = 'nv-bottomnav';
        return el;
    },
    navitem(doc, p, ctx) {
        const el = button(doc, { text: `${p.label ?? ''}${p.badge ? ` (${p.badge})` : ''}`, icon: p.icon, action: p.action, id: p.id }, ctx, 'nv-button nv-text');
        if (p.active) el.classList.add('nv-active');
        return el;
    },
    fab: (doc, p, ctx) => button(doc, { text: p.label ?? '', icon: p.icon, action: p.action, id: p.id }, ctx, 'nv-fab'),
    loading(doc, p) {
        const el = doc.createElement('progress');
        el.title = p.message ?? '';
        return el;
    },
};

/** Render one node (and its subtree) to a DOM element. */
export function renderNode(node, doc, ctx, depth = 0) {
    if (depth > 64 || !node || typeof node.type !== 'string') {
        const bad = doc.createElement('div');
        bad.className = 'nv-unknown';
        bad.textContent = '[invalid node]';
        return bad;
    }
    const props = node.properties && typeof node.properties === 'object' ? node.properties : {};
    const make = RENDERERS[node.type];
    const el = make ? make(doc, props, ctx) : doc.createElement('div');
    if (!make) {
        el.className = 'nv-unknown';
        el.textContent = `[unsupported: ${node.type}]`;
    }
    applyCommon(el, props);
    el.dataset.nodeType = node.type;
    for (const child of Array.isArray(node.children) ? node.children : []) {
        el.append(renderNode(child, doc, ctx, depth + 1));
    }
    return el;
}

/** Replace `container`'s content with the frame. */
export function renderFrame(frame, container, { onAction = () => {} } = {}) {
    const doc = container.ownerDocument;
    container.replaceChildren();
    if (!frame || frame.version !== 1 || !frame.root) {
        const msg = doc.createElement('div');
        msg.className = 'nv-unknown';
        msg.textContent = '[not a version 1 frame]';
        container.append(msg);
        return;
    }
    container.append(renderNode(frame.root, doc, { onAction }));
}

function sameOrigin(url) {
    const u = new URL(url, location.href);
    if (u.origin !== location.origin) throw new Error('Preview only loads screens from the same origin.');
    return u.href;
}

/**
 * Wire up the preview page: load a screen, render it, send actions to PHP,
 * and show the bridge calls PHP made while handling them.
 */
export function mountPreview({ screen, events, csrf, secret, phone, callsLog, status }) {
    const doc = phone.ownerDocument;
    const say = (m) => { if (status) status.textContent = m; };

    const render = (frame) => renderFrame(frame, phone, { onAction: send });

    async function load(url) {
        try {
            const res = await fetch(sameOrigin(url), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            render(await res.json());
            say('Loaded ' + url);
        } catch (e) {
            say('Could not load screen: ' + e.message);
        }
    }

    async function send({ action, id, value, payload }) {
        const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
        if (csrf) headers['X-CSRF-Token'] = csrf;
        if (secret) headers['X-Native-Secret'] = secret;
        try {
            const res = await fetch(events, {
                method: 'POST', headers, credentials: 'same-origin',
                body: JSON.stringify({ event: 'nativeAction', payload: { action, id, value, payload } }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) throw new Error((data.error && data.error.message) || `HTTP ${res.status}`);
            if (data.result) render(data.result);
            if (callsLog) {
                for (const c of data.calls || []) {
                    const li = doc.createElement('li');
                    li.textContent = c.arguments === undefined ? c.function : `${c.function} ${JSON.stringify(c.arguments)}`;
                    callsLog.prepend(li);
                }
            }
            say(`Action "${action}" handled` + (data.result ? ' (new screen)' : ''));
        } catch (e) {
            say('Action failed: ' + e.message);
        }
    }

    return { load, send, render };
}

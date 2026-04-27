<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Noto Sans Thai', system-ui, sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
<div id="toast-container" class="fixed top-4 right-4 z-[110] space-y-2 pointer-events-none"></div>

<dialog id="cmd-palette" class="rounded-xl p-0 w-[560px] max-w-[calc(100vw-1rem)] backdrop:bg-black/40">
    <div class="p-3">
        <div class="flex items-center gap-2 mb-2">
            <input id="cmd-input" placeholder="พิมพ์เพื่อค้นหา (หน้า)..." class="flex-1 border rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-500" autocomplete="off">
            <button id="cmd-close" type="button" class="w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-600 flex items-center justify-center text-lg shrink-0" aria-label="ปิด" title="ปิด">
                ✕
            </button>
        </div>
        <div id="cmd-results" class="max-h-[55vh] overflow-y-auto"></div>
        <div class="text-xs text-slate-400 mt-2 hidden md:flex gap-3">
            <span><kbd class="bg-slate-100 px-1 rounded">↑↓</kbd> เลื่อน</span>
            <span><kbd class="bg-slate-100 px-1 rounded">↵</kbd> เลือก</span>
            <span><kbd class="bg-slate-100 px-1 rounded">esc</kbd> ปิด</span>
        </div>
    </div>
</dialog>

{{-- Unified compact top bar (visible on all auth pages) --}}
<header id="topbar" class="sticky top-0 z-40 bg-white border-b border-slate-200 shadow-sm hidden">
    <div class="max-w-7xl mx-auto px-3 sm:px-4 h-14 flex items-center gap-2">
        {{-- Left: Logo + back --}}
        <a href="/dashboard" class="flex items-center gap-2 font-bold text-slate-800 hover:text-cyan-700 shrink-0">
            <span class="text-xl">💊</span>
            <span class="hidden sm:inline">{{ config('app.name') }}</span>
        </a>

        {{-- Spacer --}}
        <div class="flex-1"></div>

        {{-- Right: Cmd-K hint + Bell + Branch + User --}}
        <button id="cmd-k-btn" class="hidden md:flex items-center gap-1.5 px-2 py-1 text-xs text-slate-500 bg-slate-100 hover:bg-slate-200 rounded">
            <span>🔍</span>
            <span>ค้นหา</span>
            <kbd class="bg-white border border-slate-300 px-1 rounded text-[10px]">⌘K</kbd>
        </button>

        <a href="/notifications" id="topbar-bell" class="relative w-9 h-9 rounded-full hover:bg-slate-100 flex items-center justify-center" title="การแจ้งเตือน">
            <span class="text-lg">🔔</span>
            <span id="topbar-badge" class="hidden absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center">0</span>
        </a>

        {{-- Branch switcher --}}
        <div class="relative">
            <button id="branch-btn" class="flex items-center gap-1 px-2 py-1.5 text-sm bg-slate-100 hover:bg-slate-200 rounded-full">
                <span>🏥</span>
                <span id="branch-current" class="hidden sm:inline max-w-[120px] truncate">…</span>
                <span class="text-slate-400 text-xs">▾</span>
            </button>
            <div id="branch-menu" class="hidden absolute right-0 mt-1 bg-white shadow-xl rounded-lg border border-slate-200 min-w-[220px] py-1 z-50"></div>
        </div>

        {{-- User menu --}}
        <div class="relative">
            <button id="user-btn" class="w-9 h-9 rounded-full bg-cyan-100 hover:bg-cyan-200 flex items-center justify-center text-sm font-semibold text-cyan-700" title="บัญชี">
                <span id="user-initial">?</span>
            </button>
            <div id="user-menu" class="hidden absolute right-0 mt-1 bg-white shadow-xl rounded-lg border border-slate-200 min-w-[200px] py-1 z-50">
                <div class="px-3 py-2 border-b">
                    <div id="user-name-display" class="text-sm font-semibold">…</div>
                    <div id="user-email-display" class="text-xs text-slate-500"></div>
                </div>
                <a href="/admin/staff" class="block px-3 py-1.5 text-sm hover:bg-slate-50">👥 พนักงาน</a>
                <a href="/admin/settings" class="block px-3 py-1.5 text-sm hover:bg-slate-50">⚙️ ตั้งค่าระบบ</a>
                <a href="/admin/system-health" class="block px-3 py-1.5 text-sm hover:bg-slate-50">🩺 System Health</a>
                <div class="border-t my-1"></div>
                <button id="user-logout" class="w-full text-left px-3 py-1.5 text-sm hover:bg-rose-50 text-rose-600">ออกจากระบบ</button>
            </div>
        </div>
    </div>

</header>

{{-- Bottom tab bar — visible on all sizes when authenticated --}}
<nav id="bottom-nav" class="fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur border-t border-slate-200 hidden shadow-[0_-2px_8px_rgba(0,0,0,0.05)]">
    <div class="max-w-2xl mx-auto grid grid-cols-7 h-14">
        <a href="/dashboard" class="bottom-tab" data-tab="/dashboard" title="หน้าหลัก">
            <span>🏠</span><span class="bottom-tab-label">หน้าหลัก</span>
        </a>
        <a href="/patients" class="bottom-tab" data-tab="/patients" title="ผู้ป่วย">
            <span>👤</span><span class="bottom-tab-label">ผู้ป่วย</span>
        </a>
        <a href="/appointments" class="bottom-tab" data-tab="/appointments" title="นัดหมาย">
            <span>📅</span><span class="bottom-tab-label">นัดหมาย</span>
        </a>
        <a href="/pos" class="bottom-tab" data-tab="/pos" title="POS">
            <span>💳</span><span class="bottom-tab-label">POS</span>
        </a>
        <a href="/inventory" class="bottom-tab" data-tab="/inventory" title="คลัง">
            <span>📦</span><span class="bottom-tab-label">คลัง</span>
        </a>
        <a href="/reports" class="bottom-tab" data-tab="/reports" title="รายงาน">
            <span>📋</span><span class="bottom-tab-label">รายงาน</span>
        </a>
        <button id="bottom-more" class="bottom-tab" title="เพิ่มเติม (Cmd+K)">
            <span>⋯</span><span class="bottom-tab-label">เพิ่มเติม</span>
        </button>
    </div>
    <style>
        .bottom-tab {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 2px; color: #64748b; font-size: 20px;
            border-top: 2px solid transparent;
            transition: color .15s, background .15s;
        }
        .bottom-tab-label { font-size: 10px; font-weight: 500; }
        .bottom-tab:hover { color: #0e7490; background: #ecfeff; }
        .bottom-tab.active { color: #0e7490; background: #ecfeff; border-top-color: #0891b2; }
        @media (max-width: 480px) {
            .bottom-tab-label { font-size: 9px; }
        }
    </style>
</nav>

@yield('body')

<script>
    // Toast notifications (replace alert())
    window.toast = {
        show(message, type = 'info', ms = 3500) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const colors = {
                success: 'bg-emerald-600',
                error: 'bg-rose-600',
                warning: 'bg-amber-500',
                info: 'bg-cyan-600',
            };
            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            const t = document.createElement('div');
            t.className = `${colors[type] || colors.info} text-white px-4 py-2 rounded-lg shadow-lg pointer-events-auto flex items-center gap-2 min-w-[220px] max-w-md transition transform translate-x-full opacity-0`;
            t.innerHTML = `<span>${icons[type] || icons.info}</span><span class="flex-1">${message}</span><button class="opacity-70 hover:opacity-100 ml-2">✕</button>`;
            t.querySelector('button').onclick = () => removeToast(t);
            container.appendChild(t);
            requestAnimationFrame(() => { t.classList.remove('translate-x-full', 'opacity-0'); });
            const timer = setTimeout(() => removeToast(t), ms);
            t.addEventListener('mouseenter', () => clearTimeout(timer));
            function removeToast(el) {
                el.classList.add('translate-x-full', 'opacity-0');
                setTimeout(() => el.remove(), 300);
            }
        },
        success(m, ms) { this.show(m, 'success', ms); },
        error(m, ms) { this.show(m, 'error', ms ?? 5000); },
        warning(m, ms) { this.show(m, 'warning', ms); },
        info(m, ms) { this.show(m, 'info', ms); },
    };

    // Skeleton loader helper: pass a target element, returns fn to remove
    window.skeleton = function (el, lines = 3) {
        const html = Array.from({ length: lines }, () =>
            '<div class="h-4 bg-slate-200 rounded animate-pulse mb-2"></div>'
        ).join('');
        const original = el.innerHTML;
        el.innerHTML = html;

        return () => { el.innerHTML = original; };
    };

    window.api = {
        base: '/api/v1',
        token: () => localStorage.getItem('debut.token'),
        branchId: () => localStorage.getItem('debut.branch_id'),
        async call(path, opts = {}) {
            const headers = Object.assign({
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            }, opts.headers || {});
            const t = this.token();
            const b = this.branchId();
            if (t) headers['Authorization'] = 'Bearer ' + t;
            if (b) headers['X-Branch-Id'] = b;
            const res = await fetch(this.base + path, Object.assign({}, opts, { headers }));
            const text = await res.text();
            let data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = { raw: text }; }
            return { ok: res.ok, status: res.status, data };
        },
        async upload(path, formData) {
            const headers = { 'Accept': 'application/json' };
            const t = this.token();
            const b = this.branchId();
            if (t) headers['Authorization'] = 'Bearer ' + t;
            if (b) headers['X-Branch-Id'] = b;
            const res = await fetch(this.base + path, { method: 'POST', headers, body: formData });
            const text = await res.text();
            let data = null;
            try { data = text ? JSON.parse(text) : null; } catch (e) { data = { raw: text }; }
            return { ok: res.ok, status: res.status, data };
        },
        clear() {
            localStorage.removeItem('debut.token');
            localStorage.removeItem('debut.branch_id');
        }
    };

    // Top bar setup (branch switcher + user menu + active link highlight)
    (async function setupTopBar() {
        if (!window.api.token() || window.location.pathname === '/login') return;
        const topbar = document.getElementById('topbar');
        const bottomNav = document.getElementById('bottom-nav');

        try {
            const me = await window.api.call('/auth/me');
            if (!me.ok) {
                window.api.clear();
                location.href = '/login';
                return;
            }

            const u = me.data?.data?.user;
            const branches = me.data?.data?.branches || [];
            const perms = me.data?.data?.permissions || [];

            // Make perms available globally for permission-aware UI
            window.userPerms = new Set(perms);
            window.hasPerm = (p) => {
                if (window.userPerms.has(p)) return true;
                // wildcard match: 'finance.reports.view' → check 'finance.*'
                const parts = p.split('.');
                for (let i = parts.length - 1; i > 0; i--) {
                    if (window.userPerms.has(parts.slice(0, i).join('.') + '.*')) return true;
                }
                return false;
            };

            // Show topbar + bottom-nav when authenticated
            topbar.classList.remove('hidden');
            if (bottomNav) {
                bottomNav.classList.remove('hidden');
                document.body.style.paddingBottom = '3.5rem';
            }

            // Highlight active bottom-tab
            const path = location.pathname;
            document.querySelectorAll('.bottom-tab[data-tab]').forEach(a => {
                if (a.dataset.tab === path || (a.dataset.tab !== '/dashboard' && path.startsWith(a.dataset.tab))) {
                    a.classList.add('active');
                }
            });

            // User initial + menu
            document.getElementById('user-initial').textContent = (u?.name || '?').charAt(0).toUpperCase();
            document.getElementById('user-name-display').textContent = u?.name || '';
            document.getElementById('user-email-display').textContent = u?.email || '';

            const userBtn = document.getElementById('user-btn');
            const userMenu = document.getElementById('user-menu');
            userBtn.onclick = (e) => { e.stopPropagation(); userMenu.classList.toggle('hidden'); document.getElementById('branch-menu').classList.add('hidden'); };
            document.getElementById('user-logout').onclick = async () => {
                await window.api.call('/auth/logout', { method: 'POST' });
                window.api.clear();
                location.href = '/login';
            };

            // Branch switcher
            const currentId = +window.api.branchId();
            const current = branches.find(b => b.id === currentId) || branches[0];
            if (current) {
                document.getElementById('branch-current').textContent = current.name;
                const menu = document.getElementById('branch-menu');
                menu.innerHTML = branches.map(b => `
                    <button data-id="${b.id}" class="w-full text-left px-3 py-2 hover:bg-cyan-50 text-sm ${b.id === current.id ? 'bg-cyan-50 font-semibold' : ''}">
                        ${b.id === current.id ? '✓ ' : '&nbsp;&nbsp;&nbsp;&nbsp;'}${b.name}
                        <span class="text-xs text-slate-400 ml-1">${b.code || ''}</span>
                    </button>`).join('');
                menu.querySelectorAll('button').forEach(btn => btn.onclick = () => {
                    const newId = +btn.dataset.id;
                    if (newId === current.id) { menu.classList.add('hidden'); return; }
                    localStorage.setItem('debut.branch_id', String(newId));
                    document.cookie = 'debut_branch_id=' + newId + '; path=/; max-age=31536000';
                    location.reload();
                });
                document.getElementById('branch-btn').onclick = (e) => {
                    e.stopPropagation(); menu.classList.toggle('hidden'); userMenu.classList.add('hidden');
                };
            }

            // Cmd-K button → opens command palette
            const cmdBtn = document.getElementById('cmd-k-btn');
            if (cmdBtn) {
                cmdBtn.onclick = () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true, bubbles: true }));
            }

            // Bottom-nav "More" button → opens command palette as a substitute drawer
            const moreBtn = document.getElementById('bottom-more');
            if (moreBtn) {
                moreBtn.onclick = () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true, bubbles: true }));
            }

            // Close menus on outside click
            document.addEventListener('click', () => {
                document.getElementById('branch-menu')?.classList.add('hidden');
                document.getElementById('user-menu')?.classList.add('hidden');
            });

            // Hint user on signal pages
            window.dispatchEvent(new CustomEvent('debut:user-loaded', { detail: { user: u, branches, perms } }));
        } catch (e) {
            console.warn('topbar setup error:', e);
        }
    })();

    // Command palette (Cmd/Ctrl+K)
    (function setupCommandPalette() {
        const PAGES = [
            { url: '/dashboard', icon: '🏠', label: 'Dashboard' },
            { url: '/patients', icon: '👤', label: 'Patients (ลูกค้า)' },
            { url: '/appointments', icon: '📅', label: 'Appointments (นัดหมาย)' },
            { url: '/follow-ups', icon: '⏰', label: 'Follow-ups' },
            { url: '/pos', icon: '💳', label: 'POS / Checkout' },
            { url: '/inventory', icon: '📦', label: 'Inventory (คลังสินค้า)' },
            { url: '/members', icon: '💎', label: 'Member Wallet' },
            { url: '/courses', icon: '🎫', label: 'Courses' },
            { url: '/lab/orders', icon: '🧪', label: 'Lab Orders' },
            { url: '/commissions', icon: '💸', label: 'Commissions' },
            { url: '/expenses', icon: '🧾', label: 'Expenses' },
            { url: '/closing', icon: '📊', label: 'Daily Closing' },
            { url: '/reports', icon: '📋', label: 'Reports Library' },
            { url: '/mis', icon: '📈', label: 'MIS Executive' },
            { url: '/marketing/coupons', icon: '🎟️', label: 'Marketing — Coupons' },
            { url: '/marketing/promotions', icon: '🎁', label: 'Marketing — Promotions' },
            { url: '/marketing/influencers', icon: '📢', label: 'Marketing — Influencers' },
            { url: '/marketing/reviews', icon: '⭐', label: 'Marketing — Reviews' },
            { url: '/admin/staff', icon: '👥', label: 'Admin — Staff' },
            { url: '/admin/payroll', icon: '💰', label: 'Admin — Payroll' },
            { url: '/time-clock', icon: '🕐', label: 'Time Clock Kiosk' },
            { url: '/admin/settings', icon: '⚙️', label: 'Settings Hub' },
            { url: '/qc/runs', icon: '✅', label: 'QC Runs' },
            { url: '/qc/checklists', icon: '📋', label: 'QC Checklists' },
            { url: '/audit-logs', icon: '📜', label: 'Audit Logs' },
            { url: '/admin/system-health', icon: '🩺', label: 'System Health' },
            { url: '/notifications', icon: '🔔', label: 'Notifications' },
            { url: '/accounting/ledger', icon: '📚', label: 'Accounting — Ledger' },
            { url: '/accounting/pr', icon: '📝', label: 'Accounting — PR' },
            { url: '/accounting/po', icon: '📦', label: 'Accounting — PO' },
            { url: '/accounting/disbursements', icon: '💵', label: 'Accounting — Disbursements' },
        ];

        const dlg = document.getElementById('cmd-palette');
        const input = document.getElementById('cmd-input');
        const results = document.getElementById('cmd-results');
        let selectedIdx = 0;
        let currentResults = [];

        function open() {
            if (!window.api?.token() && location.pathname !== '/login') return;
            input.value = '';
            renderResults('');
            dlg.showModal();
            setTimeout(() => input.focus(), 50);
        }

        function renderResults(query) {
            const q = query.toLowerCase().trim();
            const filtered = !q ? PAGES.slice(0, 12) :
                PAGES.filter(p => p.label.toLowerCase().includes(q) || p.url.includes(q));
            currentResults = filtered;
            selectedIdx = 0;
            results.innerHTML = filtered.map((p, i) => `
                <a data-i="${i}" href="${p.url}" class="block px-3 py-2 rounded ${i === 0 ? 'bg-cyan-50' : ''} hover:bg-slate-50 flex items-center gap-2">
                    <span class="text-xl">${p.icon}</span>
                    <div class="flex-1">
                        <div class="text-sm font-medium">${p.label}</div>
                        <div class="text-xs text-slate-400">${p.url}</div>
                    </div>
                </a>`).join('') || '<div class="text-slate-400 text-sm p-4 text-center">ไม่พบรายการ</div>';
        }

        function highlight() {
            results.querySelectorAll('a').forEach((a, i) => {
                a.classList.toggle('bg-cyan-50', i === selectedIdx);
            });
            const sel = results.querySelector(`[data-i="${selectedIdx}"]`);
            sel?.scrollIntoView({ block: 'nearest' });
        }

        input?.addEventListener('input', () => renderResults(input.value));
        input?.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { e.preventDefault(); selectedIdx = Math.min(selectedIdx + 1, currentResults.length - 1); highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); selectedIdx = Math.max(selectedIdx - 1, 0); highlight(); }
            else if (e.key === 'Enter' && currentResults[selectedIdx]) { location.href = currentResults[selectedIdx].url; }
            else if (e.key === 'Escape') dlg.close();
        });

        // Close button (visible on touch devices without keyboard)
        document.getElementById('cmd-close')?.addEventListener('click', () => dlg.close());

        // Click on backdrop closes dialog
        dlg?.addEventListener('click', (e) => {
            if (e.target === dlg) dlg.close();
        });

        document.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                open();
            }
            // Quick nav: g then h/p/a/l (vim-style)
            if (e.key === 'g' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                window.__waitG = true;
                setTimeout(() => window.__waitG = false, 1000);
            } else if (window.__waitG && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
                const map = { h: '/dashboard', p: '/patients', a: '/appointments', l: '/lab/orders', s: '/admin/settings', m: '/mis' };
                if (map[e.key]) { e.preventDefault(); location.href = map[e.key]; }
                window.__waitG = false;
            }
        });
    })();

    // Notification badge poller (60s) — updates top-bar bell
    (async function pollNotificationBadge() {
        const badge = document.getElementById('topbar-badge');
        if (!badge) return;
        if (window.location.pathname === '/login' || !window.api.token()) return;
        async function tick() {
            try {
                const r = await window.api.call('/notifications/unread-count');
                if (r.ok) {
                    const c = r.data?.data?.count || 0;
                    if (c > 0) {
                        badge.classList.remove('hidden');
                        badge.classList.add('flex');
                        badge.textContent = c > 99 ? '99+' : String(c);
                    } else {
                        badge.classList.add('hidden');
                        badge.classList.remove('flex');
                    }
                }
            } catch (e) {}
        }
        tick();
        setInterval(tick, 60_000);
    })();
</script>
@yield('scripts')
</body>
</html>

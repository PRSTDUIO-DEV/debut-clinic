@extends('layouts.app')
@section('title', 'เข้าสู่ระบบ')

@section('body')
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8">
        <div class="mb-6 text-center">
            <div class="text-3xl mb-2">💊</div>
            <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
            <p class="text-sm text-slate-500">เข้าสู่ระบบ</p>
        </div>

        @if (app()->environment('local'))
        <div id="quick-login" class="hidden mb-5 p-3 rounded-lg bg-amber-50 border border-amber-200">
            <div class="text-xs font-bold text-amber-900 mb-2">🚀 Quick Login (Dev only)</div>
            <select id="quick-select" class="w-full text-sm border border-amber-300 rounded px-2 py-1.5 mb-2">
                <option value="">— เลือกบัญชีเพื่อ login ทันที —</option>
            </select>
            <div class="text-xs text-amber-700">เลือกแล้วระบบจะกรอก email/password และเข้าสู่ระบบให้เลย</div>
        </div>
        @endif

        <form id="login-form" class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">อีเมล</label>
                <input id="email" type="email" required autocomplete="username"
                    class="w-full rounded-lg border-slate-300 focus:border-cyan-500 focus:ring-cyan-500 px-3 py-2 border">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">รหัสผ่าน</label>
                <input id="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-lg border-slate-300 focus:border-cyan-500 focus:ring-cyan-500 px-3 py-2 border">
            </div>
            <div id="error" class="hidden p-3 rounded-lg bg-red-50 text-red-700 text-sm"></div>
            <button type="submit" id="submit-btn"
                class="w-full bg-cyan-600 hover:bg-cyan-700 text-white py-2 rounded-lg font-semibold transition disabled:opacity-50">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
async function doLogin(email, password) {
    const errorEl = document.getElementById('error');
    const btn = document.getElementById('submit-btn');
    errorEl.classList.add('hidden');
    btn.disabled = true; btn.textContent = 'กำลังเข้าสู่ระบบ...';

    const res = await api.call('/auth/login', {
        method: 'POST',
        body: JSON.stringify({
            email, password,
            device_name: 'web-' + navigator.userAgent.slice(0, 30),
        })
    });

    btn.disabled = false; btn.textContent = 'เข้าสู่ระบบ';

    if (!res.ok) {
        errorEl.textContent = (res.data && res.data.message) || 'เข้าสู่ระบบไม่สำเร็จ';
        errorEl.classList.remove('hidden');
        return false;
    }

    const d = res.data.data;
    localStorage.setItem('debut.token', d.token);
    localStorage.setItem('debut.user', JSON.stringify(d.user));
    localStorage.setItem('debut.permissions', JSON.stringify(d.permissions));
    const primary = (d.branches || []).find(b => b.is_primary) || d.branches[0];
    if (primary) localStorage.setItem('debut.branch_id', primary.id);
    window.location.href = '/dashboard';
    return true;
}

document.getElementById('login-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    await doLogin(
        document.getElementById('email').value,
        document.getElementById('password').value,
    );
});

@if (app()->environment('local'))
(async function loadQuickAccounts() {
    const r = await fetch('/api/v1/dev/quick-accounts', { headers: { 'Accept': 'application/json' } });
    if (!r.ok) return;
    const j = await r.json();
    const accounts = (j && j.data) ? j.data : [];
    if (!accounts.length) return;
    const wrap = document.getElementById('quick-login');
    const sel = document.getElementById('quick-select');
    accounts.forEach(a => {
        const opt = document.createElement('option');
        opt.value = JSON.stringify({ email: a.email, password: a.password });
        opt.textContent = `${a.role || '?'} — ${a.name} (${a.email})`;
        sel.appendChild(opt);
    });
    wrap.classList.remove('hidden');
    sel.addEventListener('change', async () => {
        if (!sel.value) return;
        try {
            const { email, password } = JSON.parse(sel.value);
            document.getElementById('email').value = email;
            document.getElementById('password').value = password;
            await doLogin(email, password);
        } catch (e) {
            console.error(e);
        }
    });
})();
@endif
</script>
@endsection

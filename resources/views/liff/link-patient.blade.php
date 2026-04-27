<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เชื่อม LINE — Debut Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Noto Sans Thai', system-ui, sans-serif; }</style>
    <script charset="utf-8" src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
</head>
<body class="bg-emerald-50 min-h-screen">
<div class="max-w-md mx-auto p-6">
    <div class="bg-white rounded-xl shadow p-6 mt-6">
        <div class="text-center text-3xl mb-2">💎</div>
        <h1 class="text-xl font-bold text-center mb-1">Debut Clinic</h1>
        <p class="text-center text-slate-500 text-sm mb-4">เชื่อม LINE กับบัญชีผู้ป่วย</p>

        <div id="line-info" class="bg-emerald-50 rounded p-3 text-sm mb-4 hidden">
            <div class="text-xs text-slate-500 mb-1">LINE Profile</div>
            <div class="flex items-center gap-3">
                <img id="li-avatar" class="w-10 h-10 rounded-full bg-slate-200" alt="">
                <div>
                    <div id="li-name" class="font-semibold"></div>
                    <div id="li-userid" class="text-xs text-slate-500 font-mono"></div>
                </div>
            </div>
        </div>

        <div id="already-linked" class="hidden bg-cyan-50 rounded p-3 text-sm mb-4">
            <div class="font-semibold mb-1">✅ บัญชีนี้เชื่อมแล้ว</div>
            <div id="al-info" class="text-xs"></div>
        </div>

        <form id="form" class="space-y-3">
            <label class="block text-sm">HN (เลขประจำตัวผู้ป่วย)
                <input name="hn" class="w-full border rounded px-3 py-2 mt-1 font-mono">
            </label>
            <label class="block text-sm">หรือเบอร์โทร
                <input name="phone" class="w-full border rounded px-3 py-2 mt-1">
            </label>
            <button type="submit" class="w-full bg-cyan-600 text-white py-2 rounded">เชื่อมบัญชี</button>
        </form>

        <div id="status" class="text-sm mt-3 text-center"></div>
    </div>
    <div class="text-center text-xs text-slate-400 mt-4">© Debut Clinic</div>
</div>

<script>
const LIFF_ID = '{{ env("LINE_LIFF_ID", "") }}';
let idToken = null;

async function init() {
    try {
        if (LIFF_ID) {
            await liff.init({ liffId: LIFF_ID });
            if (!liff.isLoggedIn()) {
                liff.login({ redirectUri: window.location.href });
                return;
            }
            const profile = await liff.getProfile();
            idToken = liff.getIDToken();
            document.getElementById('li-avatar').src = profile.pictureUrl || '';
            document.getElementById('li-name').textContent = profile.displayName;
            document.getElementById('li-userid').textContent = profile.userId;
            document.getElementById('line-info').classList.remove('hidden');
        } else {
            // Dev mode: prompt for fake user_id
            const userId = prompt('LIFF_ID not configured — enter a fake LINE userId for dev:');
            const name = prompt('Display name:') || 'Dev User';
            if (userId) {
                idToken = `dev:${userId}:${name}`;
                document.getElementById('li-name').textContent = name;
                document.getElementById('li-userid').textContent = userId;
                document.getElementById('line-info').classList.remove('hidden');
            }
        }

        // Check if already linked
        const me = await fetch('/api/v1/liff/me', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_token: idToken }),
        }).then(r => r.json());
        if (me?.data?.patient) {
            document.getElementById('already-linked').classList.remove('hidden');
            document.getElementById('al-info').innerHTML = `${me.data.patient.name}<br><span class="font-mono">HN ${me.data.patient.hn}</span>`;
            document.getElementById('form').classList.add('hidden');
        }
    } catch (e) {
        document.getElementById('status').innerHTML = `<span class="text-rose-600">${e.message}</span>`;
    }
}

document.getElementById('form').addEventListener('submit', async e => {
    e.preventDefault();
    const f = e.target;
    const status = document.getElementById('status');
    status.innerHTML = '<span class="text-slate-500">กำลังเชื่อมบัญชี...</span>';
    try {
        const r = await fetch('/api/v1/liff/link-patient', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_token: idToken,
                hn: f.hn.value || null,
                phone: f.phone.value || null,
            }),
        });
        const data = await r.json();
        if (!r.ok || !data.ok) throw new Error(data.error || 'ไม่สำเร็จ');
        status.innerHTML = '<span class="text-emerald-700">✅ เชื่อมสำเร็จ! '+data.data.name+'</span>';
        setTimeout(() => location.reload(), 2000);
    } catch (e) {
        status.innerHTML = `<span class="text-rose-600">❌ ${e.message}</span>`;
    }
});

init();
</script>
</body>
</html>

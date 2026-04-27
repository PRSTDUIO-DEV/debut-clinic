<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Time Clock — Debut Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-cyan-500 to-purple-600 min-h-screen text-white">
<div class="min-h-screen flex flex-col items-center justify-center p-6">
    <h1 class="text-4xl font-bold mb-2">⏰ Time Clock</h1>
    <div id="clock" class="text-6xl font-mono font-bold mb-1"></div>
    <div id="date" class="text-lg text-cyan-100 mb-6"></div>

    <div class="bg-white text-slate-800 rounded-3xl shadow-2xl p-8 w-full max-w-md">
        <label class="block mb-2">
            <span class="text-sm font-semibold">รหัสพนักงาน</span>
            <input id="emp-code" autofocus class="w-full border-2 rounded-xl px-4 py-3 mt-1 text-2xl font-mono focus:border-cyan-500" autocomplete="off">
        </label>
        <label class="block mb-3">
            <span class="text-sm font-semibold">PIN</span>
            <input id="pin" type="password" inputmode="numeric" maxlength="6" class="w-full border-2 rounded-xl px-4 py-3 mt-1 text-2xl font-mono text-center tracking-widest focus:border-cyan-500" autocomplete="off">
        </label>

        <div class="grid grid-cols-2 gap-3">
            <button id="btn-in" class="bg-emerald-500 hover:bg-emerald-600 text-white text-xl font-bold py-4 rounded-xl shadow-lg active:scale-95 transition">
                🟢 Clock IN
            </button>
            <button id="btn-out" class="bg-rose-500 hover:bg-rose-600 text-white text-xl font-bold py-4 rounded-xl shadow-lg active:scale-95 transition">
                🔴 Clock OUT
            </button>
        </div>

        <div id="msg" class="mt-4 text-center text-lg min-h-[3rem]"></div>

        <div class="mt-4 pt-4 border-t border-slate-200">
            <label class="text-xs text-slate-400">สาขา
                <select id="branch" class="w-full border rounded px-2 py-1.5 mt-1 text-sm"></select>
            </label>
        </div>
    </div>

    <a href="/dashboard" class="mt-6 text-sm text-cyan-100 hover:text-white">← Admin</a>
</div>

<script>
function tick() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('th-TH');
    document.getElementById('date').textContent = now.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
}
setInterval(tick, 1000); tick();

async function loadBranches() {
    // No auth needed for branch list — try lookup endpoint
    try {
        const res = await fetch('/api/v1/lookups/branches', { headers: { 'Accept': 'application/json' } });
        if (res.ok) {
            const json = await res.json();
            document.getElementById('branch').innerHTML = (json.data || []).map(b => `<option value="${b.id}">${b.name}</option>`).join('');
        }
    } catch (e) {}
}
loadBranches();

async function action(kind) {
    const code = document.getElementById('emp-code').value.trim();
    const pin = document.getElementById('pin').value.trim();
    const branch = document.getElementById('branch').value;
    const msg = document.getElementById('msg');
    if (!code || !pin) { msg.innerHTML = '<span class="text-rose-600">⚠️ กรอกรหัสและ PIN ให้ครบ</span>'; return; }

    const res = await fetch(`/api/v1/public/time-clock/${kind}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ employee_code: code, pin, branch_id: +branch }),
    });
    const json = await res.json();
    if (res.ok) {
        const d = json.data;
        const time = new Date(d.time).toLocaleTimeString('th-TH');
        const verb = kind === 'in' ? 'IN' : 'OUT';
        const lateText = d.late_minutes > 0 ? `<span class="text-amber-600">⏰ สาย ${d.late_minutes} นาที</span>` : '';
        const otText = d.overtime_minutes > 0 ? `<span class="text-violet-600">+ OT ${d.overtime_minutes} นาที</span>` : '';
        msg.innerHTML = `<div class="text-emerald-600 font-bold">✅ ${d.user.name}</div>
            <div class="text-slate-600">Clock-${verb} เวลา ${time}</div>
            <div class="text-sm">${lateText} ${otText}</div>`;
        document.getElementById('emp-code').value = '';
        document.getElementById('pin').value = '';
        document.getElementById('emp-code').focus();
        setTimeout(() => msg.innerHTML = '', 5000);
    } else {
        msg.innerHTML = `<span class="text-rose-600">❌ ${Object.values(json.errors || {}).flat().join(' / ') || 'เกิดข้อผิดพลาด'}</span>`;
    }
}

document.getElementById('btn-in').onclick = () => action('in');
document.getElementById('btn-out').onclick = () => action('out');
document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        if (document.activeElement?.id === 'pin' || document.activeElement?.id === 'emp-code') {
            action('in');
        }
    }
});
</script>
</body>
</html>

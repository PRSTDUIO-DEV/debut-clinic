<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีวิว Debut Clinic</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
<div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-md">
    <h1 class="text-xl font-bold mb-1">⭐ รีวิวการให้บริการ</h1>
    <p class="text-sm text-slate-500 mb-4">ขอบคุณที่ใช้บริการ Debut Clinic — โปรดให้คะแนนและความคิดเห็นของท่าน</p>

    <form id="form" class="space-y-3">
        <div>
            <label class="text-sm font-semibold">ให้คะแนน</label>
            <div id="stars" class="flex gap-1 text-3xl text-slate-300 cursor-pointer mt-1">
                @for ($i = 1; $i <= 5; $i++)
                    <span data-rating="{{ $i }}">☆</span>
                @endfor
            </div>
            <input type="hidden" name="rating" id="rating-input" value="0">
        </div>
        <input name="title" placeholder="หัวข้อ (ไม่บังคับ)" class="w-full border rounded-lg px-3 py-2">
        <textarea name="body" rows="4" placeholder="ความคิดเห็นของท่าน..." class="w-full border rounded-lg px-3 py-2"></textarea>
        <button type="submit" class="w-full bg-cyan-600 text-white font-semibold py-2 rounded-lg">ส่งรีวิว</button>
        <div id="msg" class="text-sm text-center mt-2"></div>
    </form>
</div>

<script>
const TOKEN = @json($token);

document.querySelectorAll('#stars span').forEach(s => {
    s.onclick = () => {
        const r = +s.dataset.rating;
        document.getElementById('rating-input').value = r;
        document.querySelectorAll('#stars span').forEach((x, i) => {
            x.textContent = i < r ? '★' : '☆';
            x.className = i < r ? 'text-amber-500' : 'text-slate-300';
        });
    };
});

document.getElementById('form').onsubmit = async (e) => {
    e.preventDefault();
    const data = {
        rating: +document.getElementById('rating-input').value,
        title: e.target.title.value || null,
        body: e.target.body.value || null,
    };
    if (!data.rating) return alert('โปรดเลือกคะแนน');

    const res = await fetch('/api/v1/public/reviews/' + TOKEN, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    const json = await res.json();
    const msg = document.getElementById('msg');
    if (res.ok) {
        msg.className = 'text-sm text-emerald-700 text-center mt-2';
        msg.textContent = '✅ ส่งรีวิวแล้ว ขอบคุณครับ/ค่ะ';
        setTimeout(() => location.reload(), 1500);
    } else {
        msg.className = 'text-sm text-rose-700 text-center mt-2';
        msg.textContent = JSON.stringify(json.errors || json);
    }
};
</script>
</body>
</html>

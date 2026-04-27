<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขอบคุณ</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-2xl shadow-lg p-8 text-center max-w-sm">
    <div class="text-5xl mb-2">✅</div>
    <h1 class="text-xl font-bold mb-2">ขอบคุณสำหรับรีวิว</h1>
    <p class="text-slate-500">ความคิดเห็นของท่านได้ถูกบันทึกเรียบร้อย</p>
    <div class="mt-4 text-amber-500 text-2xl">{{ str_repeat('★', $review->rating).str_repeat('☆', 5 - $review->rating) }}</div>
</div>
</body>
</html>

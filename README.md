# Debut Clinic — Backend (Laravel 11)

## Stack

- PHP 8.5 (รองรับ Laravel 11 ^8.2)
- Laravel 11.31
- MySQL 8.0
- Redis 8.x (cache, queue, session)
- Meilisearch 1.42 (full-text search)
- Sanctum (API token)
- Scout (search abstraction) + Meilisearch driver

## Quick Start

```bash
# 1) ติดตั้ง dependency
composer install

# 2) สร้าง .env จาก template + ใส่ APP_KEY
cp .env.example .env
php artisan key:generate

# 3) ตั้งค่า DB ใน .env (ค่าเริ่มต้นใช้กับ local)
# DB_DATABASE=debut_clinic, DB_USERNAME=root, DB_PASSWORD=

# 4) Migrate
php artisan migrate

# 5) รัน dev server
php artisan serve
# หรือ: php -S 127.0.0.1:8000 -t public
```

## Health Check

- DB: `php artisan migrate:status`
- Redis: `php artisan tinker` → `cache()->put('k','v',60); cache()->get('k');`
- Meilisearch: `curl http://127.0.0.1:7700/health`

## Tooling

- Code style: `vendor/bin/pint` (config ที่ `pint.json`)
- ตรวจสอบไม่แก้: `vendor/bin/pint --test`
- Pre-commit hook ติดตั้งที่ root repo (`.git/hooks/pre-commit`)

## Notes

- PHP 8.5 มี deprecation warning `PDO::MYSQL_ATTR_SSL_CA` — ไม่ส่งผลต่อ runtime
- ค่า dev `MAIL_MAILER=log` (mail ทุกฉบับเขียนลง log ไม่ส่งจริง)
- Trinity workflow และเอกสารโปรเจกต์อยู่ที่ root: `IMPLEMENTATION_BRIEF.md`, `ARCHITECTURE.md`, `API_CONTRACT.md`

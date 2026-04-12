# Dummy CRM

Лёгкая CRM для тимлида: проекты, заметки, трекинг времени, планирование (Gantt), burndown и отчётность.

## Стек

- Backend: Laravel (API)
- Frontend: Vue 3 + TypeScript + Vite
- DB: PostgreSQL
- Auth: Laravel Sanctum (SPA cookies)
- Markdown: `markforge/markforge`

## Структура репозитория

- `backend/` — Laravel API
- `frontend/` — Vue SPA

## Быстрый старт (Docker)

Требования:

- Docker + Docker Compose

Запуск:

```bash
docker compose up --build
```

Адреса:

- Frontend: http://localhost:5173
- Backend: http://localhost:8000
- Healthcheck: http://localhost:8000/api/health

## Projects API

Базовый CRUD проектов:

- `GET /api/projects`
- `POST /api/projects`
- `GET /api/projects/{id}`
- `PUT /api/projects/{id}`
- `DELETE /api/projects/{id}`

## Переменные окружения

Docker по умолчанию поднимает PostgreSQL с кредами:

- DB: `crm`
- User: `crm`
- Password: `crm`

`backend/.env` создаётся из `backend/.env.example` при старте контейнера.

## Разработка
 
 - Frontend использует `VITE_API_BASE_URL` (в Docker выставляется в `docker-compose.yml`).
 - Backend поднимается на `0.0.0.0:8000` командой `php artisan serve` внутри контейнера.

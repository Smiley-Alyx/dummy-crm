# Dummy CRM

Лёгкая CRM для тимлида: проекты, заметки, трекинг времени, планирование (Gantt), burndown и отчётность.

Сейчас реализовано:

- Проекты (CRUD)
- Заметки (CRUD)
- Учёт времени (CRUD + сводка)
- Планирование по отгрузкам: задачи, назначения (мощность в часах/день), ежедневные отметки
- Диаграмма Ганта по отгрузке + экспорт в XLSX

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

- Frontend: http://localhost:5174
- Backend: http://localhost:8000
- Healthcheck: http://localhost:8000/api/health

## Интерфейс (SPA)

- Проекты: `/#/projects` (в dev-режиме: `/projects`)
- Отгрузки проекта: `/projects/:projectId/shipments`
- Отгрузка (задачи, назначения, отметки): `/projects/:projectId/shipments/:shipmentId`
- Гант по отгрузке: `/projects/:projectId/shipments/:shipmentId/gantt`

## Shipments API

- `GET /api/shipments?project_id=...`
- `POST /api/shipments`
- `GET /api/shipments/{id}`
- `PUT /api/shipments/{id}`
- `DELETE /api/shipments/{id}`

Планирование:

- `GET /api/shipments/{id}/gantt` — расчётная диаграмма Ганта по задачам
- `GET /api/shipments/{id}/export` — экспорт XLSX (диаграмма + burndown)

Справочник пользователей (для назначений):

- `GET /api/users`

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

Важно: внутри Docker контейнеров переменные `DB_*` задаются через `docker-compose.yml`.

## Разработка
 
 - Frontend использует `VITE_API_BASE_URL` (в Docker выставляется в `docker-compose.yml`).
 - Backend поднимается на `0.0.0.0:8000` командой `php artisan serve` внутри контейнера.

## Тесты

Backend:

```bash
docker compose exec backend composer test
```

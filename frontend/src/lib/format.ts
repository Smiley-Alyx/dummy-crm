export function parseYmd(ymd: string): Date | null {
  if (!ymd) return null

  const m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(ymd)
  if (!m) return null

  const y = Number(m[1])
  const mo = Number(m[2])
  const d = Number(m[3])

  if (!Number.isFinite(y) || !Number.isFinite(mo) || !Number.isFinite(d)) return null

  return new Date(y, mo - 1, d)
}

export function parseIso(value: string): Date | null {
  if (!value) return null

  const ymd = parseYmd(value)
  if (ymd) return ymd

  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) return null

  return dt
}

const ruDate = new Intl.DateTimeFormat('ru-RU', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
})

const ruDateTime = new Intl.DateTimeFormat('ru-RU', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
})

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const d = parseIso(value)
  if (!d) return value
  return ruDate.format(d)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const d = parseIso(value)
  if (!d) return value
  return ruDateTime.format(d)
}

export function formatMinutes(minutes: number): string {
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  if (h <= 0) return `${m} мин`
  if (m === 0) return `${h} ч`
  return `${h} ч ${m} мин`
}

export function formatStage(stage: string | null | undefined): string {
  if (!stage) return '—'

  const map: Record<string, string> = {
    planned: 'План',
    in_progress: 'В работе',
    done: 'Готово',
    dev: 'Разработка',
    qa: 'Тестирование',
    prod: 'Прод',
  }

  return map[stage] ?? stage
}

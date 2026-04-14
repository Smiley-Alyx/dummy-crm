<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'
import { formatDate, formatMinutes } from '../lib/format'

type TimeEntry = {
  id: number
  project_id: number
  entry_date: string
  minutes: number
  note: string | null
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

type User = {
  id: number
  name: string
  email: string
}

type Project = {
  id: number
  name: string
}

type ReportTaskRow = {
  task_id: number
  task_title: string
  minutes: number
}

type ReportShipmentBlock = {
  shipment_id: number | null
  shipment_title: string
  minutes: number
  tasks: ReportTaskRow[]
}

type ReportProjectBlock = {
  project_id: number
  project_name: string
  minutes: number
  shipments: ReportShipmentBlock[]
}

type FlatReportRow =
  | { kind: 'shipment'; key: string; shipment_title: string; minutes: number }
  | { kind: 'task'; key: string; task_title: string; task_id: number; minutes: number }

type ReportResponse = {
  user_id: number
  project_id: number | null
  from: string | null
  to: string | null
  total_minutes: number
  projects: ReportProjectBlock[]
}

type SummaryByDayRow = {
  entry_date: string
  minutes: number
}

type SummaryResponse = {
  total_minutes: number
  by_day: SummaryByDayRow[]
}

const timeEntries = ref<TimeEntry[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const mode = ref<'entries' | 'report'>('entries')

const projectId = ref<string>('')
const from = ref<string>('')
const to = ref<string>('')

const reportUserId = ref<number | null>(null)
const users = ref<User[]>([])
const projects = ref<Project[]>([])
const report = ref<ReportResponse | null>(null)

const summary = ref<SummaryResponse | null>(null)

const totalHours = computed(() => {
  const total = summary.value?.total_minutes ?? 0
  return (total / 60).toFixed(2)
})

function reportRows(p: ReportProjectBlock): FlatReportRow[] {
  const out: FlatReportRow[] = []
  for (const s of p.shipments) {
    out.push({
      kind: 'shipment',
      key: `s-${p.project_id}-${String(s.shipment_id)}`,
      shipment_title: s.shipment_title,
      minutes: s.minutes,
    })
    for (const t of s.tasks) {
      out.push({
        kind: 'task',
        key: `t-${p.project_id}-${String(s.shipment_id)}-${t.task_id}`,
        task_title: t.task_title,
        task_id: t.task_id,
        minutes: t.minutes,
      })
    }
  }
  return out
}

async function fetchAll() {
  loading.value = true
  error.value = null

  try {
    if (mode.value === 'report') {
      if (!reportUserId.value) {
        report.value = null
        return
      }

      const params: Record<string, any> = {
        user_id: reportUserId.value,
      }
      if (projectId.value) params.project_id = Number(projectId.value)
      if (from.value) params.from = from.value
      if (to.value) params.to = to.value

      const res = await api.get<ReportResponse>('/api/task-work-logs/report', { params })
      report.value = res.data
      timeEntries.value = []
      summary.value = null
      return
    }

    const params: Record<string, any> = {}

    if (projectId.value) params.project_id = Number(projectId.value)
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value

    const [entriesRes, summaryRes] = await Promise.all([
      api.get<Paginated<TimeEntry>>('/api/time-entries', { params }),
      api.get<SummaryResponse>('/api/time-entries/summary', { params }),
    ])

    timeEntries.value = entriesRes.data.data
    summary.value = summaryRes.data
  } catch (e: any) {
    error.value = e?.message ?? 'Не удалось загрузить записи времени'
  } finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const [usersRes, projectsRes] = await Promise.all([
      api.get<Paginated<User>>('/api/users', { params: { per_page: 200 } }),
      api.get<Paginated<Project>>('/api/projects', { params: { per_page: 200 } }),
    ])
    users.value = usersRes.data.data
    projects.value = projectsRes.data.data
    if (reportUserId.value == null && users.value.length) {
      reportUserId.value = users.value[0]!.id
    }
  } catch {
    // ignore
  }
}

async function removeEntry(id: number) {
  if (!confirm('Удалить запись?')) return

  try {
    await api.delete(`/api/time-entries/${id}`)
    await fetchAll()
  } catch (e: any) {
    alert(e?.message ?? 'Не удалось удалить запись')
  }
}

onMounted(async () => {
  await loadLookups()
  await fetchAll()
})
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Учёт времени</h1>
        <div class="sheet-subtitle">Факт по минутам + сводка / отчёт по исполнителям</div>
      </div>

      <div class="sheet-actions">
        <button type="button" class="sheet-btn" :class="{ 'sheet-btn-primary': mode === 'entries' }" @click="mode = 'entries'; fetchAll()">
          Записи
        </button>
        <button type="button" class="sheet-btn" :class="{ 'sheet-btn-primary': mode === 'report' }" @click="mode = 'report'; fetchAll()">
          Отчёт
        </button>
        <RouterLink v-if="mode === 'entries'" class="sheet-link" to="/time/new">Добавить</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div class="sheet-form" style="margin-bottom: 12px;">
        <div class="sheet-grid-4" style="align-items: end; grid-template-columns: 1fr 1fr 1fr auto;" v-if="mode === 'entries'">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Проект ID</div>
            <input v-model="projectId" class="sheet-input" inputmode="numeric" placeholder="например, 1" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">С</div>
            <input v-model="from" class="sheet-input" type="date" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">По</div>
            <input v-model="to" class="sheet-input" type="date" />
          </label>

          <button type="button" class="sheet-btn sheet-btn-primary" @click="fetchAll">Применить</button>
        </div>

        <div class="sheet-grid-4" style="align-items: end; grid-template-columns: 1.4fr 1fr 1fr 1fr auto;" v-else>
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Сотрудник</div>
            <select v-model.number="reportUserId" class="sheet-select">
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Проект</div>
            <select v-model="projectId" class="sheet-select">
              <option value="">Все</option>
              <option v-for="p in projects" :key="p.id" :value="String(p.id)">{{ p.name }} (#{{ p.id }})</option>
            </select>
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">С</div>
            <input v-model="from" class="sheet-input" type="date" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">По</div>
            <input v-model="to" class="sheet-input" type="date" />
          </label>

          <button type="button" class="sheet-btn sheet-btn-primary" @click="fetchAll">Применить</button>
        </div>

        <div v-if="mode === 'entries' && summary" class="sheet-muted" style="font-size: 13px;">
          <strong>Итого:</strong> {{ formatMinutes(summary.total_minutes) }} ({{ totalHours }} ч)
        </div>

        <div v-if="mode === 'report' && report" class="sheet-muted" style="font-size: 13px;">
          <strong>Итого:</strong> {{ formatMinutes(report.total_minutes) }} ({{ (report.total_minutes / 60).toFixed(2) }} ч)
        </div>
      </div>

      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else-if="mode === 'entries'" class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Дата</th>
              <th>Проект</th>
              <th>Время</th>
              <th>Комментарий</th>
              <th class="right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in timeEntries" :key="t.id">
              <td>{{ formatDate(t.entry_date) }}</td>
              <td>{{ t.project_id }}</td>
              <td>{{ formatMinutes(t.minutes) }}</td>
              <td>{{ t.note ?? '—' }}</td>
              <td class="right">
                <RouterLink class="sheet-link" :to="`/time/${t.id}/edit`">Изменить</RouterLink>
                <span> | </span>
                <a class="sheet-link" href="#" @click.prevent="removeEntry(t.id)">Удалить</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Проект / Отгрузка / Задача</th>
              <th class="right">Время</th>
            </tr>
          </thead>
          <tbody v-if="!report || !report.projects.length">
            <tr>
              <td colspan="2" class="sheet-muted">Нет данных</td>
            </tr>
          </tbody>
          <tbody v-else v-for="p in report.projects" :key="p.project_id">
            <tr>
              <td style="font-weight: 700; background: var(--sheet-header);">{{ p.project_name }} (#{{ p.project_id }})</td>
              <td class="right" style="font-weight: 700; background: var(--sheet-header);">{{ formatMinutes(p.minutes) }}</td>
            </tr>

            <tr v-for="r in reportRows(p)" :key="r.key">
              <td v-if="r.kind === 'shipment'" style="padding-left: 18px; font-weight: 700;">{{ r.shipment_title }}</td>
              <td v-else style="padding-left: 34px;">{{ r.task_title }} (#{{ r.task_id }})</td>

              <td v-if="r.kind === 'shipment'" class="right" style="font-weight: 700;">{{ formatMinutes(r.minutes) }}</td>
              <td v-else class="right">{{ formatMinutes(r.minutes) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

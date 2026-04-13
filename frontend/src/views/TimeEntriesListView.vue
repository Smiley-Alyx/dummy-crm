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

const projectId = ref<string>('')
const from = ref<string>('')
const to = ref<string>('')

const summary = ref<SummaryResponse | null>(null)

const totalHours = computed(() => {
  const total = summary.value?.total_minutes ?? 0
  return (total / 60).toFixed(2)
})

async function fetchAll() {
  loading.value = true
  error.value = null

  try {
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

async function removeEntry(id: number) {
  if (!confirm('Удалить запись?')) return

  try {
    await api.delete(`/api/time-entries/${id}`)
    await fetchAll()
  } catch (e: any) {
    alert(e?.message ?? 'Не удалось удалить запись')
  }
}

onMounted(fetchAll)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Учёт времени</h1>
        <div class="sheet-subtitle">Факт по минутам + сводка</div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/time/new">Добавить</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div class="sheet-form" style="margin-bottom: 12px;">
        <div class="sheet-grid-4" style="align-items: end;">
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

        <div v-if="summary" class="sheet-muted" style="font-size: 13px;">
          <strong>Итого:</strong> {{ formatMinutes(summary.total_minutes) }} ({{ totalHours }} ч)
        </div>
      </div>

      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
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
    </div>
  </div>
</template>

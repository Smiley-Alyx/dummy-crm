<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { formatDate, formatStage } from '../lib/format'
import BurndownChart from '../components/BurndownChart.vue'

type Shipment = {
  id: number
  project_id: number
  title: string
  description: string | null
  planned_start_date: string | null
  planned_due_date: string | null
  created_at: string
  updated_at: string
}

type GanttTask = {
  id: number
  title: string
  start_date: string
  due_date: string | null
  planned_end_date: string | null
  control_date: string | null
  effective_due_date: string | null
  color: 'white' | 'green' | 'yellow' | 'red'
  stage: string
}

type GanttResponse = {
  shipment: Shipment
  today: string
  tasks: GanttTask[]
}

type BurndownPoint = {
  i: number
  date: string | null
  planned_remaining_hours: number
  actual_remaining_hours: number
  spent_cumulative_hours: number
}

type BurndownResponse = {
  total_estimate_hours: number
  points: BurndownPoint[]
}

const route = useRoute()
const projectId = computed(() => Number(route.params.projectId))
const shipmentId = computed(() => Number(route.params.shipmentId))

const loading = ref(false)
const error = ref<string | null>(null)

const shipment = ref<Shipment | null>(null)
const today = ref<string>('')
const tasks = ref<GanttTask[]>([])

const burndownTotal = ref<number>(0)
const burndownPoints = ref<BurndownPoint[]>([])

function parseDate(s: string): Date {
  const [y, m, d] = s.split('-').map((x) => Number(x))
  return new Date(y, m - 1, d)
}

function toYmd(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function isWeekday(d: Date): boolean {
  const wd = d.getDay()
  return wd !== 0 && wd !== 6
}

const calendarDays = computed(() => {
  if (!tasks.value.length) return [] as { ymd: string; label: string }[]

  let min: Date | null = null
  let max: Date | null = null

  for (const t of tasks.value) {
    const start = parseDate(t.start_date)
    const due = t.effective_due_date ? parseDate(t.effective_due_date) : null

    if (!min || start < min) min = start
    if (due && (!max || due > max)) max = due
  }

  if (!min) return []
  if (!max) max = min

  const out: { ymd: string; label: string }[] = []
  const cur = new Date(min)

  while (cur <= max) {
    if (isWeekday(cur)) {
      const ymd = toYmd(cur)
      out.push({
        ymd,
        label: `${String(cur.getDate()).padStart(2, '0')}.${String(cur.getMonth() + 1).padStart(2, '0')}`,
      })
    }
    cur.setDate(cur.getDate() + 1)
  }

  return out
})

function cellStyle(task: GanttTask, dayYmd: string) {
  const start = task.start_date
  const due = task.effective_due_date

  let bg = '#ffffff'

  if (dayYmd < start) {
    bg = '#ffffff'
  } else if (due && dayYmd <= due) {
    if (task.color === 'green') bg = '#C6EFCE'
    if (task.color === 'yellow') bg = '#FFEB9C'
    if (task.color === 'red') bg = '#FFC7CE'
    if (task.color === 'white') bg = '#ffffff'
  } else {
    bg = '#ffffff'
  }

  const style: Record<string, string> = {
    background: bg,
    width: '14px',
    height: '14px',
    border: '1px solid #e5e7eb',
  }

  if (task.control_date && dayYmd === task.control_date) {
    style.border = '2px solid #111827'
  }

  if (today.value && dayYmd === today.value) {
    style.outline = '2px solid #2563eb'
    style.outlineOffset = '-2px'
  }

  return style
}

async function fetchGantt() {
  loading.value = true
  error.value = null

  try {
    const [ganttRes, burndownRes] = await Promise.all([
      api.get<GanttResponse>(`/api/shipments/${shipmentId.value}/gantt`),
      api.get<BurndownResponse>(`/api/shipments/${shipmentId.value}/burndown`),
    ])

    shipment.value = ganttRes.data.shipment
    today.value = ganttRes.data.today
    tasks.value = ganttRes.data.tasks

    burndownTotal.value = burndownRes.data.total_estimate_hours
    burndownPoints.value = burndownRes.data.points
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить диаграмму Ганта'
  } finally {
    loading.value = false
  }
}

function downloadExport() {
  window.location.href = `${api.defaults.baseURL}/api/shipments/${shipmentId.value}/export`
}

watch(
  () => [route.params.projectId, route.params.shipmentId],
  () => fetchGantt(),
)

onMounted(fetchGantt)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Гант</h1>
        <div class="sheet-subtitle">Отгрузка №{{ shipmentId }}<span v-if="shipment"> — {{ shipment.title }}</span></div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments/${shipmentId}`">← Назад</RouterLink>
        <button type="button" class="sheet-btn sheet-btn-primary" @click="downloadExport">Экспорт XLSX</button>
      </div>
    </div>

    <div class="sheet-body">
      <BurndownChart v-if="burndownPoints.length" :points="burndownPoints" :total-estimate-hours="burndownTotal" />

      <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #C6EFCE; border: 1px solid #e5e7eb;"></span> В срок
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #FFEB9C; border: 1px solid #e5e7eb;"></span> Риск
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #FFC7CE; border: 1px solid #e5e7eb;"></span> Просрочено
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #fff; border: 1px solid #e5e7eb;"></span> Не начато
      </span>
      <span style="color: #6b7280;">Сегодня — синяя обводка; контрольная точка — толстая рамка</span>
    </div>

    <div v-if="loading">Загрузка...</div>
    <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

    <div v-else class="sheet-table-wrap">
      <table class="sheet-table" style="min-width: 980px;">
        <thead>
          <tr>
            <th class="sheet-sticky-col sheet-th" style="min-width: 340px;">
              Задача
            </th>
            <th
              v-for="d in calendarDays"
              :key="d.ymd"
              style="text-align: center; font-size: 12px; color: var(--sheet-muted);"
            >
              {{ d.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in tasks" :key="t.id">
            <td class="sheet-sticky-col">
              <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                <div>
                  <div>{{ t.title }}</div>
                  <div style="font-size: 12px; color: #6b7280;">
                    старт: {{ formatDate(t.start_date) }}
                    <span> | </span>
                    дедлайн: {{ formatDate(t.effective_due_date) }}
                    <span> | </span>
                    стадия: {{ formatStage(t.stage) }}
                  </div>
                </div>
                <div style="font-size: 12px; color: #6b7280;">#{{ t.id }}</div>
              </div>
            </td>
            <td v-for="d in calendarDays" :key="d.ymd" style="padding: 2px;">
              <div :style="cellStyle(t, d.ymd)"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    </div>
  </div>
</template>

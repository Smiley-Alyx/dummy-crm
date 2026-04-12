<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'

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

const route = useRoute()
const projectId = computed(() => Number(route.params.projectId))
const shipmentId = computed(() => Number(route.params.shipmentId))

const loading = ref(false)
const error = ref<string | null>(null)

const shipment = ref<Shipment | null>(null)
const today = ref<string>('')
const tasks = ref<GanttTask[]>([])

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
    const res = await api.get<GanttResponse>(`/api/shipments/${shipmentId.value}/gantt`)
    shipment.value = res.data.shipment
    today.value = res.data.today
    tasks.value = res.data.tasks
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Failed to load gantt'
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
  <div style="max-width: 1100px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
      <div style="display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap;">
        <h1 style="margin: 0;">Gantt</h1>
        <span style="color: #6b7280;">Shipment #{{ shipmentId }}</span>
        <span v-if="shipment" style="color: #111827;">— {{ shipment.title }}</span>
      </div>

      <div style="display: flex; gap: 12px; align-items: center;">
        <button
          type="button"
          @click="downloadExport"
          style="padding: 8px 10px; border: 1px solid #111827; border-radius: 8px; background: #111827; color: #fff;"
        >
          Export XLSX
        </button>
      </div>
    </div>

    <div style="margin-top: 12px; display: flex; gap: 12px; flex-wrap: wrap;">
      <RouterLink :to="`/projects/${projectId}/shipments/${shipmentId}`">← Back to shipment</RouterLink>
    </div>

    <div style="margin-top: 12px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #C6EFCE; border: 1px solid #e5e7eb;"></span> Green
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #FFEB9C; border: 1px solid #e5e7eb;"></span> Yellow
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #FFC7CE; border: 1px solid #e5e7eb;"></span> Red
      </span>
      <span style="display: inline-flex; align-items: center; gap: 6px;">
        <span style="width: 12px; height: 12px; background: #fff; border: 1px solid #e5e7eb;"></span> White (not started)
      </span>
      <span style="color: #6b7280;">Today outlined in blue; control date has thick border</span>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Loading...</div>
    <div v-else-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <div v-else style="margin-top: 16px; overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 10px;">
      <table style="border-collapse: collapse; width: 100%; min-width: 980px;">
        <thead>
          <tr>
            <th style="text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; position: sticky; left: 0; background: #fff; z-index: 2; min-width: 320px;">
              Task
            </th>
            <th
              v-for="d in calendarDays"
              :key="d.ymd"
              style="padding: 8px 4px; border-bottom: 1px solid #e5e7eb; text-align: center; font-size: 12px; color: #6b7280;"
            >
              {{ d.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in tasks" :key="t.id">
            <td
              style="padding: 8px; border-bottom: 1px solid #f3f4f6; position: sticky; left: 0; background: #fff; z-index: 1;"
            >
              <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 8px;">
                <div>
                  <div>{{ t.title }}</div>
                  <div style="font-size: 12px; color: #6b7280;">
                    start: {{ t.start_date }}
                    <span> | </span>
                    due: {{ t.effective_due_date ?? '—' }}
                    <span> | </span>
                    stage: {{ t.stage }}
                  </div>
                </div>
                <div style="font-size: 12px; color: #6b7280;">#{{ t.id }}</div>
              </div>
            </td>
            <td v-for="d in calendarDays" :key="d.ymd" style="padding: 2px; border-bottom: 1px solid #f3f4f6;">
              <div :style="cellStyle(t, d.ymd)"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { formatDate, formatStage } from '../lib/format'
import BurndownChart from '../components/BurndownChart.vue'

type Project = {
  id: number
  name: string
  starts_on: string | null
  ends_on: string | null
}

function exportExcel() {
  const base = String(import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '')
  window.location.href = `${base}/api/projects/${projectId.value}/gantt-export`
}

type Shipment = {
  id: number
  project_id: number
  title: string
  planned_start_date: string | null
  planned_due_date: string | null
}

type TaskAssignee = {
  user_id: number
  name: string
  capacity_hours_per_day: number
}

type SheetTask = {
  id: number
  shipment_id: number | null
  order: number
  title: string
  start_date: string
  effective_due_date: string | null
  stage: string
  capacity_hours_per_day: number
  remaining_hours: number
  assignees: TaskAssignee[]
  color: 'white' | 'green' | 'yellow' | 'red'
  control_date: string | null
}

type GanttSheetResponse = {
  project: Project
  today: string
  shipments: Shipment[]
  tasks: SheetTask[]
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

const loading = ref(false)
const error = ref<string | null>(null)

const project = ref<Project | null>(null)
const today = ref<string>('')
const shipments = ref<Shipment[]>([])
const tasks = ref<SheetTask[]>([])

const burndownOpen = ref(true)
const burndownTotal = ref<number>(0)
const burndownPoints = ref<BurndownPoint[]>([])

const shipmentOpen = ref<Record<string, boolean>>({})

const windowWorkdays = ref(25)
const viewStartYmd = ref<string | null>(null)

function parseDate(s: string): Date {
  const base = (s ?? '').slice(0, 10)
  const [y, m, d] = base.split('-').map((x) => Number(x))
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

function nextWorkdayYmd(ymd: string): string {
  const cur = parseDate(ymd)
  while (!isWeekday(cur)) {
    cur.setDate(cur.getDate() + 1)
  }
  return toYmd(cur)
}

function addWorkdays(ymd: string, delta: number): string {
  const cur = parseDate(ymd)
  const step = delta >= 0 ? 1 : -1
  let remaining = Math.abs(delta)
  while (remaining > 0) {
    cur.setDate(cur.getDate() + step)
    if (isWeekday(cur)) remaining -= 1
  }
  return toYmd(cur)
}

const shiftWorkdays = computed(() => {
  return Math.max(5, Math.floor(windowWorkdays.value / 2))
})

const minStartYmd = computed(() => {
  let min: string | null = null
  if (project.value?.starts_on) min = project.value.starts_on
  for (const t of tasks.value) {
    if (!min || t.start_date < min) min = t.start_date
  }
  return min
})

const maxEndYmd = computed(() => {
  let max: string | null = null
  if (project.value?.ends_on) max = project.value.ends_on
  for (const t of tasks.value) {
    const d = t.effective_due_date
    if (d && (!max || d > max)) max = d
  }
  return max
})

const calendarDays = computed(() => {
  const start = viewStartYmd.value ?? minStartYmd.value
  if (!start) return [] as { ymd: string; label: string }[]

  const out: { ymd: string; label: string }[] = []
  let cur = parseDate(start)

  while (out.length < windowWorkdays.value) {
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

function canPrev(): boolean {
  const start = viewStartYmd.value ?? minStartYmd.value
  const min = minStartYmd.value
  if (!start || !min) return false
  return start > min
}

function canNext(): boolean {
  const max = maxEndYmd.value
  const last = calendarDays.value.length ? calendarDays.value[calendarDays.value.length - 1]!.ymd : null
  if (!max || !last) return false
  return last < max
}

function prevWindow() {
  const start = viewStartYmd.value ?? minStartYmd.value
  if (!start) return
  viewStartYmd.value = addWorkdays(start, -shiftWorkdays.value)

  const min = minStartYmd.value
  if (min && viewStartYmd.value < min) viewStartYmd.value = min
}

function nextWindow() {
  const start = viewStartYmd.value ?? minStartYmd.value
  if (!start) return
  viewStartYmd.value = addWorkdays(start, shiftWorkdays.value)
}

function gotoStart() {
  const min = minStartYmd.value
  if (!min) return
  viewStartYmd.value = nextWorkdayYmd(min)
}

function gotoToday() {
  if (!today.value) return
  viewStartYmd.value = nextWorkdayYmd(today.value)

  const min = minStartYmd.value
  if (min && viewStartYmd.value < min) viewStartYmd.value = nextWorkdayYmd(min)
}

function gotoEnd() {
  const max = maxEndYmd.value
  if (!max) return

  const last = nextWorkdayYmd(max)
  const start = addWorkdays(last, -(windowWorkdays.value - 1))
  viewStartYmd.value = nextWorkdayYmd(start)

  const min = minStartYmd.value
  if (min && viewStartYmd.value < min) viewStartYmd.value = nextWorkdayYmd(min)
}

function assigneeText(t: SheetTask): string {
  if (!t.assignees.length) return '—'
  return t.assignees.map((a) => `${a.name} (${a.capacity_hours_per_day}ч/д)`).join(', ')
}

function remainingText(t: SheetTask): string {
  const cap = t.capacity_hours_per_day
  if (!cap) return `${t.remaining_hours.toFixed(1)}ч` 
  return `${t.remaining_hours.toFixed(1)}ч / ${cap.toFixed(1)}ч/д`
}

function cellStyle(task: SheetTask, dayYmd: string) {
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

  return {
    background: bg,
  }
}

function cellClass(task: SheetTask, dayYmd: string) {
  return {
    'gantt-cell': true,
    'gantt-cell--control': Boolean(task.control_date && dayYmd === task.control_date),
    'gantt-cell--today': Boolean(today.value && dayYmd === today.value),
  }
}

const tasksByShipment = computed(() => {
  const map = new Map<number | null, SheetTask[]>()
  for (const t of tasks.value) {
    const k = t.shipment_id ?? null
    if (!map.has(k)) map.set(k, [])
    map.get(k)!.push(t)
  }

  for (const [k, arr] of map.entries()) {
    arr.sort((a, b) => (a.order - b.order) || (a.id - b.id))
    map.set(k, arr)
  }

  return map
})

const orderedShipmentIds = computed(() => {
  const ids = [...tasksByShipment.value.keys()]
  ids.sort((a, b) => {
    if (a == null && b == null) return 0
    if (a == null) return -1
    if (b == null) return 1
    return a - b
  })
  return ids
})

function shipmentTitle(shipmentId: number | null): string {
  if (shipmentId == null) return 'Без отгрузки'
  const s = shipments.value.find((x) => x.id === shipmentId)
  return s ? s.title : `Отгрузка #${shipmentId}`
}

function shipmentKey(shipmentId: number | null): string {
  return shipmentId == null ? 'null' : String(shipmentId)
}

function isShipmentOpen(shipmentId: number | null): boolean {
  const key = shipmentKey(shipmentId)
  if (shipmentOpen.value[key] == null) shipmentOpen.value[key] = true
  return Boolean(shipmentOpen.value[key])
}

function toggleShipment(shipmentId: number | null) {
  const key = shipmentKey(shipmentId)
  shipmentOpen.value[key] = !isShipmentOpen(shipmentId)
}

function shipmentTasksCount(shipmentId: number | null): number {
  return tasksByShipment.value.get(shipmentId ?? null)?.length ?? 0
}

function shipmentRemainingHours(shipmentId: number | null): number {
  const arr = tasksByShipment.value.get(shipmentId ?? null) ?? []
  return arr.reduce((sum, t) => sum + (t.remaining_hours ?? 0), 0)
}

async function fetchAll() {
  loading.value = true
  error.value = null

  try {
    const [sheetRes, burndownRes] = await Promise.all([
      api.get<GanttSheetResponse>(`/api/projects/${projectId.value}/gantt-sheet`),
      api.get<BurndownResponse>(`/api/projects/${projectId.value}/burndown`),
    ])

    project.value = sheetRes.data.project
    today.value = sheetRes.data.today
    shipments.value = sheetRes.data.shipments
    tasks.value = sheetRes.data.tasks

    burndownTotal.value = burndownRes.data.total_estimate_hours
    burndownPoints.value = burndownRes.data.points

    if (!viewStartYmd.value && minStartYmd.value) {
      viewStartYmd.value = minStartYmd.value
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить лист Ганта'
  } finally {
    loading.value = false
  }
}

watch(
  () => route.params.projectId,
  () => fetchAll(),
)

onMounted(fetchAll)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Гант</h1>
        <div class="sheet-subtitle">Проект №{{ projectId }}<span v-if="project"> — {{ project.name }}</span></div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments`">← Отгрузки</RouterLink>
        <button type="button" class="sheet-btn" @click="exportExcel">Экспорт в Excel</button>
      </div>
    </div>

    <div class="sheet-body">
      <div class="sheet-card" style="margin-bottom: 12px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
          <div style="font-weight: 700;">Burndown</div>
          <button type="button" class="sheet-btn" @click="burndownOpen = !burndownOpen">
            {{ burndownOpen ? 'Свернуть' : 'Развернуть' }}
          </button>
        </div>
        <div v-if="burndownOpen" style="margin-top: 10px;">
          <BurndownChart v-if="burndownPoints.length" :points="burndownPoints" :total-estimate-hours="burndownTotal" />
          <div v-else class="sheet-muted">Нет данных для burndown</div>
        </div>
      </div>

      <div class="sheet-card" style="margin-bottom: 12px;">
        <div class="sheet-grid-4" style="align-items: end;">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Окно (раб. дней)</div>
            <input v-model.number="windowWorkdays" class="sheet-input" type="number" min="5" max="90" step="1" />
          </label>

          <div></div>
          <div></div>

          <div style="display: inline-flex; gap: 8px; justify-content: flex-end;">
            <button type="button" class="sheet-btn" :disabled="!minStartYmd" @click="gotoStart">К началу</button>
            <button type="button" class="sheet-btn" :disabled="!today" @click="gotoToday">К сегодня</button>
            <button type="button" class="sheet-btn" :disabled="!maxEndYmd" @click="gotoEnd">К концу</button>
            <button type="button" class="sheet-btn" :disabled="!canPrev()" @click="prevWindow">← Назад</button>
            <button type="button" class="sheet-btn" :disabled="!canNext()" @click="nextWindow">Вперёд →</button>
          </div>
        </div>

        <div class="sheet-muted" style="font-size: 12px; margin-top: 8px;">
          Сегодня — синяя обводка; контрольная точка — толстая рамка
        </div>
      </div>

      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table gantt-sheet-table" style="min-width: 1200px;">
          <thead>
            <tr>
              <th class="sheet-sticky-col" style="min-width: 360px;">Задача</th>
              <th class="sheet-sticky-col" style="left: 360px; min-width: 260px;">Исполнитель</th>
              <th class="sheet-sticky-col" style="left: 620px; min-width: 120px;">Начало</th>
              <th class="sheet-sticky-col" style="left: 740px; min-width: 140px;">Осталось</th>
              <th v-for="d in calendarDays" :key="d.ymd" class="gantt-day-head">
                {{ d.label }}
              </th>
            </tr>
          </thead>

          <tbody v-for="s in orderedShipmentIds" :key="`ship-${shipmentKey(s)}`">
            <tr>
              <td class="sheet-sticky-col" colspan="4" style="font-weight: 700; background: var(--sheet-header); padding: 0;">
                <button type="button" class="gantt-group-head" @click="toggleShipment(s)">
                  <span class="gantt-group-head__chev">{{ isShipmentOpen(s) ? '▾' : '▸' }}</span>
                  <span class="gantt-group-head__title">{{ shipmentTitle(s) }}</span>
                  <span class="gantt-group-head__meta">
                    {{ shipmentTasksCount(s) }} задач · {{ shipmentRemainingHours(s).toFixed(1) }}ч
                  </span>
                </button>
              </td>
              <td v-for="d in calendarDays" :key="`${shipmentKey(s)}-${d.ymd}`" class="gantt-day-td" style="background: var(--sheet-header);"></td>
            </tr>

            <tr v-for="t in tasksByShipment.get(s)" v-show="isShipmentOpen(s)" :key="t.id">
              <td class="sheet-sticky-col">
                <div style="display: grid; gap: 2px;">
                  <div style="padding-left: 14px;">{{ t.title }}</div>
                  <div class="sheet-muted" style="font-size: 12px; padding-left: 14px;">{{ formatStage(t.stage) }} · #{{ t.id }}</div>
                </div>
              </td>
              <td class="sheet-sticky-col" style="left: 360px;">
                <div class="sheet-muted" style="font-size: 13px;">{{ assigneeText(t) }}</div>
              </td>
              <td class="sheet-sticky-col" style="left: 620px;">
                {{ formatDate(t.start_date) }}
              </td>
              <td class="sheet-sticky-col" style="left: 740px;">
                {{ remainingText(t) }}
              </td>
              <td v-for="d in calendarDays" :key="d.ymd" class="gantt-day-td">
                <div :class="cellClass(t, d.ymd)" :style="cellStyle(t, d.ymd)"></div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

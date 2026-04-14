<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { formatDate, formatMinutes, formatStage } from '../lib/format'

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

type Task = {
  id: number
  project_id: number
  shipment_id: number
  title: string
  acceptance_criteria: string | null
  estimate_hours: number
  start_date: string
  due_date: string | null
  stage: string
  order: number
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

type TaskAssignment = {
  id: number
  task_id: number
  user_id: number
  capacity_hours_per_day: number
  created_at: string
  updated_at: string
}

type TaskWorkLog = {
  id: number
  task_id: number
  user_id: number
  work_date: string
  minutes: number
  comment: string | null
  created_at: string
  updated_at: string
}

type Note = {
  id: number
  project_id: number | null
  task_id: number | null
  title: string
  body: string | null
  is_pinned: boolean
  created_at: string
  updated_at: string
}

const route = useRoute()
const projectId = computed(() => Number(route.params.projectId))
const shipmentId = computed(() => Number(route.params.shipmentId))

const shipment = ref<Shipment | null>(null)
const tasks = ref<Task[]>([])

const users = ref<User[]>([])

const loading = ref(false)
const error = ref<string | null>(null)

const newTitle = ref('')
const newEstimateHours = ref<number>(8)
const newStartDate = ref<string>(new Date().toISOString().slice(0, 10))
const newDueDate = ref<string | null>(null)
const creatingTask = ref(false)

const detailsOpen = ref<Record<number, boolean>>({})
const detailsLoading = ref<Record<number, boolean>>({})
const assignmentsByTask = ref<Record<number, TaskAssignment[]>>({})
const logsByTask = ref<Record<number, TaskWorkLog[]>>({})
const notesByTask = ref<Record<number, Note[]>>({})

const assignUserId = ref<Record<number, number | null>>({})
const assignCapacity = ref<Record<number, number>>({})
const logUserId = ref<Record<number, number | null>>({})
const logMinutes = ref<Record<number, number>>({})
const noteText = ref<Record<number, string>>({})

const todayYmd = new Date().toISOString().slice(0, 10)

async function fetchAll() {
  loading.value = true
  error.value = null

  try {
    const [shipmentRes, tasksRes, usersRes] = await Promise.all([
      api.get<Shipment>(`/api/shipments/${shipmentId.value}`),
      api.get<Paginated<Task>>('/api/tasks', {
        params: {
          shipment_id: shipmentId.value,
          per_page: 100,
        },
      }),
      api.get<Paginated<User>>('/api/users', {
        params: {
          per_page: 200,
        },
      }),
    ])

    shipment.value = shipmentRes.data
    tasks.value = tasksRes.data.data
    users.value = usersRes.data.data
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить отгрузку'
  } finally {
    loading.value = false
  }
}

async function loadTaskDetails(taskId: number) {
  detailsLoading.value[taskId] = true
  error.value = null

  try {
    const [assignmentsRes, logsRes] = await Promise.all([
      api.get<Paginated<TaskAssignment>>('/api/task-assignments', {
        params: {
          task_id: taskId,
          per_page: 200,
        },
      }),
      api.get<Paginated<TaskWorkLog>>('/api/task-work-logs', {
        params: {
          task_id: taskId,
          per_page: 200,
        },
      }),
    ])

    assignmentsByTask.value[taskId] = assignmentsRes.data.data
    logsByTask.value[taskId] = logsRes.data.data

    const notesRes = await api.get<Paginated<Note>>('/api/notes', {
      params: {
        task_id: taskId,
        per_page: 200,
      },
    })
    notesByTask.value[taskId] = notesRes.data.data

    if (assignUserId.value[taskId] == null && users.value.length) {
      assignUserId.value[taskId] = users.value[0]!.id
    }
    if (logUserId.value[taskId] == null && users.value.length) {
      logUserId.value[taskId] = users.value[0]!.id
    }
    if (assignCapacity.value[taskId] == null) {
      assignCapacity.value[taskId] = 4
    }
    if (logMinutes.value[taskId] == null) {
      logMinutes.value[taskId] = 60
    }
    if (noteText.value[taskId] == null) {
      noteText.value[taskId] = ''
    }
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить данные задачи'
  } finally {
    detailsLoading.value[taskId] = false
  }
}

async function createNote(taskId: number) {
  const text = (noteText.value[taskId] ?? '').trim()
  if (!text) return

  error.value = null

  try {
    await api.post('/api/notes', {
      task_id: taskId,
      title: text,
      body: null,
      is_pinned: false,
    })
    noteText.value[taskId] = ''
    await loadTaskDetails(taskId)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать заметку'
  }
}

async function createAssignment(taskId: number) {
  const userId = assignUserId.value[taskId]
  const capacity = assignCapacity.value[taskId]
  if (!userId) return

  error.value = null

  try {
    await api.post('/api/task-assignments', {
      task_id: taskId,
      user_id: userId,
      capacity_hours_per_day: capacity,
    })
    await loadTaskDetails(taskId)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать назначение'
  }
}

async function createWorkLog(taskId: number) {
  const userId = logUserId.value[taskId]
  const minutes = logMinutes.value[taskId]
  if (!userId) return

  error.value = null

  try {
    await api.post('/api/task-work-logs', {
      task_id: taskId,
      user_id: userId,
      work_date: todayYmd,
      minutes,
    })
    await loadTaskDetails(taskId)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать отметку'
  }
}

function userLabel(userId: number): string {
  const u = users.value.find((x) => x.id === userId)
  return u ? `${u.name}` : `#${userId}`
}

function toggleTaskDetails(taskId: number) {
  detailsOpen.value[taskId] = !detailsOpen.value[taskId]

  if (
    detailsOpen.value[taskId] &&
    !assignmentsByTask.value[taskId] &&
    !logsByTask.value[taskId] &&
    !notesByTask.value[taskId]
  ) {
    loadTaskDetails(taskId)
  }
}

async function createTask() {
  if (!newTitle.value.trim()) return

  creatingTask.value = true
  error.value = null

  try {
    await api.post('/api/tasks', {
      project_id: projectId.value,
      shipment_id: shipmentId.value,
      title: newTitle.value,
      estimate_hours: newEstimateHours.value,
      start_date: newStartDate.value,
      due_date: newDueDate.value,
      stage: 'planned',
      order: tasks.value.length + 1,
    })

    newTitle.value = ''
    await fetchAll()
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать задачу'
  } finally {
    creatingTask.value = false
  }
}

async function removeTask(id: number) {
  if (!confirm('Удалить задачу?')) return

  try {
    await api.delete(`/api/tasks/${id}`)
    await fetchAll()
  } catch (e: any) {
    alert(e?.response?.data?.message ?? e?.message ?? 'Не удалось удалить задачу')
  }
}

function downloadExport() {
  window.location.href = `${api.defaults.baseURL}/api/shipments/${shipmentId.value}/export`
}

watch(
  () => [route.params.projectId, route.params.shipmentId],
  () => {
    fetchAll()
  },
)

onMounted(fetchAll)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Отгрузка</h1>
        <div class="sheet-subtitle">№{{ shipmentId }}<span v-if="shipment"> — {{ shipment.title }}</span></div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments`">← Назад</RouterLink>
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments/${shipmentId}/gantt`">Гант</RouterLink>
        <button type="button" class="sheet-btn sheet-btn-primary" @click="downloadExport">Экспорт XLSX</button>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else style="display: grid; gap: 12px;">
        <section class="sheet-card">
          <h2 class="sheet-section-title">Создать задачу</h2>

          <form @submit.prevent="createTask" class="sheet-form">
            <div class="sheet-grid-4" style="align-items: end; grid-template-columns: 2fr 1fr 1fr 1fr;">
              <label>
                <div class="sheet-muted" style="font-size: 12px;">Название</div>
                <input v-model="newTitle" required class="sheet-input" />
              </label>

              <label>
                <div class="sheet-muted" style="font-size: 12px;">Оценка (ч)</div>
                <input
                  v-model.number="newEstimateHours"
                  type="number"
                  min="0.25"
                  step="0.25"
                  class="sheet-input"
                />
              </label>

              <label>
                <div class="sheet-muted" style="font-size: 12px;">Старт</div>
                <input v-model="newStartDate" type="date" class="sheet-input" />
              </label>

              <label>
                <div class="sheet-muted" style="font-size: 12px;">Дедлайн (опционально)</div>
                <input v-model="newDueDate" type="date" class="sheet-input" />
              </label>
            </div>

            <div>
              <button type="submit" :disabled="creatingTask" class="sheet-btn sheet-btn-primary">
                {{ creatingTask ? 'Сохранение...' : 'Создать задачу' }}
              </button>
            </div>
          </form>
        </section>

        <section>
          <h2 class="sheet-section-title" style="margin-bottom: 0;">Задачи</h2>

          <div class="sheet-table-wrap" style="margin-top: 10px;">
            <table class="sheet-table">
              <thead>
                <tr>
                  <th>№</th>
                  <th>Название</th>
                  <th>Старт</th>
                  <th>Дедлайн</th>
                  <th>Стадия</th>
                  <th class="right">Действия</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="t in tasks" :key="t.id">
                  <td>{{ t.order }}</td>
                  <td>{{ t.title }}</td>
                  <td>{{ formatDate(t.start_date) }}</td>
                  <td>{{ formatDate(t.due_date) }}</td>
                  <td>{{ formatStage(t.stage) }}</td>
                  <td class="right">
                    <a class="sheet-link" href="#" @click.prevent="toggleTaskDetails(t.id)">
                      {{ detailsOpen[t.id] ? 'Скрыть' : 'Управлять' }}
                    </a>
                    <span> | </span>
                    <a class="sheet-link" href="#" @click.prevent="removeTask(t.id)">Удалить</a>
                  </td>
                </tr>

                <tr v-for="t in tasks" v-show="detailsOpen[t.id]" :key="`details-${t.id}`">
                  <td colspan="6" style="padding: 12px; background: #fafafa;">
                    <div style="display: grid; gap: 12px;">
                      <div v-if="detailsLoading[t.id]">Загрузка данных задачи...</div>

                      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="sheet-card">
                          <div style="font-weight: 700; margin-bottom: 8px;">Назначения</div>

                          <div v-if="assignmentsByTask[t.id]?.length" style="display: grid; gap: 6px; margin-bottom: 10px;">
                            <div v-for="a in assignmentsByTask[t.id]" :key="a.id" style="display: flex; justify-content: space-between; gap: 12px;">
                              <div>{{ userLabel(a.user_id) }}</div>
                              <div style="color: #6b7280;">{{ a.capacity_hours_per_day }} ч/день</div>
                            </div>
                          </div>
                          <div v-else style="color: #6b7280; margin-bottom: 10px;">Пока нет назначений</div>

                          <form @submit.prevent="createAssignment(t.id)" style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                            <label style="display: grid; gap: 6px;">
                              <div class="sheet-muted" style="font-size: 12px;">Исполнитель</div>
                              <select v-model.number="assignUserId[t.id]" class="sheet-select">
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                              </select>
                            </label>

                            <label style="display: grid; gap: 6px;">
                              <div class="sheet-muted" style="font-size: 12px;">ч/день</div>
                              <input
                                v-model.number="assignCapacity[t.id]"
                                type="number"
                                min="0.25"
                                step="0.25"
                                class="sheet-input"
                                style="width: 120px;"
                              />
                            </label>

                            <button type="submit" class="sheet-btn sheet-btn-primary">
                              Назначить
                            </button>
                          </form>
                        </div>

                        <div class="sheet-card">
                          <div style="font-weight: 700; margin-bottom: 8px;">Отметки (сегодня: {{ formatDate(todayYmd) }})</div>

                          <div v-if="logsByTask[t.id]?.length" style="display: grid; gap: 6px; margin-bottom: 10px;">
                            <div v-for="l in logsByTask[t.id]" :key="l.id" style="display: flex; justify-content: space-between; gap: 12px;">
                              <div>{{ userLabel(l.user_id) }}</div>
                              <div style="color: #6b7280;">{{ formatMinutes(l.minutes) }}</div>
                            </div>
                          </div>
                          <div v-else style="color: #6b7280; margin-bottom: 10px;">Пока нет отметок</div>

                          <form @submit.prevent="createWorkLog(t.id)" style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                            <label style="display: grid; gap: 6px;">
                              <div class="sheet-muted" style="font-size: 12px;">Исполнитель</div>
                              <select v-model.number="logUserId[t.id]" class="sheet-select">
                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                              </select>
                            </label>

                            <label style="display: grid; gap: 6px;">
                              <div class="sheet-muted" style="font-size: 12px;">минут</div>
                              <input
                                v-model.number="logMinutes[t.id]"
                                type="number"
                                min="1"
                                max="1440"
                                step="1"
                                class="sheet-input"
                                style="width: 120px;"
                              />
                            </label>

                            <button type="submit" class="sheet-btn sheet-btn-primary">
                              Отметить
                            </button>
                          </form>
                        </div>
                      </div>

                      <div class="sheet-card">
                        <div style="font-weight: 700; margin-bottom: 8px;">Заметки</div>

                        <div v-if="notesByTask[t.id]?.length" style="display: grid; gap: 6px; margin-bottom: 10px;">
                          <div v-for="n in notesByTask[t.id]" :key="n.id" style="display: grid; gap: 2px;">
                            <div>{{ n.title }}</div>
                            <div class="sheet-muted" style="font-size: 12px;">#{{ n.id }}</div>
                          </div>
                        </div>
                        <div v-else style="color: #6b7280; margin-bottom: 10px;">Пока нет заметок</div>

                        <form @submit.prevent="createNote(t.id)" style="display: flex; gap: 8px; align-items: end; flex-wrap: wrap;">
                          <label style="display: grid; gap: 6px; flex: 1; min-width: 260px;">
                            <div class="sheet-muted" style="font-size: 12px;">Новая заметка</div>
                            <input v-model="noteText[t.id]" class="sheet-input" placeholder="Например: Клиент добавил фичу" />
                          </label>

                          <button type="submit" class="sheet-btn sheet-btn-primary">
                            Добавить
                          </button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

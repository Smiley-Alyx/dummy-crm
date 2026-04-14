<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'

type Note = {
  id: number
  project_id: number | null
  task_id: number | null
  title: string
  body: string | null
  is_pinned: boolean
  project?: { id: number; name: string } | null
  task?: { id: number; shipment_id: number | null; title: string; shipment?: { id: number; title: string } | null } | null
  created_at: string
  updated_at: string
}

type Project = {
  id: number
  name: string
}

type Paginated<T> = {
  data: T[]
}

const notes = ref<Note[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const projectId = ref<string>('')
const projects = ref<Project[]>([])

async function loadProjects() {
  try {
    const res = await api.get<Paginated<Project>>('/api/projects', { params: { per_page: 200 } })
    projects.value = res.data.data
  } catch {
    // ignore
  }
}

async function fetchNotes() {
  loading.value = true
  error.value = null

  try {
    const params: Record<string, any> = {}
    if (projectId.value) params.project_id = Number(projectId.value)

    const res = await api.get<Paginated<Note>>('/api/notes', { params })
    notes.value = res.data.data
  } catch (e: any) {
    error.value = e?.message ?? 'Не удалось загрузить заметки'
  } finally {
    loading.value = false
  }
}

async function removeNote(id: number) {
  if (!confirm('Удалить заметку?')) return

  try {
    await api.delete(`/api/notes/${id}`)
    await fetchNotes()
  } catch (e: any) {
    alert(e?.message ?? 'Не удалось удалить заметку')
  }
}

onMounted(async () => {
  await loadProjects()
  await fetchNotes()
})
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Заметки</h1>
        <div class="sheet-subtitle">Личные заметки и подсказки по проектам</div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/notes/new">Создать</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div class="sheet-form" style="margin-bottom: 12px;">
        <div class="sheet-grid-4" style="align-items: end; grid-template-columns: 1.2fr 1fr 1fr auto;">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Проект</div>
            <select v-model="projectId" class="sheet-select">
              <option value="">Все</option>
              <option v-for="p in projects" :key="p.id" :value="String(p.id)">{{ p.name }} (#{{ p.id }})</option>
            </select>
          </label>

          <div></div>
          <div></div>

          <button type="button" class="sheet-btn sheet-btn-primary" @click="fetchNotes">Применить</button>
        </div>
      </div>

      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Проект</th>
              <th>Отгрузка</th>
              <th>Задача</th>
              <th>Заголовок</th>
              <th>Закреплено</th>
              <th class="right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="n in notes" :key="n.id">
              <td>{{ n.project?.name ?? (n.project_id ?? '—') }}</td>
              <td>{{ n.task?.shipment?.title ?? '—' }}</td>
              <td>{{ n.task?.title ?? '—' }}</td>
              <td>{{ n.title }}</td>
              <td>{{ n.is_pinned ? 'да' : 'нет' }}</td>
              <td class="right">
                <RouterLink class="sheet-link" :to="`/notes/${n.id}/edit`">Изменить</RouterLink>
                <span> | </span>
                <a class="sheet-link" href="#" @click.prevent="removeNote(n.id)">Удалить</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

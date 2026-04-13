<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'
import { formatDate } from '../lib/format'

type Project = {
  id: number
  name: string
  description: string | null
  status: string
  starts_on: string | null
  ends_on: string | null
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

const projects = ref<Project[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function fetchProjects() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Paginated<Project>>('/api/projects')
    projects.value = res.data.data
  } catch (e: any) {
    error.value = e?.message ?? 'Не удалось загрузить проекты'
  } finally {
    loading.value = false
  }
}

async function removeProject(id: number) {
  if (!confirm('Удалить проект?')) return

  try {
    await api.delete(`/api/projects/${id}`)
    await fetchProjects()
  } catch (e: any) {
    alert(e?.message ?? 'Не удалось удалить проект')
  }
}

onMounted(fetchProjects)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Проекты</h1>
        <div class="sheet-subtitle">Список проектов</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/projects/new">Создать</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Статус</th>
              <th>Даты</th>
              <th class="right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in projects" :key="p.id">
              <td>{{ p.name }}</td>
              <td>{{ p.status }}</td>
              <td>
                <span>{{ formatDate(p.starts_on) }}</span>
                <span> → </span>
                <span>{{ formatDate(p.ends_on) }}</span>
              </td>
              <td class="right">
                <RouterLink class="sheet-link" :to="`/projects/${p.id}/edit`">Изменить</RouterLink>
                <span> | </span>
                <RouterLink class="sheet-link" :to="`/projects/${p.id}/shipments`">Отгрузки</RouterLink>
                <span> | </span>
                <a class="sheet-link" href="#" @click.prevent="removeProject(p.id)">Удалить</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

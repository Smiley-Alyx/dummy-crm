<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'

type Note = {
  id: number
  project_id: number | null
  title: string
  body: string | null
  is_pinned: boolean
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

const notes = ref<Note[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function fetchNotes() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Paginated<Note>>('/api/notes')
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

onMounted(fetchNotes)
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
      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Заголовок</th>
              <th>Закреплено</th>
              <th class="right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="n in notes" :key="n.id">
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

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
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Заметки</h1>
      <RouterLink to="/notes/new">Создать</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Загрузка...</div>
    <div v-else-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <table v-else style="width: 100%; margin-top: 16px; border-collapse: collapse;">
      <thead>
        <tr>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Заголовок</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Закреплено</th>
          <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="n in notes" :key="n.id">
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ n.title }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ n.is_pinned ? 'да' : 'нет' }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">
            <RouterLink :to="`/notes/${n.id}/edit`">Изменить</RouterLink>
            <span> | </span>
            <a href="#" @click.prevent="removeNote(n.id)">Удалить</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

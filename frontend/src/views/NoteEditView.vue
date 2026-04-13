<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

type Note = {
  id: number
  title: string
  body: string | null
  is_pinned: boolean
}

const route = useRoute()
const router = useRouter()

const id = Number(route.params.id)

const title = ref('')
const body = ref<string>('')
const is_pinned = ref(false)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Note>(`/api/notes/${id}`)
    title.value = res.data.title
    body.value = res.data.body ?? ''
    is_pinned.value = res.data.is_pinned
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить заметку'
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.put(`/api/notes/${id}`, {
      title: title.value,
      body: body.value || null,
      is_pinned: is_pinned.value,
    })

    await router.push('/notes')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось обновить заметку'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Редактировать заметку</h1>
        <div class="sheet-subtitle">Заметка №{{ id }}</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/notes">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="loading">Загрузка...</div>
      <div v-else>
        <form @submit.prevent="submit" class="sheet-form">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Заголовок</div>
            <input v-model="title" required class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Текст</div>
            <textarea v-model="body" rows="6" class="sheet-textarea"></textarea>
          </label>

          <label style="display: flex; gap: 8px; align-items: center;">
            <input v-model="is_pinned" type="checkbox" />
            <span>Закрепить</span>
          </label>

          <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

          <div>
            <button type="submit" class="sheet-btn sheet-btn-primary" :disabled="saving">
              {{ saving ? 'Сохранение...' : 'Сохранить' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

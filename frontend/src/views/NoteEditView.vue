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
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Редактировать заметку</h1>
      <RouterLink to="/notes">Назад</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Загрузка...</div>
    <div v-else>
      <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
        <label>
          Заголовок
          <input v-model="title" required style="display: block; width: 100%;" />
        </label>

        <label>
          Текст
          <textarea v-model="body" rows="6" style="display: block; width: 100%;"></textarea>
        </label>

        <label style="display: flex; gap: 8px; align-items: center;">
          <input v-model="is_pinned" type="checkbox" />
          Закрепить
        </label>

        <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

        <button type="submit" :disabled="saving">{{ saving ? 'Сохранение...' : 'Сохранить' }}</button>
      </form>
    </div>
  </div>
</template>

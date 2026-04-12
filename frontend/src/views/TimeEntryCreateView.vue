<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

const router = useRouter()

const project_id = ref<string>('')
const entry_date = ref<string>('')
const minutes = ref<string>('')
const note = ref<string>('')

const saving = ref(false)
const error = ref<string | null>(null)

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.post('/api/time-entries', {
      project_id: Number(project_id.value),
      entry_date: entry_date.value,
      minutes: Number(minutes.value),
      note: note.value || null,
    })

    await router.push('/time')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать запись'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Добавить запись</h1>
      <RouterLink to="/time">Назад</RouterLink>
    </div>

    <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
      <label>
        Проект ID
        <input v-model="project_id" required inputmode="numeric" style="display: block; width: 100%;" />
      </label>

      <label>
        Дата
        <input v-model="entry_date" required type="date" style="display: block; width: 100%;" />
      </label>

      <label>
        Минут
        <input v-model="minutes" required inputmode="numeric" placeholder="например, 90" style="display: block; width: 100%;" />
      </label>

      <label>
        Комментарий
        <input v-model="note" style="display: block; width: 100%;" />
      </label>

      <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

      <button type="submit" :disabled="saving">{{ saving ? 'Сохранение...' : 'Создать' }}</button>
    </form>
  </div>
</template>

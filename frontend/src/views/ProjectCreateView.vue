<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

const router = useRouter()

const name = ref('')
const description = ref<string>('')
const status = ref('active')
const starts_on = ref<string>('')
const ends_on = ref<string>('')
const saving = ref(false)
const error = ref<string | null>(null)

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.post('/api/projects', {
      name: name.value,
      description: description.value || null,
      status: status.value || null,
      starts_on: starts_on.value || null,
      ends_on: ends_on.value || null,
    })

    await router.push('/projects')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать проект'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Создать проект</h1>
      <RouterLink to="/projects">Назад</RouterLink>
    </div>

    <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
      <label>
        Название
        <input v-model="name" required style="display: block; width: 100%;" />
      </label>

      <label>
        Описание
        <textarea v-model="description" rows="4" style="display: block; width: 100%;"></textarea>
      </label>

      <label>
        Статус
        <input v-model="status" style="display: block; width: 100%;" />
      </label>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <label>
          Дата старта
          <input v-model="starts_on" type="date" style="display: block; width: 100%;" />
        </label>

        <label>
          Дата завершения
          <input v-model="ends_on" type="date" style="display: block; width: 100%;" />
        </label>
      </div>

      <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

      <button type="submit" :disabled="saving">{{ saving ? 'Сохранение...' : 'Создать' }}</button>
    </form>
  </div>
</template>

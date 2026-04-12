<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

type Project = {
  id: number
  name: string
  description: string | null
  status: string
  starts_on: string | null
  ends_on: string | null
}

const route = useRoute()
const router = useRouter()

const id = Number(route.params.id)

const name = ref('')
const description = ref<string>('')
const status = ref('active')
const starts_on = ref<string>('')
const ends_on = ref<string>('')
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Project>(`/api/projects/${id}`)
    name.value = res.data.name
    description.value = res.data.description ?? ''
    status.value = res.data.status
    starts_on.value = res.data.starts_on ?? ''
    ends_on.value = res.data.ends_on ?? ''
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить проект'
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.put(`/api/projects/${id}`, {
      name: name.value,
      description: description.value || null,
      status: status.value || null,
      starts_on: starts_on.value || null,
      ends_on: ends_on.value || null,
    })

    await router.push('/projects')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось обновить проект'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Редактировать проект</h1>
      <RouterLink to="/projects">Назад</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Загрузка...</div>
    <div v-else>
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

        <button type="submit" :disabled="saving">{{ saving ? 'Сохранение...' : 'Сохранить' }}</button>
      </form>
    </div>
  </div>
</template>

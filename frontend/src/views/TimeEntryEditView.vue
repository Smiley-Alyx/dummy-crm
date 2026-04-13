<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

type TimeEntry = {
  id: number
  project_id: number
  entry_date: string
  minutes: number
  note: string | null
}

const route = useRoute()
const router = useRouter()

const id = Number(route.params.id)

const project_id = ref<string>('')
const entry_date = ref<string>('')
const minutes = ref<string>('')
const note = ref<string>('')

const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function load() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<TimeEntry>(`/api/time-entries/${id}`)
    project_id.value = String(res.data.project_id)
    entry_date.value = res.data.entry_date
    minutes.value = String(res.data.minutes)
    note.value = res.data.note ?? ''
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось загрузить запись'
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.put(`/api/time-entries/${id}`, {
      project_id: Number(project_id.value),
      entry_date: entry_date.value,
      minutes: Number(minutes.value),
      note: note.value || null,
    })

    await router.push('/time')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось обновить запись'
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
        <h1>Редактировать запись</h1>
        <div class="sheet-subtitle">Запись №{{ id }}</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/time">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="loading">Загрузка...</div>
      <div v-else>
        <form @submit.prevent="submit" class="sheet-form">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Проект ID</div>
            <input v-model="project_id" required inputmode="numeric" class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Дата</div>
            <input v-model="entry_date" required type="date" class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Минут</div>
            <input v-model="minutes" required inputmode="numeric" class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Комментарий</div>
            <input v-model="note" class="sheet-input" />
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

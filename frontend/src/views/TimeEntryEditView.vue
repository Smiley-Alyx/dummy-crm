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
    error.value = e?.response?.data?.message ?? e?.message ?? 'Failed to load entry'
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
    error.value = e?.response?.data?.message ?? e?.message ?? 'Failed to update entry'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Edit time entry</h1>
      <RouterLink to="/time">Back</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Loading...</div>
    <div v-else>
      <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
        <label>
          Project ID
          <input v-model="project_id" required inputmode="numeric" style="display: block; width: 100%;" />
        </label>

        <label>
          Date
          <input v-model="entry_date" required type="date" style="display: block; width: 100%;" />
        </label>

        <label>
          Minutes
          <input v-model="minutes" required inputmode="numeric" style="display: block; width: 100%;" />
        </label>

        <label>
          Note
          <input v-model="note" style="display: block; width: 100%;" />
        </label>

        <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

        <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
      </form>
    </div>
  </div>
</template>

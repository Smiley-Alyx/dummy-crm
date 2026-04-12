<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'

type TimeEntry = {
  id: number
  project_id: number
  entry_date: string
  minutes: number
  note: string | null
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

type SummaryByDayRow = {
  entry_date: string
  minutes: number
}

type SummaryResponse = {
  total_minutes: number
  by_day: SummaryByDayRow[]
}

const timeEntries = ref<TimeEntry[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

const projectId = ref<string>('')
const from = ref<string>('')
const to = ref<string>('')

const summary = ref<SummaryResponse | null>(null)

const totalHours = computed(() => {
  const total = summary.value?.total_minutes ?? 0
  return (total / 60).toFixed(2)
})

async function fetchAll() {
  loading.value = true
  error.value = null

  try {
    const params: Record<string, any> = {}

    if (projectId.value) params.project_id = Number(projectId.value)
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value

    const [entriesRes, summaryRes] = await Promise.all([
      api.get<Paginated<TimeEntry>>('/api/time-entries', { params }),
      api.get<SummaryResponse>('/api/time-entries/summary', { params }),
    ])

    timeEntries.value = entriesRes.data.data
    summary.value = summaryRes.data
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to load time entries'
  } finally {
    loading.value = false
  }
}

async function removeEntry(id: number) {
  if (!confirm('Delete entry?')) return

  try {
    await api.delete(`/api/time-entries/${id}`)
    await fetchAll()
  } catch (e: any) {
    alert(e?.message ?? 'Failed to delete entry')
  }
}

onMounted(fetchAll)
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Time tracking</h1>
      <RouterLink to="/time/new">Add</RouterLink>
    </div>

    <div style="margin-top: 16px; display: grid; gap: 12px;">
      <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: end;">
        <label>
          Project ID
          <input v-model="projectId" inputmode="numeric" placeholder="e.g. 1" style="display: block; width: 100%;" />
        </label>

        <label>
          From
          <input v-model="from" type="date" style="display: block; width: 100%;" />
        </label>

        <label>
          To
          <input v-model="to" type="date" style="display: block; width: 100%;" />
        </label>

        <button type="button" @click="fetchAll">Apply</button>
      </div>

      <div v-if="summary" style="padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px;">
        <div><strong>Total:</strong> {{ summary.total_minutes }} min ({{ totalHours }} h)</div>
      </div>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Loading...</div>
    <div v-else-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <table v-else style="width: 100%; margin-top: 16px; border-collapse: collapse;">
      <thead>
        <tr>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Date</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Project</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Minutes</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Note</th>
          <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="t in timeEntries" :key="t.id">
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ t.entry_date }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ t.project_id }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ t.minutes }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ t.note ?? '—' }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">
            <RouterLink :to="`/time/${t.id}/edit`">Edit</RouterLink>
            <span> | </span>
            <a href="#" @click.prevent="removeEntry(t.id)">Delete</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

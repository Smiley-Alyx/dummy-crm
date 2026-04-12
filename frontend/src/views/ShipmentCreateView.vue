<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../lib/api'

const route = useRoute()
const router = useRouter()

const projectId = Number(route.params.projectId)

const title = ref('')
const description = ref<string | null>(null)
const plannedStartDate = ref<string | null>(null)
const plannedDueDate = ref<string | null>(null)

const saving = ref(false)
const error = ref<string | null>(null)

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.post('/api/shipments', {
      project_id: projectId,
      title: title.value,
      description: description.value,
      planned_start_date: plannedStartDate.value,
      planned_due_date: plannedDueDate.value,
    })

    await router.push(`/projects/${projectId}/shipments`)
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Failed to create shipment'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Create shipment</h1>
      <RouterLink :to="`/projects/${projectId}/shipments`">Back</RouterLink>
    </div>

    <div v-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
      <label style="display: grid; gap: 6px;">
        <span>Title</span>
        <input v-model="title" required style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px;" />
      </label>

      <label style="display: grid; gap: 6px;">
        <span>Description</span>
        <textarea
          v-model="description"
          rows="4"
          style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px;"
        ></textarea>
      </label>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
        <label style="display: grid; gap: 6px;">
          <span>Planned start</span>
          <input v-model="plannedStartDate" type="date" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px;" />
        </label>

        <label style="display: grid; gap: 6px;">
          <span>Planned due</span>
          <input v-model="plannedDueDate" type="date" style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 6px;" />
        </label>
      </div>

      <button
        type="submit"
        :disabled="saving"
        style="padding: 10px 12px; border: 1px solid #111827; border-radius: 8px; background: #111827; color: #fff;"
      >
        {{ saving ? 'Saving...' : 'Create' }}
      </button>
    </form>
  </div>
</template>

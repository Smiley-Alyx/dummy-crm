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
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать отгрузку'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Создать отгрузку</h1>
        <div class="sheet-subtitle">Проект №{{ projectId }}</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments`">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

      <form @submit.prevent="submit" class="sheet-form" style="margin-top: 10px;">
        <label>
          <div class="sheet-muted" style="font-size: 12px;">Название</div>
          <input v-model="title" required class="sheet-input" />
        </label>

        <label>
          <div class="sheet-muted" style="font-size: 12px;">Описание</div>
          <textarea v-model="description" rows="4" class="sheet-textarea"></textarea>
        </label>

        <div class="sheet-grid-2">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">План. старт</div>
            <input v-model="plannedStartDate" type="date" class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">План. дедлайн</div>
            <input v-model="plannedDueDate" type="date" class="sheet-input" />
          </label>
        </div>

        <div>
          <button type="submit" class="sheet-btn sheet-btn-primary" :disabled="saving">
            {{ saving ? 'Сохранение...' : 'Создать' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

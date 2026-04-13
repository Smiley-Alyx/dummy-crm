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
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Добавить запись</h1>
        <div class="sheet-subtitle">Учёт времени</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/time">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
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
          <input v-model="minutes" required inputmode="numeric" placeholder="например, 90" class="sheet-input" />
        </label>

        <label>
          <div class="sheet-muted" style="font-size: 12px;">Комментарий</div>
          <input v-model="note" class="sheet-input" />
        </label>

        <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

        <div>
          <button type="submit" class="sheet-btn sheet-btn-primary" :disabled="saving">
            {{ saving ? 'Сохранение...' : 'Создать' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

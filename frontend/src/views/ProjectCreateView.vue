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
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Создать проект</h1>
        <div class="sheet-subtitle">Новый проект</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/projects">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <form @submit.prevent="submit" class="sheet-form">
        <label>
          <div class="sheet-muted" style="font-size: 12px;">Название</div>
          <input v-model="name" required class="sheet-input" />
        </label>

        <label>
          <div class="sheet-muted" style="font-size: 12px;">Описание</div>
          <textarea v-model="description" rows="4" class="sheet-textarea"></textarea>
        </label>

        <label>
          <div class="sheet-muted" style="font-size: 12px;">Статус</div>
          <input v-model="status" class="sheet-input" />
        </label>

        <div class="sheet-grid-2">
          <label>
            <div class="sheet-muted" style="font-size: 12px;">Дата старта</div>
            <input v-model="starts_on" type="date" class="sheet-input" />
          </label>

          <label>
            <div class="sheet-muted" style="font-size: 12px;">Дата завершения</div>
            <input v-model="ends_on" type="date" class="sheet-input" />
          </label>
        </div>

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

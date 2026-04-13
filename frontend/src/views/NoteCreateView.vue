<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { api } from '../lib/api'

const router = useRouter()

const title = ref('')
const body = ref<string>('')
const is_pinned = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function submit() {
  saving.value = true
  error.value = null

  try {
    await api.post('/api/notes', {
      title: title.value,
      body: body.value || null,
      is_pinned: is_pinned.value,
    })

    await router.push('/notes')
  } catch (e: any) {
    error.value = e?.response?.data?.message ?? e?.message ?? 'Не удалось создать заметку'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Создать заметку</h1>
        <div class="sheet-subtitle">Новая заметка</div>
      </div>
      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/notes">← Назад</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <form @submit.prevent="submit" class="sheet-form">
        <label>
          <div class="sheet-muted" style="font-size: 12px;">Заголовок</div>
          <input v-model="title" required class="sheet-input" />
        </label>

        <label>
          <div class="sheet-muted" style="font-size: 12px;">Текст</div>
          <textarea v-model="body" rows="6" class="sheet-textarea"></textarea>
        </label>

        <label style="display: flex; gap: 8px; align-items: center;">
          <input v-model="is_pinned" type="checkbox" />
          <span>Закрепить</span>
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

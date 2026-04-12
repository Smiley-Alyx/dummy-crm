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
    error.value = e?.response?.data?.message ?? e?.message ?? 'Failed to create note'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Create note</h1>
      <RouterLink to="/notes">Back</RouterLink>
    </div>

    <form @submit.prevent="submit" style="margin-top: 16px; display: grid; gap: 12px;">
      <label>
        Title
        <input v-model="title" required style="display: block; width: 100%;" />
      </label>

      <label>
        Body
        <textarea v-model="body" rows="6" style="display: block; width: 100%;"></textarea>
      </label>

      <label style="display: flex; gap: 8px; align-items: center;">
        <input v-model="is_pinned" type="checkbox" />
        Pinned
      </label>

      <div v-if="error" style="color: #b91c1c;">{{ error }}</div>

      <button type="submit" :disabled="saving">{{ saving ? 'Saving...' : 'Create' }}</button>
    </form>
  </div>
</template>

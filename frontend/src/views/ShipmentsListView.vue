<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../lib/api'
import { formatDate } from '../lib/format'

type Shipment = {
  id: number
  project_id: number
  title: string
  description: string | null
  planned_start_date: string | null
  planned_due_date: string | null
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

const route = useRoute()
const projectId = ref<number>(Number(route.params.projectId))

const shipments = ref<Shipment[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

watch(
  () => route.params.projectId,
  (v) => {
    projectId.value = Number(v)
    fetchShipments()
  },
)

async function fetchShipments() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Paginated<Shipment>>('/api/shipments', {
      params: {
        project_id: projectId.value,
      },
    })
    shipments.value = res.data.data
  } catch (e: any) {
    error.value = e?.message ?? 'Не удалось загрузить отгрузки'
  } finally {
    loading.value = false
  }
}

async function removeShipment(id: number) {
  if (!confirm('Удалить отгрузку?')) return

  try {
    await api.delete(`/api/shipments/${id}`)
    await fetchShipments()
  } catch (e: any) {
    alert(e?.message ?? 'Не удалось удалить отгрузку')
  }
}

onMounted(fetchShipments)
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <div style="display: flex; align-items: baseline; gap: 12px;">
        <h1 style="margin: 0;">Отгрузки</h1>
        <span style="color: #6b7280;">Проект #{{ projectId }}</span>
      </div>
      <RouterLink :to="`/projects/${projectId}/shipments/new`">Создать</RouterLink>
    </div>

    <div style="margin-top: 12px;">
      <RouterLink to="/projects">← Назад к проектам</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Загрузка...</div>
    <div v-else-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <table v-else style="width: 100%; margin-top: 16px; border-collapse: collapse;">
      <thead>
        <tr>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Название</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Плановые даты</th>
          <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="s in shipments" :key="s.id">
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">
            <RouterLink :to="`/projects/${projectId}/shipments/${s.id}`">{{ s.title }}</RouterLink>
          </td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">
            <span>{{ formatDate(s.planned_start_date) }}</span>
            <span> → </span>
            <span>{{ formatDate(s.planned_due_date) }}</span>
          </td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">
            <RouterLink :to="`/projects/${projectId}/shipments/${s.id}`">Открыть</RouterLink>
            <span> | </span>
            <a href="#" @click.prevent="removeShipment(s.id)">Удалить</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

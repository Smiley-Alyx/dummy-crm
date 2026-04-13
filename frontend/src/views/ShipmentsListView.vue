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
  <div class="sheet-page">
    <div class="sheet-page-header">
      <div>
        <h1>Отгрузки</h1>
        <div class="sheet-subtitle">Проект №{{ projectId }}</div>
      </div>

      <div class="sheet-actions">
        <RouterLink class="sheet-link" to="/projects">← К проектам</RouterLink>
        <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments/new`">Создать</RouterLink>
      </div>
    </div>

    <div class="sheet-body">
      <div v-if="loading">Загрузка...</div>
      <div v-else-if="error" style="color: #b91c1c;">{{ error }}</div>

      <div v-else class="sheet-table-wrap">
        <table class="sheet-table">
          <thead>
            <tr>
              <th>Название</th>
              <th>Плановые даты</th>
              <th class="right">Действия</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in shipments" :key="s.id">
              <td>
                <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments/${s.id}`">{{ s.title }}</RouterLink>
              </td>
              <td>
                <span>{{ formatDate(s.planned_start_date) }}</span>
                <span> → </span>
                <span>{{ formatDate(s.planned_due_date) }}</span>
              </td>
              <td class="right">
                <RouterLink class="sheet-link" :to="`/projects/${projectId}/shipments/${s.id}`">Открыть</RouterLink>
                <span> | </span>
                <a class="sheet-link" href="#" @click.prevent="removeShipment(s.id)">Удалить</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

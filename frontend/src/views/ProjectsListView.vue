<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../lib/api'

type Project = {
  id: number
  name: string
  description: string | null
  status: string
  starts_on: string | null
  ends_on: string | null
  created_at: string
  updated_at: string
}

type Paginated<T> = {
  data: T[]
}

const projects = ref<Project[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

async function fetchProjects() {
  loading.value = true
  error.value = null

  try {
    const res = await api.get<Paginated<Project>>('/api/projects')
    projects.value = res.data.data
  } catch (e: any) {
    error.value = e?.message ?? 'Failed to load projects'
  } finally {
    loading.value = false
  }
}

async function removeProject(id: number) {
  if (!confirm('Delete project?')) return

  try {
    await api.delete(`/api/projects/${id}`)
    await fetchProjects()
  } catch (e: any) {
    alert(e?.message ?? 'Failed to delete project')
  }
}

onMounted(fetchProjects)
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 24px;">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
      <h1 style="margin: 0;">Projects</h1>
      <RouterLink to="/projects/new">Create</RouterLink>
    </div>

    <div v-if="loading" style="margin-top: 16px;">Loading...</div>
    <div v-else-if="error" style="margin-top: 16px; color: #b91c1c;">{{ error }}</div>

    <table v-else style="width: 100%; margin-top: 16px; border-collapse: collapse;">
      <thead>
        <tr>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Name</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Status</th>
          <th style="text-align: left; border-bottom: 1px solid #e5e7eb; padding: 8px;">Dates</th>
          <th style="text-align: right; border-bottom: 1px solid #e5e7eb; padding: 8px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in projects" :key="p.id">
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ p.name }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">{{ p.status }}</td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px;">
            <span>{{ p.starts_on ?? '—' }}</span>
            <span> → </span>
            <span>{{ p.ends_on ?? '—' }}</span>
          </td>
          <td style="border-bottom: 1px solid #f3f4f6; padding: 8px; text-align: right;">
            <RouterLink :to="`/projects/${p.id}/edit`">Edit</RouterLink>
            <span> | </span>
            <a href="#" @click.prevent="removeProject(p.id)">Delete</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

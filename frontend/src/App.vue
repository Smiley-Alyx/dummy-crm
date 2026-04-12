<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div style="max-width: 900px; margin: 0 auto; padding: 16px;">
    <header style="display: flex; gap: 12px; align-items: center; padding: 12px 0; border-bottom: 1px solid #e5e7eb;">
      <RouterLink to="/projects">Projects</RouterLink>
      <RouterLink to="/notes">Notes</RouterLink>
      <RouterLink to="/time">Time</RouterLink>
    </header>

    <main style="padding-top: 16px;">
      <RouterView />
    </main>
  </div>
</template>

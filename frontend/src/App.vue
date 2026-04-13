<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="app-shell">
    <div class="sheet-container">
      <header class="sheet-topbar">
        <RouterLink to="/projects">Проекты</RouterLink>
        <RouterLink to="/notes">Заметки</RouterLink>
        <RouterLink to="/time">Время</RouterLink>
      </header>

      <main>
        <RouterView />
      </main>
    </div>
  </div>
</template>

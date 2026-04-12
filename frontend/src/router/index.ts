import { createRouter, createWebHistory } from 'vue-router'

import ProjectsListView from '../views/ProjectsListView.vue'
import ProjectCreateView from '../views/ProjectCreateView.vue'
import ProjectEditView from '../views/ProjectEditView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/projects' },
    { path: '/projects', component: ProjectsListView },
    { path: '/projects/new', component: ProjectCreateView },
    { path: '/projects/:id/edit', component: ProjectEditView, props: true },
  ],
})

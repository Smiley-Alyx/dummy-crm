import { createRouter, createWebHistory } from 'vue-router'

import ProjectsListView from '../views/ProjectsListView.vue'
import ProjectCreateView from '../views/ProjectCreateView.vue'
import ProjectEditView from '../views/ProjectEditView.vue'
import NotesListView from '../views/NotesListView.vue'
import NoteCreateView from '../views/NoteCreateView.vue'
import NoteEditView from '../views/NoteEditView.vue'
import TimeEntriesListView from '../views/TimeEntriesListView.vue'
import TimeEntryCreateView from '../views/TimeEntryCreateView.vue'
import TimeEntryEditView from '../views/TimeEntryEditView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/projects' },
    { path: '/projects', component: ProjectsListView },
    { path: '/projects/new', component: ProjectCreateView },
    { path: '/projects/:id/edit', component: ProjectEditView, props: true },
    { path: '/notes', component: NotesListView },
    { path: '/notes/new', component: NoteCreateView },
    { path: '/notes/:id/edit', component: NoteEditView, props: true },
    { path: '/time', component: TimeEntriesListView },
    { path: '/time/new', component: TimeEntryCreateView },
    { path: '/time/:id/edit', component: TimeEntryEditView, props: true },
  ],
})

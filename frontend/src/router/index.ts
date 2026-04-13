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
import ShipmentsListView from '../views/ShipmentsListView.vue'
import ShipmentCreateView from '../views/ShipmentCreateView.vue'
import ShipmentDetailView from '../views/ShipmentDetailView.vue'
import ShipmentGanttView from '../views/ShipmentGanttView.vue'
import ProjectGanttSheetView from '../views/ProjectGanttSheetView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/projects' },
    { path: '/projects', component: ProjectsListView },
    { path: '/projects/new', component: ProjectCreateView },
    { path: '/projects/:id/edit', component: ProjectEditView, props: true },
    { path: '/projects/:projectId/shipments', component: ShipmentsListView, props: true },
    { path: '/projects/:projectId/gantt', component: ProjectGanttSheetView, props: true },
    { path: '/projects/:projectId/shipments/new', component: ShipmentCreateView, props: true },
    { path: '/projects/:projectId/shipments/:shipmentId', component: ShipmentDetailView, props: true },
    { path: '/projects/:projectId/shipments/:shipmentId/gantt', component: ShipmentGanttView, props: true },
    { path: '/notes', component: NotesListView },
    { path: '/notes/new', component: NoteCreateView },
    { path: '/notes/:id/edit', component: NoteEditView, props: true },
    { path: '/time', component: TimeEntriesListView },
    { path: '/time/new', component: TimeEntryCreateView },
    { path: '/time/:id/edit', component: TimeEntryEditView, props: true },
  ],
})

<script setup lang="ts">
import { computed } from 'vue'
import { formatDate } from '../lib/format'

type BurndownPoint = {
  i: number
  date: string | null
  planned_remaining_hours: number
  actual_remaining_hours: number
}

const props = defineProps<{
  points: BurndownPoint[]
  totalEstimateHours: number
}>()

const width = 980
const height = 260
const pad = { l: 52, r: 20, t: 16, b: 34 }

const innerW = width - pad.l - pad.r
const innerH = height - pad.t - pad.b

const maxY = computed(() => Math.max(props.totalEstimateHours || 0, 1))

function xAt(i: number) {
  const n = Math.max(props.points.length - 1, 1)
  return pad.l + (innerW * i) / n
}

function yAt(v: number) {
  const y = Math.max(0, Math.min(v, maxY.value))
  return pad.t + innerH - (innerH * y) / maxY.value
}

const plannedPath = computed(() => {
  if (!props.points.length) return ''
  return props.points
    .map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${xAt(idx)} ${yAt(p.planned_remaining_hours)}`)
    .join(' ')
})

const actualPath = computed(() => {
  if (!props.points.length) return ''
  return props.points
    .map((p, idx) => `${idx === 0 ? 'M' : 'L'} ${xAt(idx)} ${yAt(p.actual_remaining_hours)}`)
    .join(' ')
})

const yTicks = computed(() => {
  const ticks = 4
  const out: number[] = []
  for (let i = 0; i <= ticks; i++) {
    out.push((maxY.value * i) / ticks)
  }
  return out
})
</script>

<template>
  <div class="sheet-table-wrap" style="padding: 12px;">
    <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 8px;">
      <div style="font-weight: 600;">Burndown</div>
      <div class="sheet-muted" style="font-size: 12px;">Оценка: {{ totalEstimateHours }} ч</div>
    </div>

    <svg :width="width" :height="height" viewBox="0 0 980 260" role="img" aria-label="Burndown chart">
      <rect x="0" y="0" width="980" height="260" fill="#fff" />

      <g>
        <line
          v-for="t in yTicks"
          :key="t"
          :x1="pad.l"
          :x2="width - pad.r"
          :y1="yAt(t)"
          :y2="yAt(t)"
          stroke="#e0e0e0"
          stroke-width="1"
        />
        <text
          v-for="t in yTicks"
          :key="`lbl-${t}`"
          :x="pad.l - 8"
          :y="yAt(t) + 4"
          text-anchor="end"
          font-size="11"
          fill="#5f6368"
        >
          {{ t.toFixed(0) }}
        </text>
      </g>

      <path :d="plannedPath" fill="none" stroke="#1a73e8" stroke-width="2" />
      <path :d="actualPath" fill="none" stroke="#d93025" stroke-width="2" />

      <g>
        <circle
          v-for="(p, idx) in points"
          :key="`p-${idx}`"
          :cx="xAt(idx)"
          :cy="yAt(p.planned_remaining_hours)"
          r="2.5"
          fill="#1a73e8"
        >
          <title>План: {{ p.planned_remaining_hours }} ч ({{ p.date ? formatDate(p.date) : '—' }})</title>
        </circle>

        <circle
          v-for="(p, idx) in points"
          :key="`a-${idx}`"
          :cx="xAt(idx)"
          :cy="yAt(p.actual_remaining_hours)"
          r="2.5"
          fill="#d93025"
        >
          <title>Факт: {{ p.actual_remaining_hours }} ч ({{ p.date ? formatDate(p.date) : '—' }})</title>
        </circle>
      </g>

      <g>
        <text x="72" y="246" font-size="11" fill="#5f6368">План</text>
        <rect x="52" y="238" width="14" height="3" fill="#1a73e8" />

        <text x="132" y="246" font-size="11" fill="#5f6368">Факт</text>
        <rect x="112" y="238" width="14" height="3" fill="#d93025" />
      </g>
    </svg>
  </div>
</template>

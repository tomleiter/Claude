<template>
  <div class="relative inline-flex items-center justify-center" :style="{ width: size + 'px', height: size + 'px' }">
    <svg class="transform -rotate-90" :width="size" :height="size">
      <!-- Background circle -->
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke="trackColor"
        :stroke-width="strokeWidth"
      />
      <!-- Progress circle -->
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke="progressStrokeColor"
        :stroke-width="strokeWidth"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        stroke-linecap="round"
        class="transition-all duration-1000 ease-out"
      />
      <!-- Glow effect when complete -->
      <circle
        v-if="percent >= 100"
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        fill="none"
        :stroke="progressStrokeColor"
        :stroke-width="strokeWidth + 4"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="dashOffset"
        stroke-linecap="round"
        class="animate-pulse opacity-30"
      />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center">
      <span class="font-bold" :class="textSizeClass" :style="{ color: progressStrokeColor }">
        {{ Math.round(animatedPercent) }}%
      </span>
      <span v-if="label" class="text-[9px] text-gray-400 leading-tight">{{ label }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted } from 'vue'

const props = withDefaults(defineProps<{
  percent: number
  size?: number
  strokeWidth?: number
  label?: string
}>(), {
  size: 80,
  strokeWidth: 6,
})

const animatedPercent = ref(0)
let animFrame: number | null = null

function animateTo(target: number) {
  const start = animatedPercent.value
  const diff = target - start
  const duration = 800
  const startTime = performance.now()

  function step(currentTime: number) {
    const elapsed = currentTime - startTime
    const progress = Math.min(elapsed / duration, 1)
    // Ease out cubic
    const ease = 1 - Math.pow(1 - progress, 3)
    animatedPercent.value = start + diff * ease
    if (progress < 1) {
      animFrame = requestAnimationFrame(step)
    }
  }
  if (animFrame) cancelAnimationFrame(animFrame)
  animFrame = requestAnimationFrame(step)
}

onMounted(() => animateTo(props.percent))
watch(() => props.percent, (v) => animateTo(v))

const radius = computed(() => (props.size - props.strokeWidth) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const dashOffset = computed(() => circumference.value * (1 - Math.min(animatedPercent.value, 100) / 100))
const trackColor = '#E5E7EB'

const progressStrokeColor = computed(() => {
  const p = props.percent
  if (p >= 100) return '#10B981'
  if (p >= 75) return '#F59E0B'
  if (p >= 50) return '#F97316'
  return '#EF4444'
})

const textSizeClass = computed(() => {
  if (props.size >= 100) return 'text-xl'
  if (props.size >= 70) return 'text-sm'
  return 'text-xs'
})
</script>

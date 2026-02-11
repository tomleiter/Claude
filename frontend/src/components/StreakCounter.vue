<template>
  <div class="bg-gradient-to-r from-orange-50 to-red-50 rounded-lg p-3 border border-orange-200">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="text-2xl">{{ streakEmoji }}</span>
        <div>
          <div class="text-xs text-gray-500 font-medium">Combo-Streak</div>
          <div class="text-lg font-black text-orange-600">{{ streak }}x</div>
        </div>
      </div>
      <div class="text-right">
        <div class="text-xs text-gray-500">Bonus</div>
        <div class="text-sm font-bold text-green-600">+{{ bonusPercent }}%</div>
      </div>
    </div>
    <!-- Streak bar with timer -->
    <div class="mt-2 bg-orange-100 rounded-full h-1.5 overflow-hidden">
      <div
        class="h-1.5 rounded-full bg-gradient-to-r from-orange-400 to-red-500 transition-all duration-300"
        :style="{ width: timerPercent + '%' }"
      ></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'

const streak = ref(0)
const lastActionTime = ref(0)
const timerPercent = ref(100)
const STREAK_TIMEOUT = 15000 // 15 seconds to keep streak

let interval: ReturnType<typeof setInterval> | null = null

const streakEmoji = computed(() => {
  if (streak.value >= 10) return '🔥'
  if (streak.value >= 7) return '⚡'
  if (streak.value >= 5) return '💥'
  if (streak.value >= 3) return '✨'
  return '💫'
})

const bonusPercent = computed(() => {
  return Math.min(streak.value * 5, 50)
})

function tick() {
  const elapsed = Date.now() - lastActionTime.value
  if (elapsed > STREAK_TIMEOUT && streak.value > 0) {
    streak.value = 0
    timerPercent.value = 0
  } else if (lastActionTime.value > 0) {
    timerPercent.value = Math.max(0, 100 - (elapsed / STREAK_TIMEOUT) * 100)
  }
}

function recordAction() {
  const now = Date.now()
  if (now - lastActionTime.value < STREAK_TIMEOUT) {
    streak.value++
  } else {
    streak.value = 1
  }
  lastActionTime.value = now
  timerPercent.value = 100
}

onMounted(() => {
  interval = setInterval(tick, 100)
})

onUnmounted(() => {
  if (interval) clearInterval(interval)
})

defineExpose({ recordAction, streak })
</script>

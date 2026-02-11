<template>
  <div>
    <!-- Toggle button -->
    <button
      @click="showPanel = !showPanel"
      class="relative btn-secondary btn-sm flex items-center gap-1.5"
    >
      <span class="text-base">🏆</span>
      <span>{{ unlockedCount }}/{{ total }}</span>
      <!-- Notification dot -->
      <span
        v-if="hasNew"
        class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-ping-slow"
      ></span>
    </button>

    <!-- Panel overlay -->
    <Teleport to="body">
      <Transition name="slide-in">
        <div v-if="showPanel" class="fixed inset-0 z-50 flex justify-end" @click.self="showPanel = false">
          <div class="w-96 bg-white shadow-2xl h-full overflow-y-auto">
            <div class="p-4 border-b bg-gradient-to-r from-purple-600 to-pink-600 text-white">
              <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold flex items-center gap-2">
                  <span class="text-2xl">🏆</span> Achievements
                </h2>
                <button @click="showPanel = false" class="text-white/70 hover:text-white text-xl">&times;</button>
              </div>
              <p class="text-sm text-white/80 mt-1">{{ unlockedCount }} von {{ total }} freigeschaltet</p>
              <!-- Progress -->
              <div class="mt-2 bg-white/20 rounded-full h-2">
                <div
                  class="h-2 rounded-full bg-white transition-all duration-500"
                  :style="{ width: (unlockedCount / total * 100) + '%' }"
                ></div>
              </div>
            </div>

            <div class="p-4 space-y-3">
              <div
                v-for="a in sortedAchievements"
                :key="a.id"
                class="flex items-center gap-3 p-3 rounded-lg transition-all duration-300"
                :class="a.unlockedAt
                  ? 'bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 shadow-sm'
                  : 'bg-gray-50 border border-gray-100 opacity-60 grayscale'"
              >
                <div class="text-3xl w-12 h-12 flex items-center justify-center rounded-xl"
                  :class="a.unlockedAt ? 'bg-yellow-100 animate-float' : 'bg-gray-100'">
                  {{ a.unlockedAt ? a.icon : (a.secret && !a.unlockedAt ? '❓' : a.icon) }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-semibold text-sm">
                    {{ a.secret && !a.unlockedAt ? '???' : a.title }}
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ a.secret && !a.unlockedAt ? 'Geheimes Achievement' : a.description }}
                  </div>
                  <div v-if="a.unlockedAt" class="text-xs text-green-600 mt-0.5">
                    +{{ a.xpReward }} XP
                  </div>
                </div>
                <div v-if="a.unlockedAt" class="text-green-500">
                  <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" />
                  </svg>
                </div>
                <div v-else class="text-gray-300">
                  <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" />
                  </svg>
                </div>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useGamification } from '../composables/useGamification'

const { state, unlockedCount } = useGamification()

const showPanel = ref(false)
const hasNew = ref(false)

const total = computed(() => state.achievements.length)

const sortedAchievements = computed(() => {
  return [...state.achievements].sort((a, b) => {
    if (a.unlockedAt && !b.unlockedAt) return -1
    if (!a.unlockedAt && b.unlockedAt) return 1
    if (a.unlockedAt && b.unlockedAt) return new Date(b.unlockedAt).getTime() - new Date(a.unlockedAt).getTime()
    return 0
  })
})
</script>

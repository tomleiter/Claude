<template>
  <div class="flex items-center gap-2">
    <!-- Level badge -->
    <div
      class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-sm shadow-lg transition-all duration-300"
      :style="{ background: `linear-gradient(135deg, ${currentLevel.color}, ${currentLevel.color}dd)` }"
      :title="currentLevel.title"
    >
      {{ currentLevel.level }}
    </div>

    <!-- XP bar -->
    <div class="flex-1 min-w-0">
      <div class="flex items-center justify-between text-xs mb-0.5">
        <span class="font-semibold" :style="{ color: currentLevel.color }">{{ currentLevel.title }}</span>
        <span class="text-gray-400">{{ state.xp }} XP</span>
      </div>
      <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
        <div
          class="h-2 rounded-full transition-all duration-700 ease-out relative overflow-hidden"
          :style="{ width: xpProgress + '%', backgroundColor: currentLevel.color }"
        >
          <!-- Shimmer effect -->
          <div class="absolute inset-0 shimmer"></div>
        </div>
      </div>
      <div v-if="nextLevel" class="text-[10px] text-gray-400 mt-0.5">
        {{ nextLevel.xpRequired - state.xp }} XP bis {{ nextLevel.title }}
      </div>
    </div>
  </div>

  <!-- Level up overlay -->
  <Teleport to="body">
    <Transition name="level-up">
      <div v-if="state.showLevelUp" class="fixed inset-0 z-[9998] flex items-center justify-center pointer-events-none">
        <div class="text-center animate-level-up">
          <div class="text-6xl mb-2">⬆️</div>
          <div class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-orange-500 to-red-500">
            LEVEL UP!
          </div>
          <div class="text-xl font-bold mt-1" :style="{ color: currentLevel.color }">
            {{ currentLevel.title }}
          </div>
          <div
            class="mt-2 w-16 h-16 mx-auto rounded-full flex items-center justify-center text-2xl font-black text-white shadow-2xl"
            :style="{ background: `linear-gradient(135deg, ${currentLevel.color}, ${currentLevel.color}bb)` }"
          >
            {{ currentLevel.level }}
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { useGamification } from '../composables/useGamification'

const { state, currentLevel, nextLevel, xpProgress } = useGamification()
</script>

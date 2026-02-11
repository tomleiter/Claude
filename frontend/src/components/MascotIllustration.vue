<template>
  <div class="flex flex-col items-center select-none">
    <svg :width="size" :height="size" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
      <!-- Background circle glow -->
      <circle cx="100" cy="100" :r="85" :fill="bgColor" class="transition-all duration-700" opacity="0.15" />
      <circle cx="100" cy="100" :r="70" :fill="bgColor" class="transition-all duration-700" opacity="0.08" />

      <!-- Body -->
      <ellipse cx="100" cy="135" rx="45" ry="40" :fill="bodyColor" class="transition-all duration-500" />

      <!-- Belly -->
      <ellipse cx="100" cy="140" rx="30" ry="25" fill="white" opacity="0.3" />

      <!-- Progress fill in belly -->
      <clipPath id="bellyClip">
        <ellipse cx="100" cy="140" rx="28" ry="23" />
      </clipPath>
      <rect
        :y="140 + 23 - (46 * percent / 100)"
        x="72"
        width="56"
        :height="46 * percent / 100"
        :fill="progressFill"
        clip-path="url(#bellyClip)"
        class="transition-all duration-1000 ease-out"
        opacity="0.5"
      />

      <!-- Head -->
      <circle cx="100" :cy="headY" r="35" :fill="bodyColor" class="transition-all duration-500">
        <animateTransform
          v-if="isHappy"
          attributeName="transform"
          type="translate"
          values="0,0; 0,-3; 0,0"
          dur="1s"
          repeatCount="indefinite"
        />
      </circle>

      <!-- Cheeks (blush) -->
      <circle :cx="78" :cy="headY + 8" r="8" fill="#FF9999" :opacity="blushOpacity" class="transition-all duration-500" />
      <circle :cx="122" :cy="headY + 8" r="8" fill="#FF9999" :opacity="blushOpacity" class="transition-all duration-500" />

      <!-- Eyes -->
      <g v-if="!isCelebrating">
        <!-- Left eye -->
        <ellipse :cx="87" :cy="headY - 5" :rx="eyeRx" :ry="eyeRy" fill="white" />
        <circle :cx="87 + pupilOffset" :cy="headY - 5" :r="pupilSize" fill="#333">
          <animate v-if="isThinking" attributeName="cx" :values="`${85};${89};${85}`" dur="2s" repeatCount="indefinite" />
        </circle>
        <!-- Sparkle in eye -->
        <circle v-if="isHappy" :cx="85" :cy="headY - 8" r="2" fill="white" />

        <!-- Right eye -->
        <ellipse :cx="113" :cy="headY - 5" :rx="eyeRx" :ry="eyeRy" fill="white" />
        <circle :cx="113 + pupilOffset" :cy="headY - 5" :r="pupilSize" fill="#333">
          <animate v-if="isThinking" attributeName="cx" :values="`${111};${115};${111}`" dur="2s" repeatCount="indefinite" />
        </circle>
        <circle v-if="isHappy" :cx="111" :cy="headY - 8" r="2" fill="white" />
      </g>

      <!-- Celebrating eyes (stars) -->
      <g v-if="isCelebrating">
        <text :x="82" :y="headY" font-size="18" text-anchor="middle">⭐</text>
        <text :x="118" :y="headY" font-size="18" text-anchor="middle">⭐</text>
      </g>

      <!-- Mouth -->
      <path :d="mouthPath" :stroke="mouthStroke" stroke-width="2.5" fill="none" stroke-linecap="round" class="transition-all duration-500" />
      <!-- Open happy mouth -->
      <ellipse v-if="percent >= 80" cx="100" :cy="headY + 14" rx="8" ry="5" :fill="mouthStroke" opacity="0.8" />

      <!-- Eyebrows -->
      <line :x1="80" :y1="headY - 18 + browOffset" :x2="94" :y2="headY - 20 + browOffset" stroke="#555" stroke-width="2" stroke-linecap="round" class="transition-all duration-500" />
      <line :x1="106" :y1="headY - 20 + browOffset" :x2="120" :y2="headY - 18 + browOffset" stroke="#555" stroke-width="2" stroke-linecap="round" class="transition-all duration-500" />

      <!-- Arms -->
      <!-- Left arm -->
      <path :d="leftArmPath" :stroke="bodyColor" stroke-width="8" fill="none" stroke-linecap="round" class="transition-all duration-500" />
      <!-- Right arm -->
      <path :d="rightArmPath" :stroke="bodyColor" stroke-width="8" fill="none" stroke-linecap="round" class="transition-all duration-500" />

      <!-- Clipboard / document the mascot holds -->
      <g v-if="percent < 100" :transform="`translate(${clipboardX}, ${clipboardY})`">
        <rect x="0" y="0" width="22" height="28" rx="2" fill="white" stroke="#CBD5E1" stroke-width="1.5" />
        <rect x="7" y="-3" width="8" height="5" rx="1.5" fill="#94A3B8" />
        <!-- Lines on clipboard -->
        <line x1="4" y1="8" x2="18" y2="8" stroke="#E2E8F0" stroke-width="1.5" />
        <line x1="4" y1="12" x2="18" y2="12" stroke="#E2E8F0" stroke-width="1.5" />
        <line x1="4" y1="16" x2="14" y2="16" stroke="#E2E8F0" stroke-width="1.5" />
        <!-- Checkmarks based on progress -->
        <text v-if="percent >= 25" x="3" y="10" font-size="5" fill="#22C55E">✓</text>
        <text v-if="percent >= 50" x="3" y="14" font-size="5" fill="#22C55E">✓</text>
        <text v-if="percent >= 75" x="3" y="18" font-size="5" fill="#22C55E">✓</text>
      </g>

      <!-- Trophy when 100% -->
      <g v-if="percent >= 100" transform="translate(130, 90)">
        <text font-size="30" text-anchor="middle" x="0" y="10">
          🏆
          <animateTransform attributeName="transform" type="scale" values="1;1.2;1" dur="1.5s" repeatCount="indefinite" />
        </text>
      </g>

      <!-- Feet -->
      <ellipse cx="85" cy="172" rx="14" ry="6" :fill="feetColor" class="transition-all duration-500" />
      <ellipse cx="115" cy="172" rx="14" ry="6" :fill="feetColor" class="transition-all duration-500" />

      <!-- Sparkles around when happy -->
      <g v-if="percent >= 80" class="animate-spin-very-slow" style="transform-origin: 100px 100px">
        <circle cx="45" cy="60" r="3" fill="#FBBF24" opacity="0.8">
          <animate attributeName="opacity" values="0.8;0.2;0.8" dur="1.5s" repeatCount="indefinite" />
        </circle>
        <circle cx="155" cy="55" r="2" fill="#F472B6" opacity="0.6">
          <animate attributeName="opacity" values="0.6;0.1;0.6" dur="2s" repeatCount="indefinite" />
        </circle>
        <circle cx="160" cy="120" r="2.5" fill="#34D399" opacity="0.7">
          <animate attributeName="opacity" values="0.7;0.2;0.7" dur="1.8s" repeatCount="indefinite" />
        </circle>
        <circle cx="40" cy="130" r="2" fill="#60A5FA" opacity="0.7">
          <animate attributeName="opacity" values="0.7;0.1;0.7" dur="1.3s" repeatCount="indefinite" />
        </circle>
      </g>

      <!-- Small hearts when 100% -->
      <g v-if="percent >= 100">
        <text x="50" y="50" font-size="14" opacity="0.8">
          💜
          <animate attributeName="y" values="50;40;50" dur="2s" repeatCount="indefinite" />
          <animate attributeName="opacity" values="0.8;0.3;0.8" dur="2s" repeatCount="indefinite" />
        </text>
        <text x="140" y="45" font-size="12" opacity="0.6">
          💙
          <animate attributeName="y" values="45;35;45" dur="2.5s" repeatCount="indefinite" />
          <animate attributeName="opacity" values="0.6;0.2;0.6" dur="2.5s" repeatCount="indefinite" />
        </text>
      </g>
    </svg>

    <!-- Message below mascot -->
    <div class="mt-2 text-center max-w-[200px]">
      <p class="text-sm font-bold" :style="{ color: bodyColor }">{{ statusTitle }}</p>
      <p class="text-xs text-gray-500 mt-0.5">{{ statusMessage }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  percent: number
  size?: number
}>(), {
  size: 180,
})

// Mood states
const isHappy = computed(() => props.percent >= 50)
const isCelebrating = computed(() => props.percent >= 100)
const isThinking = computed(() => props.percent > 0 && props.percent < 30)

// Colors based on progress
const bodyColor = computed(() => {
  if (props.percent >= 100) return '#8B5CF6' // purple celebration
  if (props.percent >= 75) return '#10B981'  // green
  if (props.percent >= 50) return '#3B82F6'  // blue
  if (props.percent >= 25) return '#F59E0B'  // amber
  return '#6B7280' // gray
})

const feetColor = computed(() => {
  if (props.percent >= 100) return '#7C3AED'
  if (props.percent >= 75) return '#059669'
  if (props.percent >= 50) return '#2563EB'
  if (props.percent >= 25) return '#D97706'
  return '#4B5563'
})

const bgColor = computed(() => bodyColor.value)
const progressFill = computed(() => bodyColor.value)
const mouthStroke = computed(() => props.percent >= 50 ? '#333' : '#666')

// Animations
const headY = computed(() => props.percent >= 80 ? 88 : 92)
const blushOpacity = computed(() => props.percent >= 50 ? 0.4 : 0)

const eyeRx = computed(() => props.percent >= 80 ? 8 : 7)
const eyeRy = computed(() => {
  if (props.percent >= 80) return 8
  if (props.percent < 10) return 5 // sleepy
  return 7
})
const pupilSize = computed(() => props.percent >= 50 ? 4 : 3.5)
const pupilOffset = computed(() => 0)

const browOffset = computed(() => {
  if (props.percent >= 80) return -3 // excited
  if (props.percent < 10) return 3  // sleepy/sad
  return 0
})

const mouthPath = computed(() => {
  const y = headY.value
  if (props.percent >= 80) return `M 90 ${y + 12} Q 100 ${y + 22} 110 ${y + 12}` // big smile
  if (props.percent >= 50) return `M 92 ${y + 11} Q 100 ${y + 17} 108 ${y + 11}` // smile
  if (props.percent >= 25) return `M 94 ${y + 12} L 106 ${y + 12}` // neutral
  return `M 92 ${y + 15} Q 100 ${y + 10} 108 ${y + 15}` // slight frown
})

// Arms
const leftArmPath = computed(() => {
  if (props.percent >= 100) return 'M 58 130 Q 40 110 35 85' // arms up celebrating
  if (props.percent >= 50) return 'M 58 130 Q 45 125 38 115' // arm out holding clipboard
  return 'M 58 135 Q 48 140 42 150' // arms down
})

const rightArmPath = computed(() => {
  if (props.percent >= 100) return 'M 142 130 Q 160 110 165 85' // arms up
  if (props.percent >= 50) return 'M 142 130 Q 155 125 160 120'
  return 'M 142 135 Q 152 140 158 150'
})

const clipboardX = computed(() => {
  if (props.percent >= 50) return 25
  return 30
})
const clipboardY = computed(() => {
  if (props.percent >= 50) return 105
  return 145
})

// Status messages
const statusTitle = computed(() => {
  if (props.percent >= 100) return 'Fantastisch!'
  if (props.percent >= 80) return 'Fast geschafft!'
  if (props.percent >= 50) return 'Gute Arbeit!'
  if (props.percent >= 25) return 'Weiter so!'
  if (props.percent > 0) return 'Guter Anfang!'
  return 'Los geht\'s!'
})

const statusMessages: Record<string, string[]> = {
  '0': ['Klicke auf ein Feld um zu starten', 'Jedes Feld bringt dir XP!', 'Ich warte auf dich!'],
  '10': ['Das ist der Geist!', 'Du hast angefangen, super!', 'Mach weiter, Schritt für Schritt!'],
  '25': ['Ein Viertel geschafft!', 'Du bist auf dem richtigen Weg!', 'Ich glaube an dich!'],
  '50': ['Halbzeit! Ich freue mich!', 'Die Hälfte ist geschafft!', 'Du bist ein Profi!'],
  '75': ['Nur noch ein bisschen!', 'So nah am Ziel!', 'Ich bin begeistert!'],
  '100': ['Alles erledigt! 🎉', 'Du bist der Beste!', 'Perfekt ausgefüllt!'],
}

const statusMessage = computed(() => {
  let key = '0'
  if (props.percent >= 100) key = '100'
  else if (props.percent >= 75) key = '75'
  else if (props.percent >= 50) key = '50'
  else if (props.percent >= 25) key = '25'
  else if (props.percent > 0) key = '10'

  const msgs = statusMessages[key]
  return msgs[Math.floor(Math.random() * msgs.length)]
})
</script>

<style scoped>
@keyframes spin-very-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin-very-slow {
  animation: spin-very-slow 15s linear infinite;
}
</style>

<template>
  <span
    class="inline-flex items-center text-xs font-medium px-1.5 py-0.5 rounded-full"
    :class="badgeClass"
    :title="`${completion.requiredFilled}/${completion.requiredTotal} Pflichtfelder`"
  >
    {{ completion.requiredPercent }}%
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CompletionStatus } from '../types'

const props = defineProps<{
  completion: CompletionStatus
}>()

const badgeClass = computed(() => {
  const p = props.completion.requiredPercent
  if (p === 100) return 'bg-green-100 text-green-700'
  if (p >= 75) return 'bg-yellow-100 text-yellow-700'
  if (p >= 50) return 'bg-orange-100 text-orange-700'
  return 'bg-red-100 text-red-700'
})
</script>

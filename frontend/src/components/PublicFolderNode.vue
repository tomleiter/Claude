<template>
  <div>
    <div
      class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer text-sm"
      :class="{
        'bg-blue-100 text-blue-800': selectedId === node.id,
        'hover:bg-gray-100': selectedId !== node.id,
      }"
      :style="{ paddingLeft: depth * 16 + 'px' }"
      @click="$emit('select', node.id)"
    >
      <button
        v-if="node.children?.length"
        class="w-4 h-4 flex items-center justify-center text-gray-400"
        @click.stop="expanded = !expanded"
      >
        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': expanded }" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
        </svg>
      </button>
      <span v-else class="w-4"></span>

      <svg class="w-4 h-4 text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
      </svg>

      <span class="truncate flex-1">{{ node.name }}</span>

      <CompletionBadge v-if="node.completion" :completion="node.completion" />
    </div>

    <div v-if="expanded && node.children?.length">
      <PublicFolderNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :selected-id="selectedId"
        :depth="depth + 1"
        @select="$emit('select', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import type { FolderNode } from '../types'
import CompletionBadge from './CompletionBadge.vue'

const props = defineProps<{
  node: FolderNode
  selectedId: string | null
  depth: number
}>()

defineEmits<{
  select: [id: string]
}>()

const expanded = ref(props.depth < 3)
</script>

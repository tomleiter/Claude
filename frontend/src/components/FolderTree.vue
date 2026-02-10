<template>
  <div class="select-none">
    <div
      class="flex items-center gap-1 px-2 py-1 rounded cursor-pointer text-sm group"
      :class="{
        'bg-blue-100 text-blue-800': selectedId === node.id,
        'hover:bg-gray-100': selectedId !== node.id,
      }"
      :style="{ paddingLeft: depth * 16 + 'px' }"
      :draggable="node.id !== 'root'"
      @click="$emit('select', node.id)"
      @dragstart.stop="onDragStart"
      @dragover.prevent="onDragOver"
      @dragleave="onDragLeave"
      @drop.prevent="onDrop"
    >
      <!-- Expand/Collapse -->
      <button
        v-if="node.children?.length"
        class="w-4 h-4 flex items-center justify-center text-gray-400 hover:text-gray-600"
        @click.stop="expanded = !expanded"
      >
        <svg class="w-3 h-3 transition-transform" :class="{ 'rotate-90': expanded }" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" />
        </svg>
      </button>
      <span v-else class="w-4"></span>

      <!-- Folder icon -->
      <svg class="w-4 h-4 text-yellow-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
        <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
      </svg>

      <!-- Name -->
      <span class="truncate flex-1">{{ node.name }}</span>

      <!-- Completion indicator -->
      <CompletionBadge v-if="node.completion" :completion="node.completion" class="flex-shrink-0" />

      <!-- Share indicator -->
      <span v-if="(node.shareTokens?.length ?? 0) > 0" class="text-green-500 flex-shrink-0" title="Geteilt">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
          <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z" />
        </svg>
      </span>

      <!-- Context menu -->
      <div class="hidden group-hover:flex items-center gap-0.5 flex-shrink-0">
        <button
          class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-blue-600 rounded"
          title="Neuer Unterordner"
          @click.stop="$emit('create', node.id)"
        >+</button>
        <button
          class="w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded"
          title="Aktionen"
          @click.stop="showMenu = !showMenu"
        >
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Context menu dropdown -->
    <div v-if="showMenu" class="ml-8 bg-white border rounded shadow-lg py-1 text-sm z-50 relative">
      <button class="w-full text-left px-3 py-1 hover:bg-gray-100" @click="doAction('rename')">Umbenennen</button>
      <button class="w-full text-left px-3 py-1 hover:bg-gray-100" @click="doAction('copy')">Kopieren</button>
      <button class="w-full text-left px-3 py-1 hover:bg-gray-100" @click="doAction('share')">Link teilen</button>
      <button class="w-full text-left px-3 py-1 hover:bg-gray-100" @click="doAction('download')">Als ZIP herunterladen</button>
      <template v-if="(node.shareTokens?.length ?? 0) > 0">
        <div class="border-t my-1"></div>
        <div class="px-3 py-1 text-xs text-gray-500">Geteilte Links:</div>
        <div v-for="token in node.shareTokens" :key="token" class="flex items-center px-3 py-1 hover:bg-gray-100">
          <span class="text-xs text-gray-600 truncate flex-1">...{{ token.slice(-8) }}</span>
          <button class="text-red-500 text-xs ml-2" @click.stop="$emit('delete-share', node.id, token)">X</button>
        </div>
      </template>
      <div v-if="node.id !== 'root'" class="border-t my-1"></div>
      <button v-if="node.id !== 'root'" class="w-full text-left px-3 py-1 hover:bg-red-50 text-red-600" @click="doAction('delete')">
        Löschen
      </button>
    </div>

    <!-- Children -->
    <div v-if="expanded && node.children?.length">
      <FolderTree
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :selected-id="selectedId"
        :depth="depth + 1"
        @select="$emit('select', $event)"
        @create="$emit('create', $event)"
        @rename="$emit('rename', $event, $event)"
        @delete="$emit('delete', $event)"
        @copy="$emit('copy', $event)"
        @move="(fId, pId) => $emit('move', fId, pId)"
        @share="$emit('share', $event)"
        @delete-share="(fId, t) => $emit('delete-share', fId, t)"
        @download="$emit('download', $event)"
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

const emit = defineEmits<{
  select: [id: string]
  create: [parentId: string]
  rename: [id: string, name: string]
  delete: [id: string]
  copy: [id: string]
  move: [folderId: string, newParentId: string]
  share: [id: string]
  'delete-share': [folderId: string, token: string]
  download: [id: string]
}>()

const expanded = ref(props.depth < 2)
const showMenu = ref(false)
const dragOver = ref(false)

function doAction(action: string) {
  showMenu.value = false
  switch (action) {
    case 'rename':
      emit('rename', props.node.id, props.node.name)
      break
    case 'copy':
      emit('copy', props.node.id)
      break
    case 'delete':
      emit('delete', props.node.id)
      break
    case 'share':
      emit('share', props.node.id)
      break
    case 'download':
      emit('download', props.node.id)
      break
  }
}

function onDragStart(e: DragEvent) {
  e.dataTransfer?.setData('folderId', props.node.id)
}

function onDragOver(e: DragEvent) {
  if (e.dataTransfer?.types.includes('folderid')) {
    dragOver.value = true
  }
}

function onDragLeave() {
  dragOver.value = false
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const folderId = e.dataTransfer?.getData('folderId')
  if (folderId && folderId !== props.node.id) {
    emit('move', folderId, props.node.id)
  }
}
</script>

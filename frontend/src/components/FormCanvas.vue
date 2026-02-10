<template>
  <div
    ref="canvasContainer"
    class="flex-1 relative overflow-auto bg-gray-100"
    @dragover.prevent="onDragOver"
    @drop.prevent="onDrop"
    @click="$emit('selectField', null)"
  >
    <!-- Canvas background -->
    <div
      ref="canvas"
      class="relative min-w-[800px] min-h-[600px]"
      :style="canvasStyle"
    >
      <!-- Background image -->
      <img
        v-if="folderData?.canvasImage"
        :src="folderData.canvasImage"
        class="absolute inset-0 w-full h-full object-contain pointer-events-none"
        @load="onImageLoad"
      />

      <!-- Grid overlay -->
      <div v-if="!folderData?.canvasImage" class="absolute inset-0 opacity-10"
        style="background-image: linear-gradient(#000 1px, transparent 1px), linear-gradient(90deg, #000 1px, transparent 1px); background-size: 20px 20px;">
      </div>

      <!-- Fields on canvas -->
      <div
        v-for="field in folderData?.fields || []"
        :key="field.id"
        class="canvas-field"
        :class="{ selected: selectedFieldId === field.id }"
        :style="{
          left: field.position.x + 'px',
          top: field.position.y + 'px',
          width: field.size.width + 'px',
          height: field.size.height + 'px',
        }"
        @mousedown.stop="startDrag($event, field)"
        @click.stop="$emit('selectField', field.id)"
      >
        <div class="p-1.5 text-xs h-full flex flex-col">
          <div class="flex items-center gap-1 mb-1">
            <span class="font-semibold truncate">{{ field.label }}</span>
            <span v-if="field.required" class="text-red-500">*</span>
            <span class="text-gray-400 ml-auto">{{ fieldTypeLabel(field.type) }}</span>
          </div>
          <div class="flex-1 bg-gray-100/50 rounded border border-dashed border-gray-300 flex items-center justify-center text-gray-400">
            {{ fieldPlaceholder(field.type) }}
          </div>
        </div>

        <!-- Resize handle -->
        <div
          class="absolute bottom-0 right-0 w-3 h-3 cursor-se-resize bg-blue-500 opacity-0 hover:opacity-100"
          :class="{ 'opacity-50': selectedFieldId === field.id }"
          @mousedown.stop="startResize($event, field)"
        ></div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { FolderData, FieldDefinition, FieldType } from '../types'

const props = defineProps<{
  folderData: FolderData | null
  selectedFieldId: string | null
}>()

const emit = defineEmits<{
  selectField: [id: string | null]
  updateFieldPosition: [fieldId: string, x: number, y: number]
  updateFieldSize: [fieldId: string, width: number, height: number]
  dropNewField: [type: FieldType, x: number, y: number]
}>()

const canvasContainer = ref<HTMLElement | null>(null)
const canvas = ref<HTMLElement | null>(null)
const imgWidth = ref(800)
const imgHeight = ref(600)

const canvasStyle = computed(() => ({
  width: imgWidth.value + 'px',
  height: imgHeight.value + 'px',
}))

function onImageLoad(e: Event) {
  const img = e.target as HTMLImageElement
  imgWidth.value = Math.max(img.naturalWidth, 800)
  imgHeight.value = Math.max(img.naturalHeight, 600)
}

function fieldTypeLabel(type: FieldType): string {
  const labels: Record<string, string> = {
    text: 'Text',
    textarea: 'Textarea',
    wysiwyg: 'WYSIWYG',
    upload: 'Upload',
    'multi-upload': 'Multi-Upload',
    select: 'Auswahl',
    repeatable: 'Referenzen',
  }
  return labels[type] || type
}

function fieldPlaceholder(type: FieldType): string {
  const labels: Record<string, string> = {
    text: 'Texteingabe...',
    textarea: 'Mehrzeiliger Text...',
    wysiwyg: 'Rich Text Editor...',
    upload: 'Datei hochladen...',
    'multi-upload': 'Dateien hochladen...',
    select: 'Auswahl...',
    repeatable: 'Mehrere Einträge...',
  }
  return labels[type] || '...'
}

function onDragOver(e: DragEvent) {
  if (e.dataTransfer?.types.includes('fieldType')) {
    e.dataTransfer.dropEffect = 'copy'
  }
}

function onDrop(e: DragEvent) {
  const type = e.dataTransfer?.getData('fieldType') as FieldType
  if (!type || !canvas.value) return

  const rect = canvas.value.getBoundingClientRect()
  const x = e.clientX - rect.left + (canvasContainer.value?.scrollLeft || 0)
  const y = e.clientY - rect.top + (canvasContainer.value?.scrollTop || 0)
  emit('dropNewField', type, Math.max(0, x - 125), Math.max(0, y - 30))
}

// Dragging logic
let dragState: { fieldId: string; startX: number; startY: number; origX: number; origY: number } | null = null

function startDrag(e: MouseEvent, field: FieldDefinition) {
  emit('selectField', field.id)
  dragState = {
    fieldId: field.id,
    startX: e.clientX,
    startY: e.clientY,
    origX: field.position.x,
    origY: field.position.y,
  }

  const onMove = (ev: MouseEvent) => {
    if (!dragState) return
    const dx = ev.clientX - dragState.startX
    const dy = ev.clientY - dragState.startY
    emit('updateFieldPosition', dragState.fieldId, Math.max(0, dragState.origX + dx), Math.max(0, dragState.origY + dy))
  }

  const onUp = () => {
    dragState = null
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}

// Resize logic
let resizeState: { fieldId: string; startX: number; startY: number; origW: number; origH: number } | null = null

function startResize(e: MouseEvent, field: FieldDefinition) {
  resizeState = {
    fieldId: field.id,
    startX: e.clientX,
    startY: e.clientY,
    origW: field.size.width,
    origH: field.size.height,
  }

  const onMove = (ev: MouseEvent) => {
    if (!resizeState) return
    const dw = ev.clientX - resizeState.startX
    const dh = ev.clientY - resizeState.startY
    emit('updateFieldSize', resizeState.fieldId, Math.max(100, resizeState.origW + dw), Math.max(40, resizeState.origH + dh))
  }

  const onUp = () => {
    resizeState = null
    document.removeEventListener('mousemove', onMove)
    document.removeEventListener('mouseup', onUp)
  }

  document.addEventListener('mousemove', onMove)
  document.addEventListener('mouseup', onUp)
}
</script>

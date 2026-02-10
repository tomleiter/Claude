<template>
  <div>
    <!-- Current files -->
    <div v-if="files.length" class="space-y-2 mb-2">
      <div
        v-for="(file, idx) in files"
        :key="idx"
        class="flex items-center gap-2 bg-gray-50 rounded p-2 text-sm"
      >
        <!-- Preview for images -->
        <img
          v-if="isImage(file)"
          :src="file.url"
          class="w-10 h-10 object-cover rounded"
        />
        <svg v-else class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
        <div class="flex-1 min-w-0">
          <p class="truncate">{{ file.name }}</p>
          <p class="text-xs text-gray-500">{{ formatSize(file.size) }}</p>
        </div>
        <button
          v-if="!disabled"
          class="text-red-500 hover:text-red-700"
          @click="removeFile(idx)"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Upload area -->
    <div
      v-if="!disabled && (multiple || files.length === 0)"
      class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-colors"
      @click="triggerUpload"
      @dragover.prevent
      @drop.prevent="onDrop"
    >
      <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
      </svg>
      <p class="text-sm text-gray-500">
        {{ uploading ? 'Hochladen...' : 'Klicken oder Datei hierher ziehen' }}
      </p>
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        :multiple="multiple"
        accept="image/*,.pdf,.doc,.docx,.txt"
        @change="onFileSelect"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useApi } from '../composables/useApi'
import type { UploadedFile } from '../types'

const props = defineProps<{
  modelValue: UploadedFile | UploadedFile[] | null | undefined
  multiple: boolean
  disabled: boolean
  token?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: UploadedFile | UploadedFile[] | null]
}>()

const api = useApi()
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

const files = computed<UploadedFile[]>(() => {
  if (!props.modelValue) return []
  return Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue]
})

function triggerUpload() {
  fileInput.value?.click()
}

async function onFileSelect(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files?.length) return
  await uploadFiles(Array.from(input.files))
  input.value = ''
}

async function onDrop(e: DragEvent) {
  const droppedFiles = Array.from(e.dataTransfer?.files || [])
  if (droppedFiles.length) await uploadFiles(droppedFiles)
}

async function uploadFiles(filesToUpload: File[]) {
  uploading.value = true
  try {
    const uploadFn = props.token ? api.publicUpload : api.upload
    const uploaded: UploadedFile[] = []
    for (const file of filesToUpload) {
      const result = await uploadFn(file)
      uploaded.push(result)
    }

    if (props.multiple) {
      emit('update:modelValue', [...files.value, ...uploaded])
    } else {
      emit('update:modelValue', uploaded[0])
    }
  } catch (e) {
    console.error('Upload failed:', e)
  } finally {
    uploading.value = false
  }
}

function removeFile(idx: number) {
  if (props.multiple) {
    const newFiles = [...files.value]
    newFiles.splice(idx, 1)
    emit('update:modelValue', newFiles)
  } else {
    emit('update:modelValue', null)
  }
}

function isImage(file: UploadedFile): boolean {
  return /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(file.url || file.name)
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

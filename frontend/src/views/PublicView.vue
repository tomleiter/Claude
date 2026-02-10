<template>
  <div class="h-screen flex flex-col">
    <!-- Top bar -->
    <header class="bg-white shadow-sm border-b px-4 py-3 flex items-center justify-between flex-shrink-0">
      <h1 class="text-lg font-bold text-gray-800">Formular</h1>
      <div class="flex items-center gap-3">
        <CompletionBadge v-if="currentFolderData?.completion" :completion="currentFolderData.completion" />
        <button v-if="currentFolderData && !currentFolderData.released" @click="saveData" class="btn-secondary btn-sm" :disabled="saving">
          {{ saving ? 'Speichern...' : 'Zwischenspeichern' }}
        </button>
        <button
          v-if="currentFolderData && !currentFolderData.released && canRelease"
          @click="releaseForm"
          class="btn-success btn-sm"
        >
          Freigeben
        </button>
        <span v-if="currentFolderData?.released" class="text-green-600 font-semibold text-sm">
          Freigegeben
        </span>
      </div>
    </header>

    <div v-if="error" class="bg-red-50 text-red-700 px-4 py-2 text-sm">{{ error }}</div>
    <div v-if="success" class="bg-green-50 text-green-700 px-4 py-2 text-sm">{{ success }}</div>

    <div class="flex flex-1 overflow-hidden">
      <!-- Left: Folder tree -->
      <aside class="w-64 bg-white border-r overflow-y-auto flex-shrink-0">
        <div class="p-3 border-b">
          <span class="font-semibold text-sm">Ordner</span>
        </div>
        <div v-if="tree" class="p-2">
          <PublicFolderNode
            :node="tree"
            :selected-id="selectedFolderId"
            :depth="0"
            @select="selectFolder"
          />
        </div>
      </aside>

      <!-- Center: Form fields -->
      <main class="flex-1 overflow-y-auto p-6">
        <div v-if="!selectedFolderId" class="flex items-center justify-center h-full text-gray-400">
          Ordner auswählen um das Formular auszufüllen
        </div>
        <div v-else-if="currentFolderData">
          <h2 class="text-xl font-bold mb-6">{{ selectedFolderName }}</h2>

          <!-- Canvas image preview -->
          <div v-if="currentFolderData.canvasImage" class="mb-6">
            <img :src="currentFolderData.canvasImage" class="max-w-full rounded border" />
          </div>

          <!-- Released notice -->
          <div v-if="currentFolderData.released" class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-green-700 font-medium">Dieses Formular wurde freigegeben am {{ currentFolderData.releasedAt }}.</p>
          </div>

          <!-- Fields -->
          <div class="space-y-6">
            <div
              v-for="field in currentFolderData.fields"
              :key="field.id"
              class="border rounded-lg p-4"
              :class="isFieldMissing(field.id) ? 'border-red-300 bg-red-50/50' : 'border-gray-200'"
            >
              <label class="label">
                {{ field.label }}
                <span v-if="field.required" class="text-red-500">*</span>
                <span v-if="field.validation?.minLength" class="text-gray-400 font-normal text-xs ml-2">
                  (mind. {{ field.validation.minLength }} Zeichen)
                </span>
                <span v-if="field.type === 'repeatable' && field.validation?.minEntries" class="text-gray-400 font-normal text-xs ml-2">
                  (mind. {{ field.validation.minEntries }} Einträge)
                </span>
              </label>

              <!-- Text -->
              <input
                v-if="field.type === 'text'"
                v-model="formData[field.id]"
                class="input"
                :placeholder="field.placeholder"
                :disabled="currentFolderData.released"
              />

              <!-- Textarea -->
              <textarea
                v-else-if="field.type === 'textarea'"
                v-model="formData[field.id]"
                class="input"
                rows="4"
                :placeholder="field.placeholder"
                :disabled="currentFolderData.released"
              ></textarea>

              <!-- WYSIWYG -->
              <div v-else-if="field.type === 'wysiwyg'">
                <QuillEditorWrapper
                  v-model="formData[field.id]"
                  :disabled="currentFolderData.released"
                />
              </div>

              <!-- Select -->
              <select
                v-else-if="field.type === 'select'"
                v-model="formData[field.id]"
                class="input"
                :disabled="currentFolderData.released"
              >
                <option value="">-- Bitte wählen --</option>
                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
              </select>

              <!-- Upload -->
              <FileUpload
                v-else-if="field.type === 'upload'"
                v-model="formData[field.id]"
                :multiple="false"
                :disabled="currentFolderData.released"
                :token="token"
              />

              <!-- Multi Upload -->
              <FileUpload
                v-else-if="field.type === 'multi-upload'"
                v-model="formData[field.id]"
                :multiple="true"
                :disabled="currentFolderData.released"
                :token="token"
              />

              <!-- Repeatable -->
              <RepeatableField
                v-else-if="field.type === 'repeatable'"
                v-model="formData[field.id]"
                :sub-fields="field.subFields || []"
                :disabled="currentFolderData.released"
                :token="token"
              />

              <!-- Validation hint -->
              <p v-if="getValidationHint(field)" class="text-xs text-red-500 mt-1">
                {{ getValidationHint(field) }}
              </p>
            </div>
          </div>

          <!-- Download button -->
          <div class="mt-8 pb-8">
            <button @click="downloadZip" class="btn-secondary">
              Als ZIP herunterladen
            </button>
          </div>
        </div>
      </main>

      <!-- Right sidebar: Status -->
      <aside class="w-72 bg-white border-l overflow-y-auto flex-shrink-0">
        <div class="p-3 border-b">
          <span class="font-semibold text-sm">Status</span>
        </div>
        <div v-if="currentFolderData" class="p-3">
          <!-- Overall progress -->
          <div class="mb-4">
            <div class="flex justify-between text-sm mb-1">
              <span>Gesamtfortschritt</span>
              <span>{{ currentFolderData.completion?.percent ?? 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="progressColor(currentFolderData.completion?.percent ?? 0)"
                :style="{ width: (currentFolderData.completion?.percent ?? 0) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Required fields progress -->
          <div class="mb-4">
            <div class="flex justify-between text-sm mb-1">
              <span>Pflichtfelder</span>
              <span>{{ currentFolderData.completion?.requiredFilled ?? 0 }}/{{ currentFolderData.completion?.requiredTotal ?? 0 }}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
              <div
                class="h-2 rounded-full transition-all"
                :class="progressColor(currentFolderData.completion?.requiredPercent ?? 0)"
                :style="{ width: (currentFolderData.completion?.requiredPercent ?? 0) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Missing fields list -->
          <div v-if="currentFolderData.missingFields?.length" class="mt-4">
            <h4 class="text-sm font-semibold mb-2 text-red-600">Fehlende Pflichtfelder:</h4>
            <ul class="space-y-1">
              <li
                v-for="mf in currentFolderData.missingFields"
                :key="mf.id"
                class="text-sm text-red-600 flex items-center gap-1"
              >
                <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" />
                </svg>
                {{ mf.label }}
              </li>
            </ul>
          </div>

          <div v-else-if="currentFolderData.completion?.requiredTotal" class="mt-4 text-green-600 text-sm font-medium">
            Alle Pflichtfelder ausgefüllt!
          </div>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'
import type { FolderNode, FolderData } from '../types'
import CompletionBadge from '../components/CompletionBadge.vue'
import PublicFolderNode from '../components/PublicFolderNode.vue'
import FileUpload from '../components/FileUpload.vue'
import RepeatableField from '../components/RepeatableField.vue'
import QuillEditorWrapper from '../components/QuillEditorWrapper.vue'

const route = useRoute()
const api = useApi()

const token = computed(() => route.params.token as string)
const tree = ref<FolderNode | null>(null)
const selectedFolderId = ref<string | null>(null)
const currentFolderData = ref<FolderData | null>(null)
const formData = ref<Record<string, any>>({})
const saving = ref(false)
const error = ref('')
const success = ref('')

const selectedFolderName = computed(() => {
  if (!tree.value || !selectedFolderId.value) return ''
  const find = (node: FolderNode): string | null => {
    if (node.id === selectedFolderId.value) return node.name
    for (const child of node.children) {
      const r = find(child)
      if (r) return r
    }
    return null
  }
  return find(tree.value) || ''
})

const canRelease = computed(() => {
  return currentFolderData.value?.completion?.requiredPercent === 100
})

async function loadTree() {
  try {
    tree.value = await api.publicGetTree(token.value)
    // Auto-select first folder
    if (tree.value && !selectedFolderId.value) {
      selectedFolderId.value = tree.value.id
    }
  } catch (e: any) {
    error.value = 'Ungültiger oder abgelaufener Link'
  }
}

async function loadFolderData(id: string) {
  try {
    currentFolderData.value = await api.publicGetFolder(token.value, id)
    formData.value = { ...(currentFolderData.value?.data || {}) }
  } catch (e: any) {
    error.value = e.message
  }
}

function selectFolder(id: string) {
  // Save current before switching
  if (selectedFolderId.value && currentFolderData.value && !currentFolderData.value.released) {
    saveData()
  }
  selectedFolderId.value = id
}

watch(selectedFolderId, (id) => {
  if (id) loadFolderData(id)
})

async function saveData() {
  if (!selectedFolderId.value) return
  saving.value = true
  error.value = ''
  try {
    currentFolderData.value = await api.publicSaveData(token.value, selectedFolderId.value, formData.value)
    success.value = 'Gespeichert!'
    setTimeout(() => success.value = '', 2000)
    // Reload tree to update completion
    tree.value = await api.publicGetTree(token.value)
  } catch (e: any) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function releaseForm() {
  if (!selectedFolderId.value) return
  if (!confirm('Formular wirklich freigeben? Dies kann nicht rückgängig gemacht werden.')) return

  // Save first
  await saveData()

  try {
    currentFolderData.value = await api.publicRelease(token.value, selectedFolderId.value)
    success.value = 'Formular wurde freigegeben! Eine E-Mail wurde versendet.'
  } catch (e: any) {
    error.value = e.message
  }
}

async function downloadZip() {
  if (!selectedFolderId.value) return
  try {
    await api.publicDownloadZip(token.value, selectedFolderId.value)
  } catch (e: any) {
    error.value = e.message
  }
}

function isFieldMissing(fieldId: string): boolean {
  return currentFolderData.value?.missingFields?.some(f => f.id === fieldId) || false
}

function getValidationHint(field: any): string {
  const value = formData.value[field.id]
  if (!field.required) return ''

  if (field.validation?.minLength && typeof value === 'string') {
    const len = value.replace(/<[^>]*>/g, '').length
    if (len > 0 && len < field.validation.minLength) {
      return `Noch ${field.validation.minLength - len} Zeichen benötigt`
    }
  }

  if (field.type === 'repeatable' && field.validation?.minEntries) {
    const entries = Array.isArray(value) ? value.length : 0
    if (entries > 0 && entries < field.validation.minEntries) {
      return `Noch ${field.validation.minEntries - entries} Einträge benötigt`
    }
  }

  return ''
}

function progressColor(percent: number): string {
  if (percent === 100) return 'progress-green'
  if (percent >= 75) return 'progress-yellow'
  if (percent >= 50) return 'progress-orange'
  return 'progress-red'
}

loadTree()
</script>

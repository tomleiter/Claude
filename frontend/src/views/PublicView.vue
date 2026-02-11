<template>
  <div class="h-screen flex flex-col">
    <!-- Confetti -->
    <ConfettiEffect :active="showConfetti" />
    <!-- Achievement Toasts -->
    <AchievementToast />

    <!-- Top bar -->
    <header class="bg-white shadow-sm border-b px-4 py-3 flex items-center gap-4 flex-shrink-0">
      <h1 class="text-lg font-bold text-gray-800">Formular</h1>

      <!-- XP Bar -->
      <div class="flex-1 max-w-xs">
        <XpBar />
      </div>

      <div class="flex items-center gap-3">
        <AchievementPanel />
        <ProgressRing
          v-if="currentFolderData?.completion"
          :percent="currentFolderData.completion.requiredPercent"
          :size="44"
          :stroke-width="4"
        />
        <button v-if="currentFolderData && !currentFolderData.released" @click="saveData" class="btn-secondary btn-sm" :disabled="saving">
          {{ saving ? 'Speichern...' : 'Zwischenspeichern' }}
        </button>
        <button
          v-if="currentFolderData && !currentFolderData.released && canRelease"
          @click="releaseForm"
          class="btn-success btn-sm animate-pulse-glow"
        >
          Freigeben
        </button>
        <span v-if="currentFolderData?.released" class="text-green-600 font-semibold text-sm flex items-center gap-1">
          <span class="text-lg">🏆</span> Freigegeben
        </span>
      </div>
    </header>

    <div v-if="error" class="bg-red-50 text-red-700 px-4 py-2 text-sm animate-slide-down">{{ error }}</div>
    <div v-if="success" class="bg-green-50 text-green-700 px-4 py-2 text-sm animate-slide-down flex items-center gap-2">
      <span class="text-lg">{{ successEmoji }}</span> {{ success }}
    </div>

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
      <main ref="mainContent" class="flex-1 overflow-y-auto p-6">
        <div v-if="!selectedFolderId" class="flex flex-col items-center justify-center h-full text-gray-400">
          <MascotIllustration :percent="0" :size="220" />
          <p class="text-lg mt-4">Ordner auswählen um das Formular auszufüllen</p>
          <p class="text-sm mt-1 text-gray-400">Jedes Feld bringt dir XP und schaltet Achievements frei!</p>
        </div>
        <div v-else-if="currentFolderData">
          <h2 class="text-xl font-bold mb-2">{{ selectedFolderName }}</h2>

          <!-- Motivation message -->
          <p class="text-sm text-gray-500 mb-6 italic">{{ motivation }}</p>

          <!-- Canvas image preview -->
          <div v-if="currentFolderData.canvasImage" class="mb-6">
            <img :src="currentFolderData.canvasImage" class="max-w-full rounded border" />
          </div>

          <!-- Released notice -->
          <div v-if="currentFolderData.released" class="mb-6 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-xl p-6 text-center">
            <span class="text-4xl block mb-2">🎉</span>
            <p class="text-green-700 font-bold text-lg">Formular erfolgreich freigegeben!</p>
            <p class="text-green-600 text-sm">{{ currentFolderData.releasedAt }}</p>
          </div>

          <!-- Fields -->
          <div class="space-y-6">
            <TransitionGroup name="field-complete">
              <div
                v-for="(field, fieldIndex) in currentFolderData.fields"
                :key="field.id"
                class="border rounded-lg p-4 transition-all duration-300"
                :class="fieldCardClass(field)"
              >
                <div class="flex items-center gap-2 mb-2">
                  <!-- Field number badge -->
                  <span
                    class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                    :class="isFieldComplete(field) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                  >
                    {{ isFieldComplete(field) ? '✓' : fieldIndex + 1 }}
                  </span>

                  <label class="label mb-0 flex-1">
                    {{ field.label }}
                    <span v-if="field.required" class="text-red-500">*</span>
                    <span v-if="field.validation?.minLength" class="text-gray-400 font-normal text-xs ml-2">
                      (mind. {{ field.validation.minLength }} Zeichen)
                    </span>
                    <span v-if="field.type === 'repeatable' && field.validation?.minEntries" class="text-gray-400 font-normal text-xs ml-2">
                      (mind. {{ field.validation.minEntries }} Einträge)
                    </span>
                  </label>

                  <!-- XP indicator on complete -->
                  <Transition name="xp-pop">
                    <span v-if="justCompleted[field.id]" class="text-green-600 font-bold text-sm animate-bounce-in">
                      +10 XP
                    </span>
                  </Transition>
                </div>

                <!-- Text -->
                <input
                  v-if="field.type === 'text'"
                  v-model="formData[field.id]"
                  class="input"
                  :placeholder="field.placeholder"
                  :disabled="currentFolderData.released"
                  @blur="onFieldChange(field)"
                />

                <!-- Textarea -->
                <textarea
                  v-else-if="field.type === 'textarea'"
                  v-model="formData[field.id]"
                  class="input"
                  rows="4"
                  :placeholder="field.placeholder"
                  :disabled="currentFolderData.released"
                  @blur="onFieldChange(field)"
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
                  @change="onFieldChange(field)"
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
                  @update:model-value="onUploadChange(field)"
                />

                <!-- Multi Upload -->
                <FileUpload
                  v-else-if="field.type === 'multi-upload'"
                  v-model="formData[field.id]"
                  :multiple="true"
                  :disabled="currentFolderData.released"
                  :token="token"
                  @update:model-value="onUploadChange(field)"
                />

                <!-- Repeatable -->
                <RepeatableField
                  v-else-if="field.type === 'repeatable'"
                  v-model="formData[field.id]"
                  :sub-fields="field.subFields || []"
                  :disabled="currentFolderData.released"
                  :token="token"
                  @update:model-value="onFieldChange(field)"
                />

                <!-- Char counter for text fields -->
                <div v-if="['text', 'textarea', 'wysiwyg'].includes(field.type) && formData[field.id]" class="mt-1 flex items-center gap-2">
                  <div class="flex-1 bg-gray-100 rounded-full h-1">
                    <div
                      class="h-1 rounded-full transition-all duration-300"
                      :class="charProgressColor(field)"
                      :style="{ width: Math.min(charPercent(field), 100) + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs text-gray-400">{{ charCount(field) }} Zeichen</span>
                </div>

                <!-- Validation hint -->
                <p v-if="getValidationHint(field)" class="text-xs text-red-500 mt-1">
                  {{ getValidationHint(field) }}
                </p>
              </div>
            </TransitionGroup>
          </div>

          <!-- Download button -->
          <div class="mt-8 pb-8">
            <button @click="downloadZip" class="btn-secondary flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Als ZIP herunterladen
            </button>
          </div>
        </div>
      </main>

      <!-- Right sidebar: Status + Gamification -->
      <aside class="w-80 bg-white border-l overflow-y-auto flex-shrink-0">
        <div class="p-3 border-b bg-gradient-to-r from-indigo-50 to-purple-50">
          <span class="font-semibold text-sm">Status & Fortschritt</span>
        </div>
        <div v-if="currentFolderData" class="p-3 space-y-4">
          <!-- Mascot + progress -->
          <div class="flex flex-col items-center py-2">
            <MascotIllustration
              :percent="currentFolderData.completion?.percent ?? 0"
              :size="150"
            />
            <div class="mt-3">
              <ProgressRing
                :percent="currentFolderData.completion?.percent ?? 0"
                :size="90"
                :stroke-width="6"
                label="Gesamt"
              />
            </div>
          </div>

          <!-- Streak counter -->
          <StreakCounter ref="streakRef" />

          <!-- Stats grid -->
          <div class="grid grid-cols-2 gap-2">
            <div class="bg-blue-50 rounded-lg p-3 text-center">
              <div class="text-2xl font-black text-blue-600">{{ currentFolderData.completion?.filled ?? 0 }}</div>
              <div class="text-xs text-blue-500">Felder</div>
            </div>
            <div class="bg-green-50 rounded-lg p-3 text-center">
              <div class="text-2xl font-black text-green-600">{{ currentFolderData.completion?.requiredFilled ?? 0 }}/{{ currentFolderData.completion?.requiredTotal ?? 0 }}</div>
              <div class="text-xs text-green-500">Pflicht</div>
            </div>
          </div>

          <!-- Required fields progress -->
          <div>
            <div class="flex justify-between text-sm mb-1">
              <span class="font-medium">Pflichtfelder</span>
              <span class="font-bold" :class="requiredColor">{{ currentFolderData.completion?.requiredPercent ?? 0 }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
              <div
                class="h-3 rounded-full transition-all duration-700"
                :class="progressColor(currentFolderData.completion?.requiredPercent ?? 0)"
                :style="{ width: (currentFolderData.completion?.requiredPercent ?? 0) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Missing fields list -->
          <div v-if="currentFolderData.missingFields?.length" class="mt-2">
            <h4 class="text-sm font-semibold mb-2 text-red-600 flex items-center gap-1">
              <span>⚠️</span> Noch offen:
            </h4>
            <ul class="space-y-1.5">
              <li
                v-for="mf in currentFolderData.missingFields"
                :key="mf.id"
                class="text-sm flex items-center gap-2 p-2 bg-red-50 rounded-lg border border-red-100 cursor-pointer hover:bg-red-100 transition-colors"
                @click="scrollToField(mf.id)"
              >
                <span class="w-5 h-5 rounded-full bg-red-200 flex items-center justify-center flex-shrink-0">
                  <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" />
                  </svg>
                </span>
                <span class="text-red-700">{{ mf.label }}</span>
              </li>
            </ul>
          </div>

          <!-- All complete celebration -->
          <div v-else-if="currentFolderData.completion?.requiredTotal" class="text-center py-4">
            <span class="text-4xl block mb-2 animate-float">🎉</span>
            <p class="text-green-600 font-bold">Alle Pflichtfelder erledigt!</p>
            <p class="text-xs text-gray-500 mt-1">Du kannst jetzt freigeben</p>
          </div>

          <!-- Recent achievements teaser -->
          <div class="border-t pt-3">
            <h4 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Letzte Achievements</h4>
            <div class="flex flex-wrap gap-1">
              <span
                v-for="a in recentAchievements"
                :key="a.id"
                class="text-xl cursor-help"
                :title="a.title + ': ' + a.description"
              >
                {{ a.icon }}
              </span>
              <span v-if="!recentAchievements.length" class="text-xs text-gray-400">
                Noch keine - fülle Felder aus um Achievements freizuschalten!
              </span>
            </div>
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
import { useGamification } from '../composables/useGamification'
import type { FolderNode, FolderData, FieldDefinition } from '../types'
import PublicFolderNode from '../components/PublicFolderNode.vue'
import FileUpload from '../components/FileUpload.vue'
import RepeatableField from '../components/RepeatableField.vue'
import QuillEditorWrapper from '../components/QuillEditorWrapper.vue'
import ProgressRing from '../components/ProgressRing.vue'
import XpBar from '../components/XpBar.vue'
import AchievementPanel from '../components/AchievementPanel.vue'
import AchievementToast from '../components/AchievementToast.vue'
import ConfettiEffect from '../components/ConfettiEffect.vue'
import StreakCounter from '../components/StreakCounter.vue'
import MascotIllustration from '../components/MascotIllustration.vue'

const route = useRoute()
const api = useApi()
const gamification = useGamification()

const token = computed(() => route.params.token as string)
const tree = ref<FolderNode | null>(null)
const selectedFolderId = ref<string | null>(null)
const currentFolderData = ref<FolderData | null>(null)
const formData = ref<Record<string, any>>({})
const saving = ref(false)
const error = ref('')
const success = ref('')
const successEmoji = ref('✅')
const showConfetti = ref(false)
const motivation = ref(gamification.getMotivation())
const justCompleted = ref<Record<string, boolean>>({})
const previouslyFilled = ref<Set<string>>(new Set())
const mainContent = ref<HTMLElement | null>(null)
const streakRef = ref<InstanceType<typeof StreakCounter> | null>(null)

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

const requiredColor = computed(() => {
  const p = currentFolderData.value?.completion?.requiredPercent ?? 0
  if (p === 100) return 'text-green-600'
  if (p >= 75) return 'text-yellow-600'
  return 'text-red-600'
})

const recentAchievements = computed(() => {
  return gamification.state.achievements
    .filter(a => a.unlockedAt)
    .sort((a, b) => new Date(b.unlockedAt!).getTime() - new Date(a.unlockedAt!).getTime())
    .slice(0, 5)
})

function isFieldComplete(field: FieldDefinition): boolean {
  const v = formData.value[field.id]
  if (v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0)) return false
  return true
}

function fieldCardClass(field: FieldDefinition): string {
  if (isFieldComplete(field)) return 'border-green-200 bg-green-50/30'
  if (isFieldMissing(field.id)) return 'border-red-300 bg-red-50/50'
  return 'border-gray-200'
}

function onFieldChange(field: FieldDefinition) {
  const wasEmpty = !previouslyFilled.value.has(field.id)
  const nowFilled = isFieldComplete(field)

  if (wasEmpty && nowFilled) {
    previouslyFilled.value.add(field.id)
    gamification.trackFieldFilled()
    streakRef.value?.recordAction()

    // Show +XP animation
    justCompleted.value[field.id] = true
    setTimeout(() => { justCompleted.value[field.id] = false }, 1500)
  }

  // Check completion
  if (currentFolderData.value?.completion) {
    gamification.trackCompletion(
      currentFolderData.value.completion.percent,
      currentFolderData.value.completion.requiredPercent
    )
  }
}

function onUploadChange(field: FieldDefinition) {
  gamification.trackUpload()
  onFieldChange(field)
}

function charCount(field: FieldDefinition): number {
  const v = formData.value[field.id]
  if (!v) return 0
  return String(v).replace(/<[^>]*>/g, '').length
}

function charPercent(field: FieldDefinition): number {
  const min = field.validation?.minLength
  if (!min) return 100
  return (charCount(field) / min) * 100
}

function charProgressColor(field: FieldDefinition): string {
  const p = charPercent(field)
  if (p >= 100) return 'bg-green-500'
  if (p >= 50) return 'bg-yellow-500'
  return 'bg-red-400'
}

function scrollToField(fieldId: string) {
  const el = mainContent.value?.querySelector(`[data-field-id="${fieldId}"]`) ||
    mainContent.value?.querySelector(`[key="${fieldId}"]`)
  el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

async function loadTree() {
  try {
    tree.value = await api.publicGetTree(token.value)
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
    motivation.value = gamification.getMotivation()
    // Track already filled fields
    previouslyFilled.value = new Set(
      currentFolderData.value?.fields
        ?.filter(f => isFieldComplete(f))
        .map(f => f.id) || []
    )
  } catch (e: any) {
    error.value = e.message
  }
}

function selectFolder(id: string) {
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
    gamification.trackSave()
    successEmoji.value = '💾'
    success.value = 'Gespeichert! +5 XP'
    setTimeout(() => success.value = '', 2000)
    tree.value = await api.publicGetTree(token.value)

    // Track completion after save
    if (currentFolderData.value?.completion) {
      gamification.trackCompletion(
        currentFolderData.value.completion.percent,
        currentFolderData.value.completion.requiredPercent
      )
    }
  } catch (e: any) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

async function releaseForm() {
  if (!selectedFolderId.value) return
  if (!confirm('Formular wirklich freigeben? Dies kann nicht rückgängig gemacht werden.')) return

  await saveData()

  try {
    currentFolderData.value = await api.publicRelease(token.value, selectedFolderId.value)
    gamification.trackRelease()
    showConfetti.value = true
    setTimeout(() => { showConfetti.value = false }, 5000)
    successEmoji.value = '🚀'
    success.value = 'Formular freigegeben! +500 XP - E-Mail wurde versendet.'
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

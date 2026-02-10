<template>
  <div class="space-y-3">
    <!-- Entries -->
    <div
      v-for="(entry, idx) in entries"
      :key="idx"
      class="border rounded-lg p-3 bg-gray-50"
    >
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium">Eintrag {{ idx + 1 }}</span>
        <button
          v-if="!disabled"
          class="text-red-500 text-sm hover:text-red-700"
          @click="removeEntry(idx)"
        >
          Entfernen
        </button>
      </div>

      <div class="space-y-2">
        <div v-for="sf in subFields" :key="sf.id">
          <label class="text-xs font-medium text-gray-600">{{ sf.label }}</label>

          <!-- Text -->
          <input
            v-if="sf.type === 'text'"
            v-model="entry[sf.id]"
            class="input text-sm"
            :placeholder="sf.placeholder"
            :disabled="disabled"
            @change="emitUpdate"
          />

          <!-- Textarea -->
          <textarea
            v-else-if="sf.type === 'textarea'"
            v-model="entry[sf.id]"
            class="input text-sm"
            rows="3"
            :placeholder="sf.placeholder"
            :disabled="disabled"
            @change="emitUpdate"
          ></textarea>

          <!-- Select -->
          <select
            v-else-if="sf.type === 'select'"
            v-model="entry[sf.id]"
            class="input text-sm"
            :disabled="disabled"
            @change="emitUpdate"
          >
            <option value="">-- Wählen --</option>
            <option v-for="opt in sf.options" :key="opt" :value="opt">{{ opt }}</option>
          </select>

          <!-- Upload -->
          <FileUpload
            v-else-if="sf.type === 'upload'"
            v-model="entry[sf.id]"
            :multiple="false"
            :disabled="disabled"
            :token="token"
            @update:model-value="emitUpdate"
          />

          <!-- Multi Upload -->
          <FileUpload
            v-else-if="sf.type === 'multi-upload'"
            v-model="entry[sf.id]"
            :multiple="true"
            :disabled="disabled"
            :token="token"
            @update:model-value="emitUpdate"
          />
        </div>
      </div>
    </div>

    <!-- Add button -->
    <button
      v-if="!disabled"
      class="btn-secondary btn-sm w-full"
      @click="addEntry"
    >
      + Eintrag hinzufügen
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { SubFieldDefinition } from '../types'
import FileUpload from './FileUpload.vue'

const props = defineProps<{
  modelValue: any[] | undefined | null
  subFields: SubFieldDefinition[]
  disabled: boolean
  token?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: any[]]
}>()

const entries = ref<any[]>(props.modelValue ? JSON.parse(JSON.stringify(props.modelValue)) : [])

watch(() => props.modelValue, (v) => {
  entries.value = v ? JSON.parse(JSON.stringify(v)) : []
}, { deep: true })

function addEntry() {
  const newEntry: Record<string, any> = {}
  for (const sf of props.subFields) {
    newEntry[sf.id] = sf.type === 'multi-upload' ? [] : ''
  }
  entries.value.push(newEntry)
  emitUpdate()
}

function removeEntry(idx: number) {
  entries.value.splice(idx, 1)
  emitUpdate()
}

function emitUpdate() {
  emit('update:modelValue', JSON.parse(JSON.stringify(entries.value)))
}
</script>

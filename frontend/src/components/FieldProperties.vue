<template>
  <div class="space-y-3">
    <!-- Label -->
    <div>
      <label class="label">Bezeichnung</label>
      <input v-model="local.label" class="input" @change="emitUpdate" />
    </div>

    <!-- Type (read only) -->
    <div>
      <label class="label">Typ</label>
      <div class="text-sm bg-gray-100 rounded px-3 py-2">{{ typeLabel }}</div>
    </div>

    <!-- Required -->
    <div class="flex items-center gap-2">
      <input type="checkbox" v-model="local.required" id="req" @change="emitUpdate" class="rounded" />
      <label for="req" class="text-sm">Pflichtfeld</label>
    </div>

    <!-- Placeholder -->
    <div v-if="['text', 'textarea'].includes(local.type)">
      <label class="label">Platzhalter</label>
      <input v-model="local.placeholder" class="input" @change="emitUpdate" />
    </div>

    <!-- Validation: Min Length -->
    <div v-if="['text', 'textarea', 'wysiwyg'].includes(local.type)">
      <label class="label">Mindestzeichenanzahl</label>
      <input v-model.number="local.validation.minLength" type="number" class="input" min="0" @change="emitUpdate" />
    </div>

    <!-- Validation: Max Length -->
    <div v-if="['text', 'textarea', 'wysiwyg'].includes(local.type)">
      <label class="label">Maximalzeichenanzahl</label>
      <input v-model.number="local.validation.maxLength" type="number" class="input" min="0" @change="emitUpdate" />
    </div>

    <!-- Select options -->
    <div v-if="local.type === 'select'">
      <label class="label">Optionen (eine pro Zeile)</label>
      <textarea v-model="optionsText" class="input" rows="4" @change="updateOptions"></textarea>
    </div>

    <!-- Repeatable: Sub-fields -->
    <div v-if="local.type === 'repeatable'">
      <label class="label">Mindesteinträge</label>
      <input v-model.number="local.validation.minEntries" type="number" class="input" min="0" @change="emitUpdate" />
    </div>

    <div v-if="local.type === 'repeatable'">
      <label class="label">Unterfelder</label>
      <div class="space-y-2 mt-1">
        <div v-for="(sf, idx) in local.subFields" :key="sf.id" class="border rounded p-2 bg-gray-50">
          <div class="flex items-center gap-2 mb-1">
            <input v-model="sf.label" class="input text-xs flex-1" @change="emitUpdate" />
            <select v-model="sf.type" class="input text-xs w-24" @change="emitUpdate">
              <option value="text">Text</option>
              <option value="textarea">Textarea</option>
              <option value="select">Auswahl</option>
              <option value="upload">Upload</option>
              <option value="multi-upload">Multi-Upload</option>
            </select>
            <button class="text-red-500 text-xs" @click="removeSubField(idx)">X</button>
          </div>
          <div v-if="sf.type === 'select'" class="mt-1">
            <textarea
              :value="(sf.options || []).join('\n')"
              class="input text-xs"
              rows="2"
              placeholder="Optionen (eine pro Zeile)"
              @change="updateSubFieldOptions(idx, ($event.target as HTMLTextAreaElement).value)"
            ></textarea>
          </div>
        </div>
        <button class="btn-secondary btn-sm w-full" @click="addSubField">+ Unterfeld</button>
      </div>
    </div>

    <!-- Position -->
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="label">X</label>
        <input v-model.number="local.position.x" type="number" class="input" @change="emitUpdate" />
      </div>
      <div>
        <label class="label">Y</label>
        <input v-model.number="local.position.y" type="number" class="input" @change="emitUpdate" />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="label">Breite</label>
        <input v-model.number="local.size.width" type="number" class="input" @change="emitUpdate" />
      </div>
      <div>
        <label class="label">Höhe</label>
        <input v-model.number="local.size.height" type="number" class="input" @change="emitUpdate" />
      </div>
    </div>

    <!-- Delete -->
    <button @click="$emit('delete', local.id)" class="btn-danger btn-sm w-full mt-4">Feld löschen</button>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import type { FieldDefinition } from '../types'

const props = defineProps<{
  field: FieldDefinition
}>()

const emit = defineEmits<{
  update: [field: FieldDefinition]
  delete: [fieldId: string]
}>()

const local = ref<FieldDefinition>(JSON.parse(JSON.stringify(props.field)))

watch(() => props.field, (f) => {
  local.value = JSON.parse(JSON.stringify(f))
}, { deep: true })

const typeLabel = computed(() => {
  const labels: Record<string, string> = {
    text: 'Textfeld',
    textarea: 'Textarea',
    wysiwyg: 'WYSIWYG Editor',
    upload: 'Datei-Upload',
    'multi-upload': 'Multi-Upload',
    select: 'Auswahlfeld',
    repeatable: 'Wiederholbare Referenzen',
  }
  return labels[local.value.type] || local.value.type
})

const optionsText = ref((props.field.options || []).join('\n'))

watch(() => props.field.options, (opts) => {
  optionsText.value = (opts || []).join('\n')
})

function updateOptions() {
  local.value.options = optionsText.value.split('\n').filter(Boolean)
  emitUpdate()
}

function emitUpdate() {
  emit('update', JSON.parse(JSON.stringify(local.value)))
}

function addSubField() {
  if (!local.value.subFields) local.value.subFields = []
  local.value.subFields.push({
    id: 'sf_' + Date.now().toString(36),
    type: 'text',
    label: 'Neues Feld',
  })
  emitUpdate()
}

function removeSubField(idx: number) {
  local.value.subFields?.splice(idx, 1)
  emitUpdate()
}

function updateSubFieldOptions(idx: number, text: string) {
  if (local.value.subFields?.[idx]) {
    local.value.subFields[idx].options = text.split('\n').filter(Boolean)
    emitUpdate()
  }
}
</script>

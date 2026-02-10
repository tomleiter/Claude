<template>
  <div>
    <div v-if="quillLoaded">
      <QuillEditor
        :content="modelValue || ''"
        content-type="html"
        theme="snow"
        :read-only="disabled"
        @update:content="onUpdate"
      />
    </div>
    <div v-else>
      <textarea
        :value="modelValue"
        class="input"
        rows="5"
        :disabled="disabled"
        @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        placeholder="Rich Text Editor wird geladen..."
      ></textarea>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, shallowRef } from 'vue'

defineProps<{
  modelValue: string | undefined
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const quillLoaded = ref(false)
const QuillEditor = shallowRef<any>(null)

onMounted(async () => {
  try {
    const module = await import('@vueup/vue-quill')
    QuillEditor.value = module.QuillEditor
    // Load styles
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://cdn.jsdelivr.net/npm/@vueup/vue-quill@1.2.0/dist/vue-quill.snow.css'
    document.head.appendChild(link)
    quillLoaded.value = true
  } catch {
    // Fallback to textarea
  }
})

function onUpdate(content: string) {
  emit('update:modelValue', content)
}
</script>

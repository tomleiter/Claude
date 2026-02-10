<template>
  <div class="h-screen flex flex-col">
    <!-- Top bar -->
    <header class="bg-white shadow-sm border-b px-4 py-3 flex items-center justify-between flex-shrink-0">
      <h1 class="text-lg font-bold text-gray-800">Form Builder - Admin</h1>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">Admin</span>
        <button @click="logout" class="btn-secondary btn-sm">Abmelden</button>
      </div>
    </header>

    <div class="flex flex-1 overflow-hidden">
      <!-- Left sidebar: Folder tree -->
      <aside class="w-72 bg-white border-r flex flex-col flex-shrink-0">
        <div class="p-3 border-b flex items-center justify-between">
          <span class="font-semibold text-sm">Ordnerstruktur</span>
          <button @click="createFolder('root')" class="btn-primary btn-sm" title="Neuer Ordner">+ Ordner</button>
        </div>
        <div class="flex-1 overflow-y-auto p-2">
          <FolderTree
            v-if="tree"
            :node="tree"
            :selected-id="selectedFolderId"
            :depth="0"
            @select="selectFolder"
            @create="createFolder"
            @rename="renameFolder"
            @delete="deleteFolder"
            @copy="copyFolder"
            @move="moveFolder"
            @share="shareFolder"
            @delete-share="deleteShareToken"
            @download="downloadFolder"
          />
        </div>
      </aside>

      <!-- Main content: Canvas + fields -->
      <main class="flex-1 overflow-hidden flex flex-col bg-gray-50">
        <div v-if="!selectedFolderId" class="flex-1 flex items-center justify-center text-gray-400">
          Ordner auswählen um Felder zu definieren
        </div>
        <template v-else>
          <!-- Toolbar -->
          <div class="p-3 border-b bg-white flex items-center gap-3 flex-shrink-0">
            <span class="font-semibold text-sm">{{ selectedFolderName }}</span>
            <div class="flex-1"></div>
            <label class="btn-secondary btn-sm cursor-pointer">
              Hintergrundbild
              <input type="file" accept="image/*" class="hidden" @change="uploadCanvasImage" />
            </label>
            <button v-if="folderData?.canvasImage" @click="removeCanvasImage" class="btn-secondary btn-sm">
              Bild entfernen
            </button>
          </div>

          <!-- Canvas area -->
          <div class="flex-1 flex overflow-hidden">
            <FormCanvas
              :folder-data="folderData"
              :selected-field-id="selectedFieldId"
              @select-field="selectField"
              @update-field-position="updateFieldPosition"
              @update-field-size="updateFieldSize"
              @drop-new-field="addFieldToCanvas"
            />

            <!-- Right sidebar: Field properties -->
            <aside class="w-80 bg-white border-l flex flex-col flex-shrink-0 overflow-y-auto">
              <!-- Field palette -->
              <div class="p-3 border-b">
                <h3 class="font-semibold text-sm mb-2">Felder (Drag & Drop)</h3>
                <div class="grid grid-cols-2 gap-2">
                  <div
                    v-for="ft in fieldTypes"
                    :key="ft.type"
                    class="border rounded p-2 text-xs text-center cursor-grab bg-gray-50 hover:bg-blue-50 hover:border-blue-300"
                    draggable="true"
                    @dragstart="onFieldTypeDragStart($event, ft.type)"
                  >
                    {{ ft.label }}
                  </div>
                </div>
              </div>

              <!-- Selected field properties -->
              <div v-if="selectedField" class="p-3 flex-1">
                <h3 class="font-semibold text-sm mb-3">Feld-Eigenschaften</h3>
                <FieldProperties
                  :field="selectedField"
                  @update="updateFieldProps"
                  @delete="deleteField"
                />
              </div>
              <div v-else class="p-3 text-sm text-gray-400">
                Feld auswählen oder ein neues Feld auf die Arbeitsfläche ziehen
              </div>
            </aside>
          </div>
        </template>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'
import type { FolderNode, FolderData, FieldDefinition, FieldType } from '../types'
import FolderTree from '../components/FolderTree.vue'
import FormCanvas from '../components/FormCanvas.vue'
import FieldProperties from '../components/FieldProperties.vue'

const router = useRouter()
const api = useApi()

const tree = ref<FolderNode | null>(null)
const selectedFolderId = ref<string | null>(null)
const folderData = ref<FolderData | null>(null)
const selectedFieldId = ref<string | null>(null)

const fieldTypes = [
  { type: 'text' as FieldType, label: 'Textfeld' },
  { type: 'textarea' as FieldType, label: 'Textarea' },
  { type: 'wysiwyg' as FieldType, label: 'WYSIWYG' },
  { type: 'upload' as FieldType, label: 'Upload' },
  { type: 'multi-upload' as FieldType, label: 'Multi-Upload' },
  { type: 'select' as FieldType, label: 'Auswahl' },
  { type: 'repeatable' as FieldType, label: 'Referenzen' },
]

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

const selectedField = computed(() => {
  if (!folderData.value || !selectedFieldId.value) return null
  return folderData.value.fields.find(f => f.id === selectedFieldId.value) || null
})

async function loadTree() {
  try {
    tree.value = await api.getTree()
  } catch {
    router.push('/login')
  }
}

async function loadFolderData(id: string) {
  folderData.value = await api.getFolderData(id)
}

function selectFolder(id: string) {
  selectedFolderId.value = id
  selectedFieldId.value = null
}

watch(selectedFolderId, (id) => {
  if (id) loadFolderData(id)
  else folderData.value = null
})

async function createFolder(parentId: string) {
  const name = prompt('Ordnername:')
  if (!name) return
  await api.createFolder(parentId, name)
  await loadTree()
}

async function renameFolder(id: string, currentName: string) {
  const name = prompt('Neuer Name:', currentName)
  if (!name || name === currentName) return
  await api.updateFolder(id, name)
  await loadTree()
}

async function deleteFolder(id: string) {
  if (!confirm('Ordner und alle Unterordner wirklich löschen?')) return
  await api.deleteFolder(id)
  if (selectedFolderId.value === id) {
    selectedFolderId.value = null
    folderData.value = null
  }
  await loadTree()
}

async function copyFolder(id: string) {
  await api.copyFolder(id)
  await loadTree()
}

async function moveFolder(folderId: string, newParentId: string) {
  await api.moveFolder(folderId, newParentId)
  await loadTree()
}

async function shareFolder(id: string) {
  const result = await api.createShare(id)
  prompt('Öffentliche URL (Strg+C zum Kopieren):', result.url)
  await loadTree()
}

async function deleteShareToken(folderId: string, token: string) {
  await api.deleteShare(folderId, token)
  await loadTree()
}

async function downloadFolder(id: string) {
  await api.downloadZip(id)
}

// Canvas image
async function uploadCanvasImage(e: Event) {
  const input = e.target as HTMLInputElement
  if (!input.files?.length || !selectedFolderId.value) return
  const uploaded = await api.upload(input.files[0])
  await api.updateCanvas(selectedFolderId.value, uploaded.url)
  await loadFolderData(selectedFolderId.value)
  input.value = ''
}

async function removeCanvasImage() {
  if (!selectedFolderId.value) return
  await api.updateCanvas(selectedFolderId.value, null)
  await loadFolderData(selectedFolderId.value)
}

// Field management
function selectField(id: string | null) {
  selectedFieldId.value = id
}

function onFieldTypeDragStart(e: DragEvent, type: FieldType) {
  e.dataTransfer?.setData('fieldType', type)
}

async function addFieldToCanvas(type: FieldType, x: number, y: number) {
  if (!folderData.value || !selectedFolderId.value) return

  const id = 'field_' + Date.now().toString(36)
  const defaultLabels: Record<string, string> = {
    text: 'Textfeld',
    textarea: 'Textarea',
    wysiwyg: 'WYSIWYG Editor',
    upload: 'Datei-Upload',
    'multi-upload': 'Mehrere Dateien',
    select: 'Auswahl',
    repeatable: 'Referenzen',
  }

  const newField: FieldDefinition = {
    id,
    type,
    label: defaultLabels[type] || 'Feld',
    required: false,
    position: { x, y },
    size: { width: 250, height: type === 'repeatable' ? 200 : type === 'wysiwyg' ? 180 : type === 'textarea' ? 120 : 60 },
    validation: {},
    placeholder: '',
  }

  if (type === 'select') {
    newField.options = ['Option 1', 'Option 2', 'Option 3']
  }

  if (type === 'repeatable') {
    newField.subFields = [
      { id: 'title', type: 'text', label: 'Titel' },
      { id: 'location', type: 'text', label: 'Ort' },
      { id: 'category', type: 'text', label: 'Kategorie' },
      { id: 'subcategory', type: 'text', label: 'Unterkategorie' },
      { id: 'subcategory2', type: 'text', label: 'Unterkategorie 2' },
      { id: 'description', type: 'textarea', label: 'Freitext' },
      { id: 'images', type: 'multi-upload', label: 'Bilder' },
    ]
    newField.validation = { minEntries: 1 }
  }

  folderData.value.fields.push(newField)
  await saveFields()
  selectedFieldId.value = id
}

async function updateFieldPosition(fieldId: string, x: number, y: number) {
  if (!folderData.value) return
  const field = folderData.value.fields.find(f => f.id === fieldId)
  if (field) {
    field.position = { x, y }
    await saveFields()
  }
}

async function updateFieldSize(fieldId: string, width: number, height: number) {
  if (!folderData.value) return
  const field = folderData.value.fields.find(f => f.id === fieldId)
  if (field) {
    field.size = { width, height }
    await saveFields()
  }
}

async function updateFieldProps(updated: FieldDefinition) {
  if (!folderData.value) return
  const idx = folderData.value.fields.findIndex(f => f.id === updated.id)
  if (idx >= 0) {
    folderData.value.fields[idx] = updated
    await saveFields()
  }
}

async function deleteField(fieldId: string) {
  if (!folderData.value) return
  folderData.value.fields = folderData.value.fields.filter(f => f.id !== fieldId)
  selectedFieldId.value = null
  await saveFields()
}

async function saveFields() {
  if (!folderData.value || !selectedFolderId.value) return
  await api.updateFields(selectedFolderId.value, folderData.value.fields)
}

function logout() {
  localStorage.removeItem('auth_token')
  router.push('/login')
}

loadTree()
</script>

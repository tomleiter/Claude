export interface FolderNode {
  id: string
  name: string
  children: FolderNode[]
  shareTokens?: string[]
  completion?: CompletionStatus
}

export interface CompletionStatus {
  total: number
  filled: number
  requiredTotal: number
  requiredFilled: number
  percent: number
  requiredPercent: number
}

export interface FieldDefinition {
  id: string
  type: FieldType
  label: string
  required: boolean
  position: { x: number; y: number }
  size: { width: number; height: number }
  validation: FieldValidation
  subFields?: SubFieldDefinition[]
  options?: string[]
  placeholder?: string
}

export type FieldType =
  | 'text'
  | 'textarea'
  | 'wysiwyg'
  | 'upload'
  | 'multi-upload'
  | 'select'
  | 'repeatable'

export interface SubFieldDefinition {
  id: string
  type: 'text' | 'textarea' | 'select' | 'upload' | 'multi-upload'
  label: string
  options?: string[]
  placeholder?: string
}

export interface FieldValidation {
  minLength?: number
  maxLength?: number
  minEntries?: number
  maxEntries?: number
  acceptedTypes?: string[]
}

export interface FolderData {
  id: string
  canvasImage: string | null
  fields: FieldDefinition[]
  data: Record<string, any>
  released: boolean
  releasedAt?: string
  completion?: CompletionStatus
  missingFields?: MissingField[]
}

export interface MissingField {
  id: string
  label: string
  type: string
}

export interface UploadedFile {
  url: string
  name: string
  size: number
}

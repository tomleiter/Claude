import type { FolderNode, FolderData, UploadedFile } from '../types'

const API_BASE = '/api'

function getToken(): string | null {
  return localStorage.getItem('auth_token')
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
  const token = getToken()
  const headers: Record<string, string> = {
    ...(options.headers as Record<string, string> || {}),
  }
  if (token) headers['Authorization'] = `Bearer ${token}`
  if (!(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json'
  }

  const res = await fetch(`${API_BASE}${url}`, { ...options, headers })

  if (res.headers.get('content-type')?.includes('application/zip')) {
    const blob = await res.blob()
    return blob as unknown as T
  }

  const data = await res.json()
  if (!res.ok) throw new Error(data.error || 'Request failed')
  return data as T
}

export function useApi() {
  return {
    // Auth
    login: (username: string, password: string) =>
      request<{ token: string; username: string }>('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ username, password }),
      }),

    // Folders
    getTree: () => request<FolderNode>('/folders/tree'),

    createFolder: (parentId: string, name: string) =>
      request<FolderNode>('/folders', {
        method: 'POST',
        body: JSON.stringify({ parentId, name }),
      }),

    updateFolder: (id: string, name: string) =>
      request<FolderNode>(`/folders/${id}`, {
        method: 'PUT',
        body: JSON.stringify({ name }),
      }),

    moveFolder: (folderId: string, newParentId: string, position?: number) =>
      request<FolderNode>('/folders/move', {
        method: 'PUT',
        body: JSON.stringify({ folderId, newParentId, position }),
      }),

    copyFolder: (id: string, targetParentId?: string) =>
      request<FolderNode>(`/folders/${id}/copy`, {
        method: 'POST',
        body: JSON.stringify({ targetParentId }),
      }),

    deleteFolder: (id: string) =>
      request<{ success: boolean }>(`/folders/${id}`, { method: 'DELETE' }),

    createShare: (id: string) =>
      request<{ token: string; url: string }>(`/folders/${id}/share`, { method: 'POST' }),

    deleteShare: (folderId: string, token: string) =>
      request<{ success: boolean }>(`/folders/${folderId}/share/${token}`, { method: 'DELETE' }),

    // Folder Data
    getFolderData: (id: string) =>
      request<FolderData>(`/folders/${id}/data`),

    updateFields: (id: string, fields: any[]) =>
      request<FolderData>(`/folders/${id}/fields`, {
        method: 'PUT',
        body: JSON.stringify({ fields }),
      }),

    updateCanvas: (id: string, canvasImage: string | null) =>
      request<FolderData>(`/folders/${id}/canvas`, {
        method: 'PUT',
        body: JSON.stringify({ canvasImage }),
      }),

    // Upload
    upload: async (file: File): Promise<UploadedFile> => {
      const formData = new FormData()
      formData.append('file', file)
      return request<UploadedFile>('/upload', {
        method: 'POST',
        body: formData,
      })
    },

    // Download
    downloadZip: async (id: string) => {
      const token = getToken()
      const res = await fetch(`${API_BASE}/download/${id}`, {
        headers: token ? { Authorization: `Bearer ${token}` } : {},
      })
      if (!res.ok) throw new Error('Download failed')
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `folder_${id}.zip`
      a.click()
      URL.revokeObjectURL(url)
    },

    // Public
    publicGetTree: (token: string) =>
      request<FolderNode>(`/public/${token}`),

    publicGetFolder: (token: string, folderId: string) =>
      request<FolderData>(`/public/${token}/folder/${folderId}`),

    publicSaveData: (token: string, folderId: string, data: Record<string, any>) =>
      request<FolderData>(`/public/${token}/folder/${folderId}`, {
        method: 'PUT',
        body: JSON.stringify({ data }),
      }),

    publicRelease: (token: string, folderId: string) =>
      request<FolderData>(`/public/${token}/folder/${folderId}/release`, {
        method: 'POST',
      }),

    publicUpload: async (file: File): Promise<UploadedFile> => {
      const formData = new FormData()
      formData.append('file', file)
      return request<UploadedFile>('/public/upload', {
        method: 'POST',
        body: formData,
      })
    },

    publicDownloadZip: async (token: string, folderId: string) => {
      const res = await fetch(`${API_BASE}/public/${token}/download/${folderId}`)
      if (!res.ok) throw new Error('Download failed')
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `folder_${folderId}.zip`
      a.click()
      URL.revokeObjectURL(url)
    },
  }
}

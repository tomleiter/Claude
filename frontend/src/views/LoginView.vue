<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-100">
    <div class="bg-white p-8 rounded-lg shadow-md w-96">
      <h1 class="text-2xl font-bold mb-6 text-center">Form Builder</h1>
      <form @submit.prevent="handleLogin">
        <div class="mb-4">
          <label class="label">Benutzername</label>
          <input v-model="username" type="text" class="input" placeholder="admin" />
        </div>
        <div class="mb-4">
          <label class="label">Passwort</label>
          <input v-model="password" type="password" class="input" placeholder="Passwort" />
        </div>
        <div v-if="error" class="mb-4 text-red-600 text-sm">{{ error }}</div>
        <button type="submit" class="btn-primary w-full" :disabled="loading">
          {{ loading ? 'Anmelden...' : 'Anmelden' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi'

const router = useRouter()
const api = useApi()

const username = ref('admin')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  loading.value = true
  error.value = ''
  try {
    const result = await api.login(username.value, password.value)
    localStorage.setItem('auth_token', result.token)
    router.push('/admin')
  } catch (e: any) {
    error.value = e.message || 'Login fehlgeschlagen'
  } finally {
    loading.value = false
  }
}
</script>

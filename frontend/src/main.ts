import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import './assets/main.css'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('./views/LoginView.vue'),
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('./views/AdminView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/public/:token',
      name: 'public',
      component: () => import('./views/PublicView.vue'),
    },
    {
      path: '/',
      redirect: '/admin',
    },
  ],
})

router.beforeEach((to) => {
  if (to.meta.requiresAuth && !localStorage.getItem('auth_token')) {
    return '/login'
  }
})

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.mount('#app')

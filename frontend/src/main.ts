import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import { configureHttp } from './api/http'
import { router } from './router'
import { useSessionStore } from './stores/session'
import './style.css'

const app = createApp(App)
app.use(createPinia())

const session = useSessionStore()
configureHttp({
  token: () => session.token,
  // Expired or revoked token: drop the session and ask for a new login.
  unauthorized: () => {
    session.clear()
    void router.push({ name: 'login', query: { expired: '1' } })
  },
})

app.use(router)
app.mount('#app')

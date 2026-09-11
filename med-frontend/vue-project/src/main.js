import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createPersistedState } from 'pinia-plugin-persistedstate'

import '@fortawesome/fontawesome-free/css/all.min.css'
import Vue3Toastify from 'vue3-toastify'
import 'vue3-toastify/dist/index.css'
import '@/CSS_GLOBAL/main.css'

import App from './App.vue'
import router from '@/router/index.js'
import { useAuthStore, connectAuthStoreToHttp } from '@/store/auth'

const app = createApp(App)
const pinia = createPinia()

// Persistenza dello store (usata solo per il token di sessione)
pinia.use(createPersistedState())
app.use(pinia)

// Alert personalizzati
app.use(Vue3Toastify, {
  autoClose: 2500,
  position: 'top-right',
})

/*
  Avvio in due fasi:
  1. si collega lo store auth all'interceptor axios (token in ogni richiesta)
  2. si verifica il token salvato prima di montare l'app, cosi' il router
     conosce gia' ruolo e sessione al primo navigate e non ci sono sfarfallii.

  bootstrap() non rilancia mai: in caso di backend spento si prosegue comunque
  con la sessione pulita, altrimenti l'app non verrebbe montata affatto.
*/
connectAuthStoreToHttp()

const auth = useAuthStore()

auth
  .bootstrap()
  .catch(() => auth.clearSession())
  .finally(() => {
    app.use(router)
    // Si monta solo quando il router ha risolto la prima navigazione:
    // evita che una guard scriva su un DOM non ancora pronto.
    router.isReady().finally(() => app.mount('#app'))
  })

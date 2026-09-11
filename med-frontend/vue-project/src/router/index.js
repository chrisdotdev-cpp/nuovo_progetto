import { createRouter, createWebHistory } from 'vue-router'
import { registerUnauthorizedHandler } from '@/services/http'
import { authGuard } from './guard'

// Import moduli
import homeRoutes from './modules/home.routes'
import pazientiRoutes from './modules/pazienti.routes'
import mediciRoutes from './modules/medici.routes'
import adminRoutes from './modules/admin.routes'

const router = createRouter({
  history: createWebHistory(),

  routes: [
    ...homeRoutes,
    ...pazientiRoutes,
    ...mediciRoutes,
    ...adminRoutes,

    // Qualsiasi rotta sconosciuta torna alla home
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],

  // Ritorno in cima a ogni cambio pagina (fondamentale su mobile)
  scrollBehavior: (to, from, savedPosition) => savedPosition ?? { top: 0 },
})

/*
  Guard di accesso: la logica sta in ./guard.js, cosi' e' verificabile da sola
  senza far navigare un router vero (vedi tests/router/guard.spec.js).
*/
router.beforeEach(authGuard)

/*
  L'interceptor axios non conosce il router: quando arriva un 401
  (token scaduto o revocato) chiama questo handler per riportare al login.
*/
registerUnauthorizedHandler(() => {
  const current = router.currentRoute.value

  // All'avvio dell'app la rotta corrente e' ancora quella "vuota": non si naviga
  if (!current?.name) return

  if (current.meta?.requiresAuth) {
    router.replace({ name: 'home', query: { redirect: current.fullPath } })
  }
})

export default router

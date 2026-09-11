import { useAuthStore } from '@/store/auth.js'
import { useToast } from '@/composables/useToast'

/**
 * Guard di accesso dell'applicazione.
 *
 * Vive in un modulo separato da router/index.js per due motivi:
 *  - e' la regola di sicurezza piu' importante del frontend e va provata da
 *    sola, con input costruiti a mano, senza dover far navigare un router vero
 *    (che trascinerebbe layout, viste lazy e store di mezza applicazione);
 *  - resta una funzione pura rispetto al router: riceve `to`, restituisce la
 *    destinazione. Nessun effetto collaterale oltre al toast.
 *
 * REGOLA CRITICA: si RESTITUISCE la destinazione, non si chiama router.push().
 * Un push() dentro la guard avvia una seconda navigazione che annulla la prima
 * ("Navigation aborted") e lascia la UI ferma dov'era: era una delle cause del
 * login bloccato.
 *
 * @param {import('vue-router').RouteLocationNormalized} to
 * @returns {true|object} true per lasciar passare, oppure la destinazione
 */
export function authGuard(to) {
  const auth = useAuthStore()

  const isPublic = to.name === 'home' || to.name === 'loginRole'

  // Utente gia' autenticato che torna su home o login -> alla sua dashboard
  if (auth.isAuthenticated && isPublic) {
    const target = auth.dashboardPath
    return target === to.path ? true : target
  }

  // Rotta protetta senza sessione valida
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Nessun toast al primo avvio (token assente): sarebbe solo rumore
    if (!auth.initializing) {
      useToast().error('Devi effettuare il login per accedere')
    }

    // Si memorizza la destinazione per tornarci dopo il login
    return { name: 'home', query: { redirect: to.fullPath } }
  }

  // Rotta con ruolo specifico: si viene dirottati sulla propria area
  if (to.meta.role && auth.role !== to.meta.role) {
    useToast().error('Non hai i permessi per accedere a questa sezione')

    if (!auth.isAuthenticated) return { name: 'home' }

    const target = auth.dashboardPath
    return target === to.path ? { name: 'home' } : target
  }

  return true
}

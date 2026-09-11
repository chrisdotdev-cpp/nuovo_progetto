import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { toast } from 'vue3-toastify'

import { authGuard } from '@/router/guard'
import { useAuthStore } from '@/store/auth.js'
import { utente } from '../helpers'

/**
 * Guard di accesso.
 *
 * E' l'unica barriera fra un URL digitato a mano e una sezione riservata:
 * il backend protegge i dati, ma senza guard il paziente vedrebbe comunque il
 * guscio della dashboard admin. Qui la funzione viene chiamata direttamente con
 * `to` costruiti a mano, cosi' si osserva il valore restituito invece di
 * dedurlo da una navigazione.
 */
describe('router guard', () => {
  let auth

  beforeEach(() => {
    setActivePinia(createPinia())
    auth = useAuthStore()
    // Dopo l'avvio dell'app initializing e' false: e' lo stato normale
    auth.initializing = false
  })

  /** Costruisce un `to` minimale ma con la stessa forma di vue-router. */
  const rotta = (overrides = {}) => ({
    name: 'paziente.panoramica',
    path: '/paziente/panoramica',
    fullPath: '/paziente/panoramica',
    params: {},
    query: {},
    meta: {},
    ...overrides,
  })

  const autenticaCome = (role, redirect = null) => {
    auth.token = 'token-valido'
    auth.user = utente({ role })
    auth.redirect = redirect
  }

  /* =====================================================================
   | Rotte pubbliche
   * ===================================================================*/

  it('lascia passare un visitatore sulla home', () => {
    const esito = authGuard(rotta({ name: 'home', path: '/', fullPath: '/' }))

    expect(esito).toBe(true)
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('lascia passare un visitatore sulla schermata di login del ruolo', () => {
    const esito = authGuard(
      rotta({ name: 'loginRole', path: '/login/paziente', fullPath: '/login/paziente' }),
    )

    expect(esito).toBe(true)
  })

  /* =====================================================================
   | Sessione assente
   * ===================================================================*/

  it('respinge una rotta protetta e memorizza la destinazione', () => {
    const esito = authGuard(
      rotta({
        name: 'paziente.appuntamenti',
        path: '/paziente/appuntamenti',
        fullPath: '/paziente/appuntamenti?tab=prossimi',
        meta: { requiresAuth: true, role: 'paziente' },
      }),
    )

    expect(esito).toEqual({
      name: 'home',
      query: { redirect: '/paziente/appuntamenti?tab=prossimi' },
    })

    expect(toast.error).toHaveBeenCalledWith(
      'Devi effettuare il login per accedere',
      expect.any(Object),
    )
  })

  /**
   * All'avvio dell'app il token e' ancora in corso di verifica: un toast qui
   * comparirebbe a ogni ricarica di pagina anche per un utente regolarmente
   * loggato. Il redirect invece resta.
   */
  it('non disturba con un toast mentre la sessione e ancora in verifica', () => {
    auth.initializing = true

    const esito = authGuard(rotta({ meta: { requiresAuth: true } }))

    expect(esito).toMatchObject({ name: 'home' })
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('un token senza utente non e una sessione valida', () => {
    auth.token = 'token-orfano'
    auth.user = null

    const esito = authGuard(rotta({ meta: { requiresAuth: true } }))

    expect(esito).toMatchObject({ name: 'home' })
  })

  /* =====================================================================
   | Sessione attiva
   * ===================================================================*/

  it('porta un utente gia autenticato dalla home alla sua dashboard', () => {
    autenticaCome('medico')

    const esito = authGuard(rotta({ name: 'home', path: '/', fullPath: '/' }))

    expect(esito).toBe('/medico/panoramica')
  })

  it('porta un utente autenticato via dalla schermata di login', () => {
    autenticaCome('admin')

    const esito = authGuard(
      rotta({ name: 'loginRole', path: '/login/admin', fullPath: '/login/admin' }),
    )

    expect(esito).toBe('/admin/panoramica')
  })

  /**
   * Se la destinazione coincide con la rotta corrente si deve restituire true.
   * Restituire l'oggetto provocherebbe una navigazione verso se stessi e
   * vue-router la interpreta come loop ("Maximum call stack" / redirect
   * infinito): era il sintomo del login che restava bloccato sullo spinner.
   */
  it('evita il redirect su se stesso', () => {
    autenticaCome('paziente', '/')

    const esito = authGuard(rotta({ name: 'home', path: '/', fullPath: '/' }))

    expect(esito).toBe(true)
  })

  it('lascia passare l utente nella propria area', () => {
    autenticaCome('paziente')

    const esito = authGuard(
      rotta({ meta: { requiresAuth: true, role: 'paziente' } }),
    )

    expect(esito).toBe(true)
    expect(toast.error).not.toHaveBeenCalled()
  })

  it('lascia passare una rotta protetta senza vincolo di ruolo', () => {
    autenticaCome('medico')

    const esito = authGuard(
      rotta({ name: 'medico.impostazioni', path: '/medico/impostazioni', meta: { requiresAuth: true } }),
    )

    expect(esito).toBe(true)
  })

  /* =====================================================================
   | Ruolo sbagliato
   * ===================================================================*/

  it('dirotta il paziente che tenta di entrare nell area admin', () => {
    autenticaCome('paziente')

    const esito = authGuard(
      rotta({
        name: 'admin.panoramica',
        path: '/admin/panoramica',
        fullPath: '/admin/panoramica',
        meta: { requiresAuth: true, role: 'admin' },
      }),
    )

    expect(esito).toBe('/paziente/panoramica')
    expect(toast.error).toHaveBeenCalledWith(
      'Non hai i permessi per accedere a questa sezione',
      expect.any(Object),
    )
  })

  it('dirotta il medico che tenta di entrare nell area paziente', () => {
    autenticaCome('medico')

    const esito = authGuard(
      rotta({ path: '/paziente/appuntamenti', meta: { requiresAuth: true, role: 'paziente' } }),
    )

    expect(esito).toBe('/medico/panoramica')
  })

  /**
   * Rotta con vincolo di ruolo ma senza requiresAuth: senza sessione non c'e'
   * una dashboard dove mandare l'utente, quindi si torna alla home.
   */
  it('senza sessione un vincolo di ruolo riporta alla home', () => {
    const esito = authGuard(rotta({ path: '/admin/panoramica', meta: { role: 'admin' } }))

    expect(esito).toEqual({ name: 'home' })
  })

  /**
   * Caso limite: il redirect del backend punta a un'area diversa da quella del
   * ruolo. Mandare l'utente li' creerebbe un rimbalzo infinito fra la guard e
   * se stessa, quindi si ripiega sulla home.
   */
  it('evita il rimbalzo infinito quando la dashboard coincide con la rotta vietata', () => {
    autenticaCome('paziente', '/admin/panoramica')

    const esito = authGuard(
      rotta({
        path: '/admin/panoramica',
        fullPath: '/admin/panoramica',
        meta: { requiresAuth: true, role: 'admin' },
      }),
    )

    expect(esito).toEqual({ name: 'home' })
  })
})

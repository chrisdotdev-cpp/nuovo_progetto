import DefaultLayout from '@/components/layouts/DefaultLayout.vue'

export default [
  {
    path: '/paziente',
    component: DefaultLayout,
    meta: { requiresAuth: true, role: 'paziente' },
    children: [
        {
            path: 'panoramica',
            name: 'paziente.panoramica',
            component: () => import('@/views/paziente/Panoramica.vue')
        },

        {
          path: 'appuntamenti',
          name: 'paziente.appuntamenti',
          component: () => import('@/views/paziente/Appuntamenti.vue')
        },

        {
          path: 'cartella-clinica',
          name: 'paziente.cartellaClinica',
          component: () => import('@/views/paziente/CartellaClinica.vue')
        },

        {
          path: 'telemedicina',
          name: 'paziente.telemedicina',
          component: () => import('@/views/paziente/Telemedicina.vue')
        },

        {
          path: 'pagamenti',
          name: 'paziente.pagamenti',
          component: () => import('@/views/paziente/Pagamenti.vue')
        },

        {
            path: 'notifiche',
            name: 'paziente.notifiche',
            component: () => import('@/views/shared/Notifiche.vue')
        },

        {
            path: 'impostazioni',
            name: 'paziente.impostazioni',
            component: () => import('@/views/shared/Impostazioni.vue')
        }
    ]
  }
]

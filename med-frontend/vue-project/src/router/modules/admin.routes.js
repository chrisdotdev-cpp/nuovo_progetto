import DefaultLayout from "@/components/layouts/DefaultLayout.vue"

export default [
    {
        path: '/admin',
        component: DefaultLayout,
        meta: {requiresAuth: true, role: 'admin'},
        children: [
            {
                path: 'panoramica',
                name: 'admin.panoramica',
                component: () => import('@/views/admin/Panoramica.vue')
            },

            {
                path: 'utenti',
                name: 'admin.utenti',
                component: () => import('@/views/admin/Utenti.vue')
            },

            {
                path: 'personale',
                name: 'admin.personale',
                component: () => import('@/views/admin/Personale.vue')
            },

            {
                path: 'prenotazioni',
                name: 'admin.prenotazioni',
                component: () => import('@/views/admin/Prenotazioni.vue')
            },

            {
                path: 'documenti',
                name: 'admin.documenti',
                component: () => import('@/views/admin/Documenti.vue')
            },

            {
                path: 'finanziario',
                name: 'admin.finanziario',
                component: () => import('@/views/admin/Finanziario.vue')
            },

            {
                path: 'farmacia',
                name: 'admin.farmacia',
                component: () => import('@/views/admin/Farmacia.vue')
            },

            {
                path: 'notifiche',
                name: 'admin.notifiche',
                component: () => import('@/views/shared/Notifiche.vue')
            },           
            
            {
                path: 'impostazioni',
                name: 'admin.impostazioni',
                component: () => import('@/views/shared/Impostazioni.vue')
            },                                                                                            
        ]
    }
]

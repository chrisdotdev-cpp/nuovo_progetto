import DefaultLayout from "@/components/layouts/DefaultLayout.vue"

export default [
    {
        path: '/medico',
        component: DefaultLayout,
        meta: { requiresAuth: true, role: 'medico'},
        children: [
            {
                path: 'panoramica',
                name: 'medico.panoramica',
                component: () => import('@/views/medico/Panoramica.vue')
            },

            {
                path: 'agenda',
                name: 'medico.agenda',
                component: () => import('@/views/medico/Agenda.vue')
            },

            {
                path: 'pazienti',
                name: 'medico.pazienti',
                component: () => import('@/views/medico/Pazienti.vue')
            },

            {
                path: 'telemedicina',
                name: 'medico.telemedicina',
                component: () => import('@/views/medico/Telemedicina.vue')
            },

            {
                path: 'prescrizioni',
                name: 'medico.prescrizioni',
                component: () => import('@/views/medico/Prescrizioni.vue')
            },

            {
                path: 'richieste',
                name: 'medico.richieste',
                component: () => import('@/views/medico/Richieste.vue')
            },

            {
                path: 'notifiche',
                name: 'medico.notifiche',
                component: () => import('@/views/shared/Notifiche.vue')
            },           

            {
                path: 'impostazioni',
                name: 'medico.impostazioni',
                component: () => import('@/views/shared/Impostazioni.vue')
            },                                                            
        ]
        
    }
]


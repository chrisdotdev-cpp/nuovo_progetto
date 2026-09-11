import AuthLayout from '@/components/layouts/AuthLayout.vue'
import LoginLayout from '@/components/layouts/LoginLayout.vue'

export default [
  // AuthLayout route
  {
    path: '/',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/home/Home.vue')
      },
    ]
  },

  // LoginLayout route
  {
    path: '/login',
    component: LoginLayout,
    children: [
      {
        path: ':role',
        name: 'loginRole',
        component: () => import('@/views/home/LoginRole.vue')
      }
    ]
  }
]

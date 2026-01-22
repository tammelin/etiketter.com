import { createRouter, createWebHistory } from 'vue-router'
import FormView from '../views/FormView.vue'
import NotFoundView from '../views/NotFoundView.vue'

const router = createRouter({
  history: createWebHistory('/etiketter/'),
  routes: [
    {
      path: '/',
      name: 'form',
      component: FormView
    },
    {
      path: '/:uuid',
      name: 'form-with-uuid',
      component: FormView
    },
    // {
    //   path: '/:pathMatch(.*)',
    //   component: NotFoundView
    // }
  ]
})

export default router

<template>
  <div class="grid grid-cols-1 sm:grid-cols-1 lg:grid-cols-3 gap-6 p-4 sm:p-6">
    <div v-for="(item, key) in roles" :key="key">
      <BaseCard
        :title="item.title"
        :icon="item.icon"
        :iconBg="item.iconBg"
        :about="item.about"
        @click="goTo(key)"
      />
    </div>
  </div>
</template>

<script setup>
import { roles } from '@/data/roles.js'
import { useRoute, useRouter } from 'vue-router'

import BaseCard from '@/components/ui/BaseCard.vue'

const router = useRouter()
const route = useRoute()

function goTo(role) {
  // Si porta avanti l'eventuale destinazione richiesta prima del login
  router.push({
    name: 'loginRole',
    params: { role },
    query: route.query.redirect ? { redirect: route.query.redirect } : {},
  })
}
</script>

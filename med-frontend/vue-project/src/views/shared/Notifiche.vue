<template>
  <!--
    Le notifiche funzionano allo stesso modo per i tre ruoli: il backend
    restituisce già solo quelle dell'utente autenticato, quindi la vista
    incapsula il componente condiviso invece di duplicarne la logica.
  -->
  <NotificheCenter />
</template>

<script setup>
import NotificheCenter from '@/components/shared/NotificheCenter.vue'
</script>

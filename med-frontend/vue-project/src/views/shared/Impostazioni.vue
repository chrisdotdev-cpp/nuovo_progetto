<template>
  <!--
    Profilo, sicurezza e (per il paziente) dati sanitari sono identici nei tre
    ruoli: la vista incapsula il componente condiviso, che mostra le sezioni
    pertinenti in base al ruolo dell'utente autenticato.
  -->
  <ImpostazioniProfilo />
</template>

<script setup>
import ImpostazioniProfilo from '@/components/shared/ImpostazioniProfilo.vue'
</script>

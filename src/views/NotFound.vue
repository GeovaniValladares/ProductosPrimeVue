<template>
  <div class="not-found">
    <h1>Página No Encontrada</h1>
    <p>Lo sentimos, la página que estás buscando no existe.</p>
    <router-link to="/dashboard">Volver al inicio</router-link>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const goHome = () => {
  router.push('/dashboard')
}
</script>
<style scoped>
.not-found {
  text-align: center;
  margin-top: 50px;
}
</style>

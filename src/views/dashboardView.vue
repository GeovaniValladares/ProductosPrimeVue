<template>
  <div class="dashboard">
    <header class="encabezado">
      <div>
        <h1>Panel principal</h1>
        <p>{{ user?.name }} · {{ admin ? 'Superadmin' : 'Usuario' }}</p>
      </div>
      <Button label="Salir" severity="danger" @click="exit" />
    </header>
    <nav class="navigation">
      <Button
        v-for="tab in tabs"
        :key="tab.key"
        :label="tab.label"
        :severity="active === tab.key ? 'primary' : 'secondary'"
        @click="active = tab.key"
      />
    </nav>
    <main class="contenido">
      <Message
        v-if="!admin && active !== 'seguridad'"
        severity="info"
        :closable="false"
        class="permission-message"
        >Tu cuenta tiene acceso de consulta. El superadmin administra usuarios, productos y
        contenidos.</Message
      >
      <ProductoTable v-if="active === 'productos'" :editable="admin" />
      <ResourceManager
        v-if="active === 'usuarios' && admin"
        resource="usuarios"
        title="Usuarios"
        :fields="userFields"
        editable
      />
      <ResourceManager
        v-if="active === 'contenidos'"
        resource="contenidos"
        title="Contenidos"
        :fields="contentFields"
        :editable="admin"
      />
      <AccountSecurity v-if="active === 'seguridad'" />
    </main>
  </div>
</template>
<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService } from '@/service/api'
import ProductoTable from './productos/ProductoTable.vue'
import ResourceManager from '@/components/ResourceManager.vue'
import AccountSecurity from '@/components/AccountSecurity.vue'
const auth = useAuthService(),
  router = useRouter(),
  user = ref(auth.getUserData()),
  active = ref('productos')
const admin = computed(() => user.value?.role === 'superadmin')
const tabs = computed(() => [
  { key: 'productos', label: 'Productos' },
  ...(admin.value ? [{ key: 'usuarios', label: 'Usuarios' }] : []),
  { key: 'contenidos', label: 'Gestor de contenido' },
  { key: 'seguridad', label: 'Mi cuenta / Seguridad' },
])
const userFields = [
  { key: 'name', label: 'Nombre' },
  { key: 'email', label: 'Correo' },
  { key: 'role', label: 'Rol', type: 'role' },
]
const contentFields = [
  { key: 'titulo', label: 'Título' },
  { key: 'cuerpo', label: 'Contenido', type: 'text' },
  { key: 'publicado', label: 'Publicado', type: 'boolean' },
]
async function exit() {
  try {
    await auth.logout()
  } finally {
    router.push('/login')
  }
}
</script>
<style scoped>
.permission-message {
  margin-bottom: 1rem;
}
.navigation {
  display: flex;
  gap: 0.7rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}
h1 {
  margin: 0;
}
@media (max-width: 600px) {
  .dashboard {
    padding: 0;
  }
  .encabezado {
    gap: 1rem;
    flex-wrap: wrap;
  }
}
</style>

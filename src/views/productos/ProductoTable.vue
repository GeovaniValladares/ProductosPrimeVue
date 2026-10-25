<template>
  <ResourceManager resource="productos" title="Productos" :editable="editable" :fields="fields" />
</template>
<script setup>
import ResourceManager from '@/components/ResourceManager.vue'
defineProps({ editable: Boolean })
const fields = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'descripcion', label: 'Descripción', type: 'text' },
  { key: 'precio', label: 'Precio (USD)', type: 'number' },
  { key: 'stock', label: 'Existencias', type: 'number' },
]
</script>

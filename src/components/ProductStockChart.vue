<template>
  <section class="inventory-chart" aria-labelledby="stock-chart-title">
    <div class="chart-heading">
      <div>
        <h3 id="stock-chart-title">Existencias por producto</h3>
        <p>Los 10 productos con más unidades entre los resultados de la búsqueda.</p>
      </div>
      <a href="https://tanstack.com/charts/catalog" target="_blank" rel="noopener noreferrer"
        >TanStack Charts</a
      >
    </div>
    <div class="metrics">
      <div>
        <span>Productos filtrados</span><strong>{{ products.length }}</strong>
      </div>
      <div>
        <span>Unidades disponibles</span><strong>{{ units }}</strong>
      </div>
      <div>
        <span>Valor del inventario</span><strong>{{ inventoryValue }}</strong>
      </div>
    </div>
    <Chart
      v-if="ranked.length"
      :definition="definition"
      :height="Math.max(260, ranked.length * 42 + 80)"
      aria-label="Gráfico de barras de existencias por producto"
      aria-description="Productos ordenados de mayor a menor existencia. Las barras naranjas indican menos de diez unidades."
    />
    <p v-else role="status">No hay productos para mostrar en el gráfico.</p>
    <p class="chart-note">
      Naranja: menos de 10 unidades. Los totales incluyen todos los productos filtrados.
    </p>
  </section>
</template>
<script setup>
import { computed } from 'vue'
import { barX, defineChart } from '@tanstack/charts'
import { scaleBand } from '@tanstack/charts/scales/band'
import { scaleLinear } from '@tanstack/charts/scales/linear'
import { tooltip } from '@tanstack/charts/tooltip'
import { Chart } from '@tanstack/charts/vue'

const props = defineProps({ products: { type: Array, default: () => [] } })
const ranked = computed(() =>
  [...props.products]
    .sort((a, b) => Number(b.stock) - Number(a.stock) || Number(a.id) - Number(b.id))
    .slice(0, 10)
    .map((product) => ({
      id: product.id,
      producto: `${product.nombre} (#${product.id})`,
      stock: Number(product.stock) || 0,
    })),
)
const units = computed(() =>
  props.products.reduce((total, product) => total + Number(product.stock || 0), 0),
)
const inventoryValue = computed(() =>
  new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(
    props.products.reduce(
      (total, product) => total + Number(product.precio || 0) * Number(product.stock || 0),
      0,
    ),
  ),
)
const definition = computed(() =>
  defineChart(
    {
      marks: [
        barX(ranked.value, {
          x: 'stock',
          y: 'producto',
          key: 'id',
          radius: 4,
          fill: (product) => (product.stock < 10 ? '#ea580c' : '#6366f1'),
        }),
      ],
      scales: {
        x: {
          scale: scaleLinear,
          domain: [0, Math.max(1, ...ranked.value.map((product) => product.stock))],
          nice: true,
          grid: true,
          axis: { label: 'Unidades disponibles' },
        },
        y: { scale: () => scaleBand().padding(0.25) },
      },
    },
    { tooltip },
  ),
)
</script>
<style scoped>
.inventory-chart {
  margin-top: 2rem;
  border-top: 1px solid var(--border-color);
  padding-top: 1.5rem;
}
.chart-heading {
  display: flex;
  justify-content: space-between;
  align-items: start;
  gap: 1rem;
  flex-wrap: wrap;
}
h3 {
  margin: 0;
}
.chart-heading p,
.chart-note {
  color: var(--text-color);
}
.chart-heading a {
  color: var(--p-primary-color);
}
.metrics {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 1rem;
  margin: 1rem 0;
}
.metrics div {
  border: 1px solid var(--border-color);
  border-radius: 8px;
  padding: 1rem;
}
.metrics span,
.metrics strong {
  display: block;
}
.metrics strong {
  font-size: 1.6rem;
  margin-top: 0.5rem;
}
</style>

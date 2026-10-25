import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProductStockChart from '@/components/ProductStockChart.vue'

let wrapper
beforeEach(() => {
  vi.stubGlobal(
    'ResizeObserver',
    class {
      observe() {}
      unobserve() {}
      disconnect() {}
    },
  )
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({
    measureText: (text) => ({ width: String(text).length * 7 }),
  })
})
afterEach(() => {
  wrapper?.unmount()
  vi.restoreAllMocks()
  vi.unstubAllGlobals()
})
describe('Gráfico TanStack en Vue', () => {
  it('renderiza barras SVG con los productos y actualiza los totales al filtrar', async () => {
    wrapper = mount(ProductStockChart, {
      props: {
        products: [
          { id: 1, nombre: 'Teclado', stock: 8, precio: '25.00' },
          { id: 2, nombre: 'Mouse', stock: 20, precio: '10.00' },
        ],
      },
    })
    await flushPromises()
    expect(wrapper.get('svg').text()).toContain('Mouse')
    expect(wrapper.get('svg').text()).toContain('Teclado')
    const bars = wrapper
      .findAll('svg rect')
      .filter((rect) => ['#6366f1', '#ea580c'].includes(rect.attributes('fill')))
    expect(bars).toHaveLength(2)
    expect(bars.every((rect) => Number(rect.attributes('width')) > 0)).toBe(true)
    expect(wrapper.get('svg').attributes('aria-label')).toBe(
      'Gráfico de barras de existencias por producto',
    )
    expect(wrapper.text()).toContain('28')
    expect(wrapper.text()).toContain('$400.00')
    await wrapper.setProps({ products: [{ id: 1, nombre: 'Teclado', stock: 8, precio: '25.00' }] })
    await flushPromises()
    expect(wrapper.get('svg').text()).not.toContain('Mouse')
    expect(wrapper.text()).toContain('$200.00')
  })
  it('muestra un estado vacío al no haber resultados', () => {
    wrapper = mount(ProductStockChart, { props: { products: [] } })
    expect(wrapper.find('svg').exists()).toBe(false)
    expect(wrapper.text()).toContain('No hay productos para mostrar')
  })
})

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import components from '@/plugins/primevue'
import ResourceManager from '@/components/ResourceManager.vue'
import api from '@/service/api'

const fields = [
  { key: 'nombre', label: 'Nombre' },
  { key: 'descripcion', label: 'Descripción', type: 'text' },
  { key: 'precio', label: 'Precio', type: 'number' },
  { key: 'stock', label: 'Existencias', type: 'number' },
]
const options = {
  plugins: [PrimeVue, components],
  stubs: {
    teleport: true,
    ProductStockChart: {
      name: 'ProductStockChart',
      props: ['products'],
      template: '<div data-chart />',
    },
  },
}
const wrappers = []
beforeEach(() =>
  vi.stubGlobal('matchMedia', () => ({
    matches: false,
    addEventListener() {},
    removeEventListener() {},
  })),
)
afterEach(() => {
  wrappers.forEach((wrapper) => wrapper.unmount())
  wrappers.length = 0
  document.body.innerHTML = ''
})
const button = (wrapper, label) => wrapper.findAll('button').find((item) => item.text() === label)
describe('CRUD PrimeVue', () => {
  it('crea, lista, edita y elimina un producto y actualiza su gráfico', async () => {
    let records = []
    api.defaults.adapter = async (config) => {
      const payload = config.data ? JSON.parse(config.data) : null
      if (config.method === 'post') records.push({ id: 1, ...payload })
      if (config.method === 'put') records = [{ id: 1, ...payload }]
      if (config.method === 'delete') records = []
      return {
        config,
        data: config.method === 'get' ? records.map((row) => ({ ...row })) : {},
        status: 200,
        headers: {},
        statusText: 'OK',
      }
    }
    const wrapper = mount(ResourceManager, {
      props: { resource: 'productos', title: 'Productos', fields, editable: true },
      global: options,
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()
    await button(wrapper, 'Nuevo').trigger('click')
    await flushPromises()
    await wrapper.get('#productosnombre').setValue('Teclado')
    await wrapper.get('#productosdescripcion').setValue('USB')
    await wrapper.get('#productosprecio').setValue('25')
    await wrapper.get('#productosprecio').trigger('blur')
    await wrapper.get('#productosstock').setValue('8')
    await wrapper.get('#productosstock').trigger('blur')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(records[0]).toMatchObject({ nombre: 'Teclado', precio: 25, stock: 8 })
    expect(wrapper.text()).toContain('Registro creado.')
    expect(wrapper.findComponent({ name: 'ProductStockChart' }).props('products')[0].stock).toBe(8)
    await button(wrapper, 'Editar').trigger('click')
    await flushPromises()
    await wrapper.get('#productosnombre').setValue('Teclado nuevo')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Teclado nuevo')
    expect(wrapper.text()).toContain('Registro actualizado.')
    await button(wrapper, 'Eliminar').trigger('click')
    await flushPromises()
    await wrapper
      .findAll('button')
      .filter((item) => item.text() === 'Eliminar')
      .at(-1)
      .trigger('click')
    await flushPromises()
    expect(records).toEqual([])
    expect(wrapper.text()).toContain('Registro eliminado.')
  })

  it('mantiene los datos y muestra el error de validación al guardar', async () => {
    api.defaults.adapter = (config) =>
      config.method === 'get'
        ? Promise.resolve({ config, data: [], status: 200, headers: {}, statusText: 'OK' })
        : Promise.reject({
            response: { status: 422, data: { errors: { nombre: ['El nombre es obligatorio.'] } } },
          })
    const wrapper = mount(ResourceManager, {
      props: { resource: 'productos', title: 'Productos', fields, editable: true },
      global: options,
      attachTo: document.body,
    })
    wrappers.push(wrapper)
    await flushPromises()
    await button(wrapper, 'Nuevo').trigger('click')
    await flushPromises()
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('El nombre es obligatorio.')
    expect(wrapper.find('form').exists()).toBe(true)
  })
})

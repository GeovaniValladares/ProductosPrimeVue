import { beforeEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PrimeVue from 'primevue/config'
import components from '@/plugins/primevue'
import LoginAuth from '@/views/auth/LoginAuth.vue'
import api from '@/service/api'
async function render(adapter) {
  api.defaults.adapter = adapter
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', component: LoginAuth },
      { path: '/dashboard', component: { template: '<div>Dashboard</div>' } },
    ],
  })
  await router.push('/login')
  await router.isReady()
  const wrapper = mount(LoginAuth, { global: { plugins: [router, PrimeVue, components] } })
  return { wrapper, router }
}
const response = (config, data) =>
  Promise.resolve({ config, data, status: 200, statusText: 'OK', headers: {} })
describe('Acceso al dashboard', () => {
  beforeEach(() => localStorage.clear())
  it('pide el código antes de abrir el dashboard y permite verificarlo', async () => {
    const requests = []
    const { wrapper, router } = await render((config) => {
      requests.push(config)
      return response(
        config,
        config.url === '/login'
          ? { requires_2fa: true, challenge: 'challenge', setup_secret: 'SECRETKEY' }
          : { token: 'verified-token', user: { role: 'user' } },
      )
    })
    await wrapper.get('#email').setValue('user@example.com')
    await wrapper.get('#password').setValue('Password123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/login')
    expect(localStorage.getItem('auth_token')).toBeNull()
    expect(wrapper.text()).toContain('SECRETKEY')
    await wrapper.get('#code').setValue('123456')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(JSON.parse(requests[1].data)).toEqual({ challenge: 'challenge', code: '123456' })
    expect(router.currentRoute.value.path).toBe('/dashboard')
    expect(localStorage.getItem('auth_token')).toBe('verified-token')
    wrapper.unmount()
  })
  it.each(['user', 'superadmin'])('redirige al dashboard al %s sin segundo factor', async (role) => {
    const { wrapper, router } = await render((config) =>
      response(config, { token: 'session-token', user: { role } }),
    )
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(router.currentRoute.value.path).toBe('/dashboard')
    expect(wrapper.find('#code').exists()).toBe(false)
    wrapper.unmount()
  })
  it('mantiene el desafío si el código es inválido', async () => {
    const { wrapper, router } = await render((config) =>
      config.url === '/login'
        ? response(config, { requires_2fa: true, challenge: 'challenge' })
        : Promise.reject({ response: { status: 422, data: { message: 'Código inválido' } } }),
    )
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    await wrapper.get('#code').setValue('123456')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('Código inválido')
    expect(router.currentRoute.value.path).toBe('/login')
    expect(localStorage.getItem('auth_token')).toBeNull()
    wrapper.unmount()
  })
})

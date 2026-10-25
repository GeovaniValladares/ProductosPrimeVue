import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import components from '@/plugins/primevue'
import AccountSecurity from '@/components/AccountSecurity.vue'
import api from '@/service/api'
import QRCode from 'qrcode'

let wrapper
afterEach(() => {
  wrapper?.unmount()
  vi.restoreAllMocks()
})
describe('Activación voluntaria', () => {
  it('mantiene la verificación desactivada hasta confirmar el código', async () => {
    const qr = vi.spyOn(QRCode, 'toDataURL').mockResolvedValue('data:image/png;base64,QRIMAGE')
    const requests = []
    api.defaults.adapter = (config) => {
      requests.push(config)
      return Promise.resolve({
        config,
        status: 200,
        statusText: 'OK',
        headers: {},
        data:
          config.method === 'get'
            ? { enabled: false }
            : config.url.endsWith('/setup')
              ? {
                  secret: 'SETUPKEY',
                  otpauth_uri:
                    'otpauth://totp/Inventario:ana@example.com?secret=SETUPKEY&issuer=Inventario',
                }
              : { enabled: true },
      })
    }
    wrapper = mount(AccountSecurity, { global: { plugins: [PrimeVue, components] } })
    await flushPromises()
    expect(wrapper.text()).toContain('Desactivado')
    await wrapper.get('#security-password').setValue('Password123!')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(wrapper.text()).toContain('SETUPKEY')
    expect(qr).toHaveBeenCalledWith(
      'otpauth://totp/Inventario:ana@example.com?secret=SETUPKEY&issuer=Inventario',
      expect.objectContaining({ width: 256, margin: 4 }),
    )
    expect(wrapper.get('img').attributes('src')).toBe('data:image/png;base64,QRIMAGE')
    expect(wrapper.text()).toContain('Desactivado')
    await wrapper.get('#security-code').setValue('123456')
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(requests.at(-1).url).toBe('/auth/authenticator/confirm')
    expect(JSON.parse(requests.at(-1).data)).toEqual({ code: '123456' })
    expect(wrapper.text()).toContain('Authenticator activado.')
    expect(wrapper.text()).not.toContain('SETUPKEY')
    expect(wrapper.find('img').exists()).toBe(false)
  })
})

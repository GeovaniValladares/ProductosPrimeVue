<template>
  <Card>
    <template #title>Seguridad de mi cuenta</template>
    <template #content>
      <p>
        Google Authenticator es opcional. Si está desactivado, puedes entrar con correo y
        contraseña.
      </p>
      <p v-if="ready">
        Estado: <strong>{{ enabled ? 'Activado' : 'Desactivado' }}</strong>
      </p>
      <Message v-if="message" severity="success" :closable="false">{{ message }}</Message>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <form v-if="ready" class="security-form" @submit.prevent="submit">
        <template v-if="!secret">
          <label for="security-password">Contraseña actual</label>
          <Password
            inputId="security-password"
            v-model="password"
            autocomplete="current-password"
            :feedback="false"
            toggleMask
            required
            fluid
          />
        </template>
        <template v-if="secret">
          <Message severity="info" :closable="false">
            En Google Authenticator pulsa + → Escanear un código QR. Después confirma el código de
            seis dígitos de la app. El QR vence en cinco minutos.
          </Message>
          <img
            v-if="qrImage"
            :src="qrImage"
            class="setup-qr"
            alt="Código QR para vincular tu cuenta con Google Authenticator"
            width="256"
            height="256"
          />
          <details>
            <summary>Introducir la clave manualmente</summary>
            <p>Usa tu correo como nombre y selecciona «Basada en tiempo».</p>
            <strong class="setup-secret">{{ secret }}</strong>
          </details>
        </template>
        <template v-if="secret || enabled">
          <label for="security-code">Código actual de Google Authenticator</label>
          <InputText
            id="security-code"
            v-model="code"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            autocomplete="one-time-code"
            required
          />
        </template>
        <Button
          type="submit"
          :label="
            enabled
              ? 'Desactivar Authenticator'
              : secret
                ? 'Confirmar activación'
                : 'Configurar Google Authenticator'
          "
          :severity="enabled ? 'danger' : 'primary'"
          :loading="busy"
        />
        <Button
          v-if="secret"
          label="Cancelar"
          severity="secondary"
          :disabled="busy"
          @click="cancel"
        />
      </form>
      <Button v-if="!ready" label="Cargar configuración" :loading="busy" @click="load" />
    </template>
  </Card>
</template>
<script setup>
import { onMounted, ref } from 'vue'
import api, { errorMessage } from '@/service/api'
import QRCode from 'qrcode'
const ready = ref(false),
  enabled = ref(false),
  busy = ref(false),
  password = ref(''),
  code = ref(''),
  secret = ref(''),
  qrImage = ref(''),
  message = ref(''),
  error = ref('')
async function load() {
  busy.value = true
  error.value = ''
  try {
    enabled.value = (await api.get('/auth/authenticator')).data.enabled
    ready.value = true
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
function cancel() {
  secret.value = ''
  qrImage.value = ''
  code.value = ''
  password.value = ''
  error.value = ''
}
async function submit() {
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    if (enabled.value) {
      await api.delete('/auth/authenticator', {
        data: { password: password.value, code: code.value },
      })
      enabled.value = false
      message.value = 'Authenticator desactivado. Puedes entrar con correo y contraseña.'
      cancel()
    } else if (secret.value) {
      await api.post('/auth/authenticator/confirm', { code: code.value })
      enabled.value = true
      message.value =
        'Authenticator activado. Se solicitará un código en tu próximo inicio de sesión.'
      cancel()
    } else {
      const { data } = await api.post('/auth/authenticator/setup', { password: password.value })
      const image = await QRCode.toDataURL(data.otpauth_uri, {
        width: 256,
        margin: 4,
        errorCorrectionLevel: 'M',
      })
      secret.value = data.secret
      qrImage.value = image
      password.value = ''
    }
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<style scoped>
.security-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  max-width: 34rem;
  margin-top: 1rem;
}
.setup-secret {
  display: block;
  overflow-wrap: anywhere;
  font-family: monospace;
  margin: 1rem 0;
}
.setup-qr {
  max-width: 100%;
  height: auto;
  background: white;
  border-radius: 8px;
}
summary {
  cursor: pointer;
}
</style>

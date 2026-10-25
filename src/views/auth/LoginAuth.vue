<template>
  <div class="contenedor-login">
    <div class="tarjeta-login">
      <div class="encabezado-login">
        <h2>Iniciar sesión</h2>
        <p>
          {{
            challenge
              ? 'Verifica tu Google Authenticator'
              : 'Ingresa tus credenciales para acceder al sistema'
          }}
        </p>
      </div>
      <form class="formulario-login" @submit.prevent="submit">
        <template v-if="!challenge">
          <label for="email">Correo electrónico</label
          ><InputText id="email" v-model="email" type="email" autocomplete="username" required />
          <label for="password">Contraseña</label
          ><Password
            inputId="password"
            v-model="password"
            autocomplete="current-password"
            :feedback="false"
            toggleMask
            required
            fluid
          />
        </template>
        <template v-else>
          <Message v-if="secret" severity="info" :closable="false"
            >En Google Authenticator pulsa + ? Introducir clave de configuración. Usa tu correo como
            nombre, selecciona «Basada en tiempo» e introduce esta clave:
            <strong class="secret">{{ secret }}</strong
            >. Guarda la clave antes de confirmar.</Message
          >
          <label for="code">Código de 6 dígitos</label
          ><InputText
            id="code"
            v-model="code"
            inputmode="numeric"
            pattern="[0-9]{6}"
            maxlength="6"
            autocomplete="one-time-code"
            required
          />
        </template>
        <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
        <Button
          type="submit"
          :label="challenge ? 'Verificar e ingresar' : 'Iniciar sesión'"
          :loading="loading"
        />
        <Button v-if="challenge" label="Volver" severity="secondary" @click="resetChallenge" />
      </form>
    </div>
  </div>
</template>
<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthService, errorMessage } from '@/service/api'
const router = useRouter(),
  auth = useAuthService()
const email = ref(''),
  password = ref(''),
  code = ref(''),
  challenge = ref(''),
  secret = ref(''),
  error = ref(''),
  loading = ref(false)
function resetChallenge() {
  challenge.value = ''
  secret.value = ''
  code.value = ''
  error.value = ''
}
async function submit() {
  loading.value = true
  error.value = ''
  try {
    const data = challenge.value
      ? await auth.verify({ challenge: challenge.value, code: code.value })
      : await auth.login({ email: email.value, password: password.value })
    if (data.requires_2fa) {
      challenge.value = data.challenge
      secret.value = data.setup_secret
      password.value = ''
    } else await router.push('/dashboard')
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
</script>
<style scoped>
.secret {
  display: block;
  overflow-wrap: anywhere;
  margin-top: 1rem;
  font-family: monospace;
}
:deep(.p-password-input) {
  width: 100%;
}
</style>

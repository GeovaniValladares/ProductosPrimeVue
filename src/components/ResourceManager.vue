<template>
  <Card
    ><template #title>{{ title }}</template
    ><template #content>
      <div class="toolbar">
        <InputText v-model="search" placeholder="Buscar…" aria-label="Buscar registros" />
        <Button v-if="editable" label="Nuevo" @click="edit()" />
        <Button
          label="CSV"
          severity="secondary"
          :disabled="!filtered.length"
          @click="exportCsv(filtered, fields, title)"
        />
        <Button label="PDF" severity="secondary" :disabled="!filtered.length" @click="pdf" />
        <Button label="Actualizar" severity="secondary" :loading="loading" @click="load" />
      </div>
      <Message v-if="error" severity="error" :closable="false">{{ error }}</Message>
      <Message v-if="notice" severity="success" :closable="false" role="status">{{
        notice
      }}</Message>
      <DataTable
        :value="filtered"
        :loading="loading"
        paginator
        :rows="5"
        :rowsPerPageOptions="[5, 10, 20]"
        scrollable
        dataKey="id"
      >
        <Column field="id" header="ID" />
        <Column v-for="f in fields" :key="f.key" :field="f.key" :header="f.label"
          ><template #body="{ data }"
            ><span class="plain-content">{{
              f.type === 'boolean' ? (data[f.key] ? 'Sí' : 'No') : data[f.key]
            }}</span></template
          ></Column
        >
        <Column v-if="editable" header="Acciones"
          ><template #body="{ data }"
            ><div class="toolbar">
              <Button label="Editar" size="small" @click="edit(data)" /><Button
                label="Eliminar"
                size="small"
                severity="danger"
                @click="deleting = data"
              /></div></template
        ></Column>
        <template #empty>No hay registros.</template>
      </DataTable>
      <ProductStockChart v-if="resource === 'productos'" :products="filtered" /> </template
  ></Card>
  <Dialog
    v-model:visible="visible"
    modal
    :header="form.id ? 'Editar registro' : 'Nuevo registro'"
    :style="{ width: 'min(95vw, 36rem)' }"
  >
    <form class="formulario-login" @submit.prevent="save">
      <template v-for="f in fields" :key="f.key">
        <label :for="resource + f.key">{{ f.label }}</label>
        <Textarea
          v-if="f.type === 'text'"
          :id="resource + f.key"
          v-model="form[f.key]"
          rows="5"
          :maxlength="f.key === 'cuerpo' ? 20000 : 5000"
        />
        <InputNumber
          v-else-if="f.type === 'number'"
          :inputId="resource + f.key"
          v-model="form[f.key]"
          :min="0"
          :maxFractionDigits="f.key === 'precio' ? 2 : 0"
          required
        />
        <Select
          v-else-if="f.type === 'role'"
          :inputId="resource + f.key"
          v-model="form[f.key]"
          :options="roles"
          optionLabel="label"
          optionValue="value"
        />
        <Checkbox
          v-else-if="f.type === 'boolean'"
          :inputId="resource + f.key"
          v-model="form[f.key]"
          binary
        />
        <InputText
          v-else
          :id="resource + f.key"
          v-model="form[f.key]"
          :type="f.key === 'email' ? 'email' : 'text'"
          maxlength="150"
          required
        />
      </template>
      <template v-if="resource === 'usuarios'"
        ><label for="user-password"
          >Contraseña
          {{ form.id ? '(dejar vacía para conservar)' : '(mínimo 10 caracteres)' }}</label
        ><Password
          inputId="user-password"
          v-model="form.password"
          :required="!form.id"
          :feedback="false"
          toggleMask
          fluid
      /></template>
      <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>
      <Button type="submit" label="Guardar" :loading="saving" />
    </form>
  </Dialog>
  <Dialog :visible="!!deleting" modal header="Eliminar registro" @update:visible="deleting = null"
    ><p>¿Eliminar «{{ deleting?.nombre || deleting?.name || deleting?.titulo }}»?</p>
    <div class="toolbar">
      <Button label="Cancelar" severity="secondary" @click="deleting = null" /><Button
        label="Eliminar"
        severity="danger"
        :loading="saving"
        @click="remove"
      />
    </div>
    <Message v-if="error" severity="error">{{ error }}</Message></Dialog
  >
</template>
<script setup>
import { ref, computed, onMounted } from 'vue'
import ProductStockChart from '@/components/ProductStockChart.vue'
import api, { errorMessage } from '@/service/api'
import { exportCsv, exportPdf } from '@/utils/reports'
const props = defineProps({ resource: String, title: String, fields: Array, editable: Boolean })
const roles = [
  { label: 'Usuario', value: 'user' },
  { label: 'Superadmin', value: 'superadmin' },
]
const notice = ref('')
const rows = ref([]),
  search = ref(''),
  loading = ref(false),
  error = ref(''),
  formError = ref(''),
  visible = ref(false),
  saving = ref(false),
  form = ref({}),
  deleting = ref(null)
const filtered = computed(() =>
  rows.value.filter((row) =>
    props.fields.some((f) =>
      String(row[f.key] ?? '')
        .toLowerCase()
        .includes(search.value.toLowerCase()),
    ),
  ),
)
async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = (await api.get('/' + props.resource)).data
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}
function edit(row) {
  notice.value = ''
  form.value = row
    ? { ...row, password: '' }
    : Object.fromEntries(
        props.fields.map((f) => [
          f.key,
          f.type === 'number' ? 0 : f.type === 'boolean' ? false : f.type === 'role' ? 'user' : '',
        ]),
      )
  formError.value = ''
  visible.value = true
}
async function save() {
  saving.value = true
  formError.value = ''
  try {
    const data = { ...form.value }
    if (!data.password) delete data.password
    if (data.id) await api.put(`/${props.resource}/${data.id}`, data)
    else await api.post('/' + props.resource, data)
    notice.value = data.id ? 'Registro actualizado.' : 'Registro creado.'
    visible.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
async function remove() {
  saving.value = true
  error.value = ''
  try {
    await api.delete(`/${props.resource}/${deleting.value.id}`)
    notice.value = 'Registro eliminado.'
    deleting.value = null
    await load()
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    saving.value = false
  }
}
async function pdf() {
  try {
    await exportPdf(filtered.value, props.fields, props.title)
  } catch {
    error.value = 'No se pudo generar el PDF.'
  }
}
onMounted(load)
</script>
<style scoped>
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
  align-items: center;
}
.plain-content {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
select {
  padding: 0.7rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
</style>

import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Card from 'primevue/card'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Textarea from 'primevue/textarea'
import InputNumber from 'primevue/inputnumber'
import Toast from 'primevue/toast'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'

export default {
  install: (app) => {
    app.component('InputText', InputText)
    app.component('Password', Password)
    app.component('Button', Button)
    app.component('Message', Message)
    app.component('Card', Card)
    app.component('DataTable', DataTable)
    app.component('Column', Column)
    app.component('Dialog', Dialog)
    app.component('Textarea', Textarea)
    app.component('InputNumber', InputNumber)
    app.component('Toast', Toast)
    app.component('Select', Select)
    app.component('Checkbox', Checkbox)
  },
}

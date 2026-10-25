import { afterEach, describe, expect, it, vi } from 'vitest'
import { createPdf, exportCsv } from '@/utils/reports'

afterEach(() => vi.restoreAllMocks())
describe('Reportes en Vue', () => {
  it('genera un PDF real con encabezados y datos de los productos', async () => {
    const doc = await createPdf(
      [{ nombre: 'Teclado', precio: 25 }],
      [
        { key: 'nombre', label: 'Nombre' },
        { key: 'precio', label: 'Precio' },
      ],
      'Productos',
    )
    const output = doc.output()
    expect(output.startsWith('%PDF-')).toBe(true)
    expect(output).toContain('Teclado')
    expect(output).toContain('Precio')
  })
  it('descarga CSV con acentos, comillas escapadas y protección de fórmulas', async () => {
    let blob
    URL.createObjectURL = (value) => {
      blob = value
      return 'blob:report'
    }
    URL.revokeObjectURL = () => {}
    let filename
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
      filename = this.download
    })
    exportCsv(
      [{ nombre: 'Café "A"', nota: '=1+1' }],
      [
        { key: 'nombre', label: 'Nombre' },
        { key: 'nota', label: 'Nota' },
      ],
      'Productos',
    )
    const text = await new Promise((resolve, reject) => {
      const reader = new FileReader()
      reader.onload = () => resolve(reader.result)
      reader.onerror = reject
      reader.readAsText(blob)
    })
    expect(filename).toBe('Productos.csv')
    expect(text).toContain('"Café ""A"""')
    expect(text).toContain('"\'=1+1"')
  })
})

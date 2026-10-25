export const csvCell = (value) => {
  let text = String(value ?? '')
  if (/^[\s]*[=+@-]/.test(text)) text = "'" + text
  return '"' + text.replaceAll('"', '""') + '"'
}
export function exportCsv(rows, fields, filename) {
  const csv =
    '\uFEFF' +
    [
      fields.map((f) => csvCell(f.label)).join(','),
      ...rows.map((row) => fields.map((f) => csvCell(row[f.key])).join(',')),
    ].join('\r\n')
  const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = filename + '.csv'
  a.click()
  URL.revokeObjectURL(url)
}
export async function createPdf(rows, fields, filename) {
  const [{ jsPDF }, { default: autoTable }] = await Promise.all([
    import('jspdf'),
    import('jspdf-autotable'),
  ])
  const doc = new jsPDF()
  doc.text(filename, 14, 16)
  autoTable(doc, {
    startY: 24,
    head: [fields.map((f) => f.label)],
    body: rows.map((row) => fields.map((f) => String(row[f.key] ?? ''))),
  })
  return doc
}
export async function exportPdf(rows, fields, filename) {
  const doc = await createPdf(rows, fields, filename)
  doc.save(filename + '.pdf')
}

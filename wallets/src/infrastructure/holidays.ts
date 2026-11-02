export async function importHolidays(file: File | null) {
  if (!file) throw new Error('Chọn file để import.')
  if (file.size > 5 * 1024 * 1024) throw new Error('File tối đa 5 MB.')
  const { read, utils } = await import('xlsx')
  const book = read(await file.arrayBuffer(), {
    type: 'array',
    cellDates: true,
  })
  const rows = utils.sheet_to_json<Record<string, unknown>>(
    book.Sheets[book.SheetNames[0]!]!,
    { defval: '' },
  )
  if (!rows.length || rows.length > 10000)
    throw new Error('File cần có 1–10.000 dòng và cột date, name, type.')
  return rows.map((row, index) => {
    const value = row.date
    const date =
      value instanceof Date
        ? `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`
        : String(value ?? '').trim()
    if (!date || !row.name)
      throw new Error(`Dòng ${index + 2}: thiếu date/name.`)
    return {
      date,
      name: String(row.name).trim(),
      type: String(row.type || 'public').trim(),
    }
  })
}

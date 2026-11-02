import { describe, it, expect } from 'vitest'
import { utils, write } from 'xlsx'
import { importHolidays } from '../infrastructure/holidays'
function file(bytes: ArrayBuffer) {
  const value = new File([bytes], 'holidays.xlsx')
  Object.defineProperty(value, 'arrayBuffer', { value: async () => bytes })
  return value
}
describe('Holiday imports', () => {
  it('reads CSV into the Rust action contract', async () => {
    const bytes = new TextEncoder().encode(
      'date,name,type\n2027-01-01,New Year,public\n',
    ).buffer
    expect(await importHolidays(file(bytes))).toEqual([
      { date: '2027-01-01', name: 'New Year', type: 'public' },
    ])
  })
  for (const bookType of ['xlsx', 'xls'] as const)
    it(`reads ${bookType} workbooks`, async () => {
      const book = utils.book_new()
      utils.book_append_sheet(
        book,
        utils.json_to_sheet([
          { date: '2027-01-01', name: 'New Year', type: 'public' },
        ]),
        'Holidays',
      )
      const bytes = write(book, { type: 'array', bookType }) as ArrayBuffer
      expect(await importHolidays(file(bytes))).toEqual([
        { date: '2027-01-01', name: 'New Year', type: 'public' },
      ])
    })
  it('rejects missing files and incomplete rows', async () => {
    await expect(importHolidays(null)).rejects.toThrow('Chọn file')
    await expect(
      importHolidays(
        file(new TextEncoder().encode('date,name\n2027-01-01,\n').buffer),
      ),
    ).rejects.toThrow('thiếu date/name')
  })
})

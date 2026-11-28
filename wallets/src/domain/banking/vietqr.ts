// Parser for VietQR (NAPAS EMVCo merchant-presented) transfer codes.
// Spec: EMVCo QRCPS-MPM + NAPAS VietQR, tag 38 holds the beneficiary.

import { findBankByBin, type Bank } from './banks'

export interface VietQrTransfer {
  bin: string
  bank: Bank | undefined
  accountNumber: string
  /** QRIBFTTA = account, QRIBFTTC = card number. */
  service: 'account' | 'card'
  amount: number | null
  message: string
  merchantName: string
  /** 11 = static (reusable), 12 = dynamic (one-off with amount). */
  dynamic: boolean
}

export class VietQrError extends Error {}

const NAPAS_GUID = 'A000000727'

/** Split an EMVCo TLV string ("IDLLvalue…") into a tag → value map. */
export function parseTlv(input: string): Map<string, string> {
  const fields = new Map<string, string>()
  let i = 0
  while (i < input.length) {
    if (i + 4 > input.length) throw new VietQrError('Mã QR bị cắt ngắn.')
    const tag = input.slice(i, i + 2)
    const length = Number(input.slice(i + 2, i + 4))
    if (!/^\d{2}$/.test(tag) || !Number.isInteger(length) || length < 0)
      throw new VietQrError('Mã QR không đúng định dạng.')
    const value = input.slice(i + 4, i + 4 + length)
    if (value.length !== length) throw new VietQrError('Mã QR bị cắt ngắn.')
    fields.set(tag, value)
    i += 4 + length
  }
  return fields
}

/** CRC-16/CCITT-FALSE as required by EMVCo tag 63. */
export function crc16(data: string): string {
  let crc = 0xffff
  for (const byte of new TextEncoder().encode(data)) {
    crc ^= byte << 8
    for (let bit = 0; bit < 8; bit++)
      crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff
  }
  return crc.toString(16).toUpperCase().padStart(4, '0')
}

export function parseVietQr(raw: string): VietQrTransfer {
  const input = raw.trim()
  if (!input.startsWith('000201'))
    throw new VietQrError('Đây không phải mã QR chuyển khoản VietQR.')
  const root = parseTlv(input)
  const crc = root.get('63')
  if (crc !== undefined) {
    if (!input.endsWith(`6304${crc}`))
      throw new VietQrError('Mã QR không đúng định dạng.')
    if (crc16(input.slice(0, -4)).toUpperCase() !== crc.toUpperCase())
      throw new VietQrError('Mã QR bị lỗi (sai mã kiểm tra).')
  }
  const merchant = root.get('38')
  if (!merchant)
    throw new VietQrError(
      'Mã QR này không phải chuyển khoản ngân hàng (VietQR/NAPAS).',
    )
  const account = parseTlv(merchant)
  if (account.get('00')?.toUpperCase() !== NAPAS_GUID)
    throw new VietQrError('Mã QR này không phải chuyển khoản qua NAPAS.')
  const beneficiary = parseTlv(account.get('01') ?? '')
  const bin = beneficiary.get('00') ?? ''
  const accountNumber = beneficiary.get('01') ?? ''
  if (!/^\d{6}$/.test(bin) || !accountNumber)
    throw new VietQrError('Mã QR thiếu ngân hàng hoặc số tài khoản.')

  const amountText = root.get('54')
  let amount: number | null = null
  if (amountText) {
    amount = Number(amountText)
    if (!Number.isFinite(amount) || amount < 0)
      throw new VietQrError('Số tiền trong mã QR không hợp lệ.')
  }
  const additional = root.get('62')
  const message = additional ? (parseTlv(additional).get('08') ?? '') : ''

  return {
    bin,
    bank: findBankByBin(bin),
    accountNumber,
    service: account.get('02') === 'QRIBFTTC' ? 'card' : 'account',
    amount,
    message: message.trim(),
    merchantName: (root.get('59') ?? '').trim(),
    dynamic: root.get('01') === '12',
  }
}

function field(tag: string, value: string): string {
  return `${tag}${String(value.length).padStart(2, '0')}${value}`
}

/** Build a VietQR payload; used by tests and to share an account as QR. */
export function buildVietQr(input: {
  bin: string
  accountNumber: string
  amount?: number | null
  message?: string
  card?: boolean
}): string {
  const beneficiary = field('00', input.bin) + field('01', input.accountNumber)
  const merchant =
    field('00', NAPAS_GUID) +
    field('01', beneficiary) +
    field('02', input.card ? 'QRIBFTTC' : 'QRIBFTTA')
  let body =
    field('00', '01') +
    field('01', input.amount ? '12' : '11') +
    field('38', merchant) +
    field('53', '704')
  if (input.amount) body += field('54', String(Math.round(input.amount)))
  body += field('58', 'VN')
  if (input.message) body += field('62', field('08', input.message))
  body += '6304'
  return body + crc16(body)
}

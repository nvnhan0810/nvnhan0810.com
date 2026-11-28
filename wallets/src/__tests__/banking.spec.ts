import { describe, expect, it } from 'vitest'
import {
  BANKS,
  bankAppLink,
  buildVietQr,
  findBankByBin,
  findBankByCode,
  maskAccountNumber,
  normalizeAccountNumber,
  parseVietQr,
  transferNote,
  VietQrError,
  walletBank,
} from '../domain'
import { crc16, parseTlv } from '../domain/banking/vietqr'
import { BANK_APPS, toBanks } from '../domain/banking/bank-list'
import master from '../domain/banking/vietqr-banks.json'
import { planBankHandoff } from '../application/bank-handoff'
import { currentPlatform, hasUserActivation } from '../infrastructure/device'

const vcb = findBankByCode('VCB')!
const mb = findBankByBin('970422')!

describe('bank directory', () => {
  it('has unique VietQR BINs and codes', () => {
    expect(new Set(BANKS.map((b) => b.bin)).size).toBe(BANKS.length)
    expect(new Set(BANKS.map((b) => b.code)).size).toBe(BANKS.length)
    expect(BANKS.every((b) => /^\d{6}$/.test(b.bin))).toBe(true)
  })

  it('is built from the verbatim vietqr.app/banks.json master data', () => {
    expect(BANKS).toHaveLength(Number(master.no_banks))
    expect(BANKS.map((b) => [b.bin, b.code])).toEqual(
      master.data.map((r) => [r.bin, r.code]),
    )
    expect(vcb).toMatchObject({ shortName: 'Vietcombank', app: 'vcb' })
    // Every app mapping points at a bank code that exists in the master data.
    const codes = new Set(master.data.map((r) => r.code))
    expect(Object.keys(BANK_APPS).filter((c) => !codes.has(c))).toEqual([])
  })

  it('merges app ids by code and keeps unknown banks selectable', () => {
    const banks = toBanks(
      {
        data: [
          { name: 'Ngân hàng A', code: 'AAA', bin: '970999', short_name: 'A' },
          { name: 'Ngân hàng B', code: 'BBB', bin: '970998', short_name: 'B' },
        ],
      },
      { AAA: { app: 'aaa', appName: 'A Mobile' } },
    )
    expect(banks[0]).toEqual({
      code: 'AAA',
      bin: '970999',
      shortName: 'A',
      name: 'Ngân hàng A',
      app: 'aaa',
      appName: 'A Mobile',
    })
    expect(banks[1].app).toBeUndefined()
  })

  it('finds banks by BIN or code and maps wallets', () => {
    expect(vcb.bin).toBe('970436')
    expect(mb.code).toBe('MB')
    expect(findBankByCode('vcb')).toBe(vcb)
    expect(walletBank({ bank_bin: null, bank_code: 'mb' })).toBe(mb)
    expect(walletBank({ bank_bin: '970436', bank_code: 'MB' })).toBe(vcb)
    expect(walletBank({})).toBeUndefined()
  })

  it('formats account numbers', () => {
    expect(maskAccountNumber('0011001932418')).toBe('•••• 2418')
    expect(maskAccountNumber('123')).toBe('123')
    expect(normalizeAccountNumber(' 0011-0019 32418 ')).toBe('0011001932418')
  })
})

describe('VietQR', () => {
  it('computes the EMVCo CRC (CRC-16/CCITT-FALSE)', () => {
    expect(crc16('123456789')).toBe('29B1')
  })

  it('parses a dynamic transfer code with amount and message', () => {
    const raw = buildVietQr({
      bin: '970436',
      accountNumber: '0011001932418',
      amount: 150000,
      message: 'Tien an trua',
    })
    const qr = parseVietQr(raw)
    expect(qr).toMatchObject({
      bin: '970436',
      accountNumber: '0011001932418',
      amount: 150000,
      message: 'Tien an trua',
      service: 'account',
      dynamic: true,
    })
    expect(qr.bank).toBe(vcb)
  })

  it('parses a static code with merchant name and no amount', () => {
    const body = buildVietQr({ bin: '970422', accountNumber: '0123456789' })
    // Insert a merchant name (tag 59) before the CRC and re-sign.
    const unsigned = body.slice(0, -8) + '5912NGUYEN VAN A' + '6304'
    const qr = parseVietQr(unsigned + crc16(unsigned))
    expect(qr.amount).toBeNull()
    expect(qr.dynamic).toBe(false)
    expect(qr.merchantName).toBe('NGUYEN VAN A')
    expect(qr.bank).toBe(mb)
  })

  it('rejects other QR codes and broken checksums', () => {
    expect(() => parseVietQr('https://example.com')).toThrow(VietQrError)
    const raw = buildVietQr({ bin: '970436', accountNumber: '1', amount: 1 })
    expect(() => parseVietQr(raw.slice(0, -1) + '0')).toThrow(/kiểm tra/)
    // EMVCo code without NAPAS tag 38 (e.g. a card-scheme merchant QR).
    const other = '000201010211' + '26080004ABCD' + '5303704' + '6304'
    expect(() => parseVietQr(other + crc16(other))).toThrow(VietQrError)
    expect(() => parseTlv('0005abc')).toThrow(VietQrError)
  })
})

describe('bank app deeplinks', () => {
  it('opens the source bank app, prefilled from a QR', () => {
    expect(bankAppLink(vcb, 'android')).toBe('https://dl.vietqr.io/pay?app=vcb')
    const url = new URL(
      bankAppLink(vcb, 'ios', {
        bank: mb,
        accountNumber: '0123456789',
        amount: 150000.4,
        message: 'Trả tiền ăn trưa — đợt 1!',
      })!,
    )
    expect(url.searchParams.get('app')).toBe('vcb')
    expect(url.searchParams.get('ba')).toBe('0123456789@mb')
    expect(url.searchParams.get('am')).toBe('150000')
    expect(url.searchParams.get('tn')).toBe('Tra tien an trua dot 1')
  })

  it('uses the iOS app id where it differs and skips banks without app', () => {
    const vib = findBankByCode('VIB')!
    expect(bankAppLink(vib, 'ios')).toContain('app=vib-2')
    expect(bankAppLink(vib, 'android')).toContain('app=vib')
    expect(bankAppLink(findBankByCode('MOMO')!, 'android')).toBeUndefined()
  })

  it('limits transfer notes to 50 ASCII characters', () => {
    expect(transferNote('Đặng Văn Đức chuyển tiền')).toBe(
      'Dang Van Duc chuyen tien',
    )
    expect(transferNote('x'.repeat(80))).toHaveLength(50)
  })

  it('plans a handoff only for bank wallets on phones', () => {
    const wallet = { type: 'bank', bank_bin: '970436' }
    expect(planBankHandoff(wallet, undefined, 'android')).toMatchObject({
      appName: 'VCB Digibank',
      link: 'https://dl.vietqr.io/pay?app=vcb',
      autofill: false,
    })
    expect(planBankHandoff(wallet, undefined, 'other')).toBeUndefined()
    expect(
      planBankHandoff({ type: 'cash', bank_bin: '970436' }, undefined, 'ios'),
    ).toBeUndefined()
    expect(planBankHandoff({ type: 'bank' }, undefined, 'ios')).toBeUndefined()
  })

  it('detects the platform from the user agent', () => {
    const nav = (userAgent: string, maxTouchPoints = 0) => ({
      userAgent,
      maxTouchPoints,
    })
    expect(currentPlatform(nav('Mozilla/5.0 (Linux; Android 15)'))).toBe(
      'android',
    )
    expect(currentPlatform(nav('Mozilla/5.0 (iPhone; CPU iPhone OS 18)'))).toBe(
      'ios',
    )
    expect(currentPlatform(nav('Mozilla/5.0 (Macintosh)', 5))).toBe('ios')
    expect(currentPlatform(nav('Mozilla/5.0 (Macintosh)'))).toBe('other')
  })

  it('reads the user activation state, assuming active when unknown', () => {
    expect(hasUserActivation({ userActivation: { isActive: false } })).toBe(
      false,
    )
    expect(hasUserActivation({ userActivation: { isActive: true } })).toBe(true)
    expect(hasUserActivation({})).toBe(true)
  })
})

// Bank master data for the Wallets app.
//
// - `vietqr-banks.json` is a verbatim copy of https://vietqr.app/banks.json
//   (BIN, code, names). To update, download that file again and replace it;
//   nothing else has to change.
// - `BANK_APPS` maps a VietQR bank code to its deeplink app id
//   (https://api.vietqr.io/v2/{android,ios}-app-deeplinks). Banks missing here
//   are still selectable; they just cannot open an app.
//
// The wallet form only asks the user to pick a bank: BIN and code always come
// from this master data.

import master from './vietqr-banks.json'

export interface Bank {
  code: string
  bin: string
  shortName: string
  name: string
  app?: string
  /** iOS uses a different app id for a few banks. */
  iosApp?: string
  appName?: string
  /** App accepts a prefilled recipient/amount. */
  autofill?: boolean
}

/** Shape of one entry in vietqr.app/banks.json. */
interface VietQrBankRecord {
  name: string
  code: string
  bin: string
  short_name: string
  supported?: boolean
}

interface VietQrBankList {
  data: VietQrBankRecord[]
}

type BankApp = Pick<Bank, 'app' | 'iosApp' | 'appName' | 'autofill'>

export const BANK_APPS: Readonly<Record<string, BankApp>> = {
  ICB: { app: 'icb', appName: 'VietinBank iPay', autofill: true },
  VCB: { app: 'vcb', appName: 'VCB Digibank' },
  MB: { app: 'mb', appName: 'MB Bank', autofill: true },
  ACB: { app: 'acb', appName: 'ACB ONE', autofill: true },
  VPB: { app: 'vpb', appName: 'VPBank NEO' },
  TPB: { app: 'tpb', appName: 'TPBank Mobile' },
  NAB: { app: 'nab', appName: 'Nam A Bank Open Banking' },
  LPB: { app: 'lpb', appName: 'LPBank' },
  BIDV: { app: 'bidv', appName: 'BIDV SmartBanking', autofill: true },
  VIB: { app: 'vib', iosApp: 'vib-2', appName: 'MyVIB' },
  HDB: { app: 'hdb', appName: 'HDBank' },
  SEAB: { app: 'seab', appName: 'SeAMobile' },
  PVCB: { app: 'pvcb', appName: 'PVConnect' },
  NCB: { app: 'ncb', appName: 'NCB iziMobile' },
  SHBVN: { app: 'shbvn', appName: 'Shinhan SOL Việt Nam' },
  SCB: { app: 'scb', appName: 'SCB Mobile Banking' },
  VBA: { app: 'vba', appName: 'Agribank Plus' },
  TCB: { app: 'tcb', appName: 'Techcombank Mobile' },
  SGICB: { app: 'sgicb', appName: 'SAIGONBANK Smart Banking' },
  ABB: { app: 'abb', appName: 'ABBank' },
  EIB: { app: 'eib', appName: 'Eximbank EDigi' },
  VIETBANK: { app: 'vab', appName: 'Vietbank Digital Plus' },
  BVB: { app: 'bvb', appName: 'BAOVIET Smart' },
  PBVN: { app: 'pbvn', appName: 'PB engage VN' },
  SHB: { app: 'shb', appName: 'SHB SAHA' },
  OCB: { app: 'ocb', appName: 'OCB OMNI', autofill: true },
  KLB: { app: 'klb', appName: 'KienlongBank Plus' },
  CIMB: { app: 'cimb', appName: 'Octo by CIMB Vietnam' },
  WVN: { app: 'wvn', appName: 'Woori WON Vietnam' },
  COOPBANK: { app: 'coopbank', appName: 'Co-opBank Mobile Banking' },
}

export function toBanks(
  list: VietQrBankList,
  apps: Readonly<Record<string, BankApp>> = BANK_APPS,
): Bank[] {
  return list.data.map((record) => ({
    code: record.code,
    bin: record.bin,
    shortName: record.short_name,
    name: record.name,
    ...apps[record.code],
  }))
}

// JSON imports are typed from the file's contents; narrow to the documented shape.
const vietQrMaster: VietQrBankList = master

export const BANKS: readonly Bank[] = toBanks(vietQrMaster)

import { ref, shallowRef } from 'vue'
export const notices = ref<{ id: number; message: string; kind: 'success' | 'error' }[]>([])
let serial = 0
export function notify(message: string, kind: 'success' | 'error' = 'success'): void {
  const id = ++serial
  notices.value.push({ id, message, kind })
  setTimeout(() => {
    notices.value = notices.value.filter((item) => item.id !== id)
  }, 6500)
}
export interface Confirmation {
  title: string
  message: string
  label: string
  danger?: boolean
}
export const confirmation = shallowRef<Confirmation | null>(null)
let resolveConfirmation: ((value: boolean) => void) | null = null
export function confirmAction(options: Confirmation): Promise<boolean> {
  resolveConfirmation?.(false)
  confirmation.value = options
  return new Promise((resolve) => {
    resolveConfirmation = resolve
  })
}
export function settleConfirmation(accepted: boolean): void {
  confirmation.value = null
  resolveConfirmation?.(accepted)
  resolveConfirmation = null
}
export async function copyText(value: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(value)
    notify('Đã sao chép.')
  } catch {
    notify('Không sao chép được. Bạn có thể chọn và sao chép nội dung trực tiếp.', 'error')
  }
}

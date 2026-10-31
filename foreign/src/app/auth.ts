import { ref } from 'vue'
import { session } from './services'
import { initializeSession } from '../modules/foreign/application/use-cases/initialize-session'
import { errorMessage } from '../modules/foreign/infrastructure/transport'
export const authError = ref('')
export const authReady = ref(false)
let initialization: Promise<string | null> | undefined
export function ensureSession() {
  return (initialization ??= initializeSession(
    session,
    new URL(window.location.href),
  )
    .catch((error) => {
      authError.value = errorMessage(error)
      return '/login'
    })
    .finally(() => {
      authReady.value = true
    }))
}
export async function login() {
  authError.value = ''
  try {
    await session.login()
  } catch (error) {
    authError.value = errorMessage(error)
  }
}
export async function logout() {
  authError.value = ''
  try {
    await session.logout()
  } catch {
    authError.value =
      'Signed out locally. Could not revoke the Identity session; please retry when online.'
  }
}

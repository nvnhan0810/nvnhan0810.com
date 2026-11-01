import { ref } from 'vue'

export const pendingRequests = ref(0)

export async function withLoading<T>(operation: () => Promise<T>): Promise<T> {
  pendingRequests.value++
  try {
    return await operation()
  } finally {
    pendingRequests.value--
  }
}

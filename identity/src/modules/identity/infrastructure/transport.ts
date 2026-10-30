export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
  ) {
    super(code)
    this.name = 'ApiError'
  }
}
export async function fetchJson<T>(
  url: string,
  init: RequestInit = {},
  transport: typeof fetch = fetch,
): Promise<T> {
  let response: Response
  try {
    response = await transport(url, {
      ...init,
      signal: init.signal ?? AbortSignal.timeout(15000),
      cache: 'no-store',
    })
  } catch {
    throw new ApiError(0, 'network_error')
  }
  let data: unknown
  try {
    data = await response.json()
  } catch {
    data = null
  }
  if (!response.ok) {
    const code =
      typeof data === 'object' && data !== null && 'error' in data && typeof data.error === 'string'
        ? data.error
        : `http_${response.status}`
    throw new ApiError(response.status, code)
  }
  return data as T
}
export function errorMessage(error: unknown): string {
  if (!(error instanceof ApiError))
    return error instanceof Error ? error.message : 'Đã có lỗi xảy ra. Vui lòng thử lại.'
  const messages: Record<string, string> = {
    network_error: 'Không kết nối được Identity API. Kiểm tra mạng, địa chỉ API và cấu hình CORS.',
    invalid_client: 'Client đăng nhập chưa được đăng ký, đã bị tắt hoặc cấu hình không đúng.',
    invalid_grant: 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
    invalid_scope: 'Client chưa được cấp scope cần thiết. Kiểm tra cấu hình client trên Identity.',
    access_denied: 'Tài khoản không có quyền thực hiện thao tác này.',
    conflict: 'Client ID đã tồn tại. Hãy chọn một ID khác.',
    version_conflict:
      'Dữ liệu đã được thay đổi ở nơi khác. Đóng cửa sổ, tải lại danh sách rồi thử lại.',
    invalid_client_metadata: 'Thông tin client không hợp lệ. Kiểm tra callback và các scope.',
    system_root_protected: 'Tài khoản system root được bảo vệ và không thể bị khóa.',
    server_error: 'Identity đang gặp sự cố. Vui lòng thử lại sau.',
    invalid_request: 'Yêu cầu chưa hợp lệ. Kiểm tra cấu hình client và callback.',
  }
  return (
    messages[error.code] ??
    (error.status === 429
      ? 'Có quá nhiều yêu cầu. Chờ một chút rồi thử lại.'
      : `Yêu cầu thất bại (${error.status}).`)
  )
}

import { session } from "../app/services";
import { ApiError } from "./transport";
export class ValidationError extends Error {
  constructor(public errors: Record<string, string>) {
    super(Object.values(errors)[0] || "Dữ liệu không hợp lệ.");
  }
}
export async function request<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const token = await session.accessToken(attempt > 0);
    const response = await fetch(`${session.settings.baseUrl}/todos${path}`, {
      ...init,
      cache: "no-store",
      signal: init.signal ?? AbortSignal.timeout(15000),
      headers: {
        "Content-Type": "application/json",
        ...init.headers,
        Authorization: `Bearer ${token}`,
      },
    });
    if (response.status === 401 && attempt === 0) continue;
    const data = await response.json();
    if (response.status === 422 && data.errors)
      throw new ValidationError(data.errors);
    if (!response.ok)
      throw new ApiError(response.status, data.error || "request_failed");
    return data as T;
  }
  throw new ApiError(401, "invalid_token");
}
export function action<T>(path: string, method: string, data: unknown = {}) {
  return request<T>("/actions", {
    method: "POST",
    body: JSON.stringify({ path, method, data }),
  });
}

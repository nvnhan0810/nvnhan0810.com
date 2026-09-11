function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

  return match ? decodeURIComponent(match[1]) : "";
}

export class JsonFetchError extends Error {
  cancelled: boolean;

  constructor(message: string, cancelled = false) {
    super(message);
    this.name = "JsonFetchError";
    this.cancelled = cancelled;
  }
}

type JsonFetchOptions = {
  method?: "GET" | "POST";
  body?: unknown;
  signal?: AbortSignal;
};

export async function jsonFetch<T>(url: string, options: JsonFetchOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  const csrfToken = getCsrfToken();

  if (csrfToken) {
    headers["X-XSRF-TOKEN"] = csrfToken;
  }

  const init: RequestInit = {
    method: options.method ?? "GET",
    headers,
    credentials: "same-origin",
    signal: options.signal,
  };

  if (options.body !== undefined) {
    headers["Content-Type"] = "application/json";
    init.body = JSON.stringify(options.body);
  }

  let response: Response;

  try {
    response = await fetch(url, init);
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new JsonFetchError("Đã hủy yêu cầu", true);
    }

    throw error;
  }

  let data: Record<string, unknown> = {};

  try {
    data = (await response.json()) as Record<string, unknown>;
  } catch {
    data = {};
  }

  if (!response.ok) {
    const message =
      typeof data.message === "string"
        ? data.message
        : "Yêu cầu thất bại, thử lại sau.";

    throw new JsonFetchError(message, data.cancelled === true || response.status === 499);
  }

  return data as T;
}

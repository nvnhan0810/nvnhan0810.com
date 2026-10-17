import { JsonFetchError } from "@/ts/utils/jsonFetch";
import { parsePageInk, type PageInk } from "../domain/page-ink";

export type PageInkSnapshot = {
  pageIndex: number;
  revision: number;
  ink: PageInk;
};

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : "";
}

function readError(data: Record<string, unknown>): string {
  const error = data.error;
  if (typeof error === "object" && error !== null) {
    const record = error as Record<string, unknown>;
    if (typeof record.message === "string") {
      return record.message;
    }
  }
  if (typeof data.message === "string") {
    return data.message;
  }
  return "Yêu cầu thất bại, thử lại sau.";
}

async function inkFetch<T>(url: string, signal?: AbortSignal): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  const csrf = getCsrfToken();
  if (csrf) {
    headers["X-XSRF-TOKEN"] = csrf;
  }

  let response: Response;
  try {
    response = await fetch(url, {
      method: "GET",
      headers,
      credentials: "same-origin",
      signal,
    });
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
    throw new JsonFetchError(readError(data), response.status === 499);
  }

  return data as T;
}

export type PageInkHttp = {
  list: (documentId: string, signal?: AbortSignal) => Promise<PageInkSnapshot[]>;
};

export function createPageInkHttp(): PageInkHttp {
  return {
    async list(documentId, signal) {
      const data = await inkFetch<{
        pages?: Array<{
          page_index: number;
          revision: number;
          ink: unknown;
        }>;
      }>(`/admin/reader/documents/${documentId}/inks`, signal);

      const pages = Array.isArray(data.pages) ? data.pages : [];
      return pages.map((page) => ({
        pageIndex: page.page_index,
        revision: page.revision,
        ink: parsePageInk(page.ink),
      }));
    },
  };
}

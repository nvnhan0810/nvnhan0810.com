import { JsonFetchError } from "@/ts/utils/jsonFetch";
import {
  parsePageMarkup,
  pageMarkupToApiPayload,
  type PageMarkup,
} from "../domain/page-markup";

export class MarkupConflictError extends Error {
  readonly serverRevision: number | null;

  constructor(message: string, serverRevision: number | null) {
    super(message);
    this.name = "MarkupConflictError";
    this.serverRevision = serverRevision;
  }
}

export type PageMarkupSnapshot = {
  pageIndex: number;
  revision: number;
  markup: PageMarkup;
};

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : "";
}

function readError(data: Record<string, unknown>): {
  message: string;
  code: string | null;
  serverRevision: number | null;
} {
  const error = data.error;
  if (typeof error === "object" && error !== null) {
    const record = error as Record<string, unknown>;
    const details = record.details;
    let serverRevision: number | null = null;
    if (typeof details === "object" && details !== null) {
      const rev = (details as Record<string, unknown>).server_revision;
      if (typeof rev === "number" && Number.isInteger(rev)) {
        serverRevision = rev;
      }
    }
    return {
      message:
        typeof record.message === "string"
          ? record.message
          : "Yêu cầu thất bại, thử lại sau.",
      code: typeof record.code === "string" ? record.code : null,
      serverRevision,
    };
  }
  return {
    message:
      typeof data.message === "string"
        ? data.message
        : "Yêu cầu thất bại, thử lại sau.",
    code: null,
    serverRevision: null,
  };
}

async function markupFetch<T>(
  url: string,
  options: { method: "GET" | "PUT"; body?: unknown; signal?: AbortSignal },
): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  const csrf = getCsrfToken();
  if (csrf) {
    headers["X-XSRF-TOKEN"] = csrf;
  }
  const init: RequestInit = {
    method: options.method,
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
    const { message, code, serverRevision } = readError(data);
    if (response.status === 409 || code === "conflict") {
      throw new MarkupConflictError(message, serverRevision);
    }
    throw new JsonFetchError(message, response.status === 499);
  }

  return data as T;
}

export type PageMarkupHttp = {
  list: (documentId: string, signal?: AbortSignal) => Promise<PageMarkupSnapshot[]>;
  put: (
    documentId: string,
    pageIndex: number,
    markup: PageMarkup,
    baseRevision: number | null,
    signal?: AbortSignal,
  ) => Promise<PageMarkupSnapshot>;
};

export function createPageMarkupHttp(): PageMarkupHttp {
  return {
    async list(documentId, signal) {
      const data = await markupFetch<{
        pages?: Array<{
          page_index: number;
          revision: number;
          markup: unknown;
        }>;
      }>(`/admin/reader/documents/${documentId}/markups`, {
        method: "GET",
        signal,
      });

      const pages = Array.isArray(data.pages) ? data.pages : [];
      return pages.map((page) => ({
        pageIndex: page.page_index,
        revision: page.revision,
        markup: parsePageMarkup(page.markup),
      }));
    },

    async put(documentId, pageIndex, markup, baseRevision, signal) {
      const body: Record<string, unknown> = {
        markup: pageMarkupToApiPayload(markup),
      };
      if (baseRevision !== null && baseRevision > 0) {
        body.base_revision = baseRevision;
      }

      const data = await markupFetch<{
        page_index: number;
        revision: number;
        markup?: unknown;
      }>(`/admin/reader/documents/${documentId}/markups/${pageIndex}`, {
        method: "PUT",
        body,
        signal,
      });

      return {
        pageIndex: data.page_index,
        revision: data.revision,
        markup: parsePageMarkup(data.markup ?? markup),
      };
    },
  };
}

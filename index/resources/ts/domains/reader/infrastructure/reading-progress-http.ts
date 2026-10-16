import { JsonFetchError } from "@/ts/utils/jsonFetch";
import {
  parseReadingProgress,
  type ReadingProgress,
} from "../domain/reading-progress";

export class ProgressConflictError extends Error {
  readonly serverRevision: number | null;

  constructor(message: string, serverRevision: number | null) {
    super(message);
    this.name = "ProgressConflictError";
    this.serverRevision = serverRevision;
  }
}

type PutProgressBody = {
  page_index: number;
  base_revision?: number;
};

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

  return match ? decodeURIComponent(match[1]) : "";
}

function readErrorPayload(data: Record<string, unknown>): {
  message: string;
  code: string | null;
  serverRevision: number | null;
} {
  const error = data.error;
  if (typeof error === "object" && error !== null) {
    const record = error as Record<string, unknown>;
    const message =
      typeof record.message === "string"
        ? record.message
        : "Yêu cầu thất bại, thử lại sau.";
    const code = typeof record.code === "string" ? record.code : null;
    const details = record.details;
    let serverRevision: number | null = null;
    if (typeof details === "object" && details !== null) {
      const rev = (details as Record<string, unknown>).server_revision;
      if (typeof rev === "number" && Number.isInteger(rev)) {
        serverRevision = rev;
      }
    }

    return { message, code, serverRevision };
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

async function progressFetch(
  url: string,
  options: { method: "GET" | "PUT"; body?: PutProgressBody; signal?: AbortSignal },
): Promise<ReadingProgress> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };

  const csrfToken = getCsrfToken();
  if (csrfToken) {
    headers["X-XSRF-TOKEN"] = csrfToken;
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
    const { message, code, serverRevision } = readErrorPayload(data);
    if (response.status === 409 || code === "conflict") {
      throw new ProgressConflictError(message, serverRevision);
    }
    throw new JsonFetchError(message, response.status === 499);
  }

  return parseReadingProgress(data);
}

export type ReadingProgressHttp = {
  get: (documentId: string, signal?: AbortSignal) => Promise<ReadingProgress>;
  put: (
    documentId: string,
    body: PutProgressBody,
    signal?: AbortSignal,
  ) => Promise<ReadingProgress>;
};

export type ReadingProgressUrls = {
  show: (documentId: string) => string;
  upsert: (documentId: string) => string;
};

export function createReadingProgressHttp(urls: ReadingProgressUrls): ReadingProgressHttp {
  return {
    get(documentId, signal) {
      return progressFetch(urls.show(documentId), {
        method: "GET",
        signal,
      });
    },
    put(documentId, body, signal) {
      return progressFetch(urls.upsert(documentId), {
        method: "PUT",
        body,
        signal,
      });
    },
  };
}

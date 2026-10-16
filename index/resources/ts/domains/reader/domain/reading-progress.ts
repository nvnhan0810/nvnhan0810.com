export type ReadingProgress = {
  documentId: string;
  pageIndex: number;
  revision: number;
  updatedAt: string | null;
};

export const ProgressSyncStatus = {
  Idle: "idle",
  Saving: "saving",
  Saved: "saved",
  Error: "error",
} as const;

export type ProgressSyncStatus =
  (typeof ProgressSyncStatus)[keyof typeof ProgressSyncStatus];

export function parseReadingProgress(data: unknown): ReadingProgress {
  if (typeof data !== "object" || data === null) {
    throw new Error("Invalid reading progress payload");
  }

  const record = data as Record<string, unknown>;
  const documentId =
    typeof record.document_id === "string"
      ? record.document_id
      : typeof record.documentId === "string"
        ? record.documentId
        : null;
  const pageIndex = record.page_index ?? record.pageIndex;
  const revision = record.revision;
  const updatedAt = record.updated_at ?? record.updatedAt ?? null;

  if (
    documentId === null ||
    typeof pageIndex !== "number" ||
    !Number.isInteger(pageIndex) ||
    pageIndex < 0 ||
    typeof revision !== "number" ||
    !Number.isInteger(revision) ||
    revision < 0
  ) {
    throw new Error("Invalid reading progress fields");
  }

  if (updatedAt !== null && typeof updatedAt !== "string") {
    throw new Error("Invalid reading progress updated_at");
  }

  return {
    documentId,
    pageIndex,
    revision,
    updatedAt,
  };
}

/** pdf.js uses 1-based page numbers; API uses 0-based page_index. */
export function pageIndexToPdfPageNumber(pageIndex: number): number {
  return pageIndex + 1;
}

export function pdfPageNumberToPageIndex(pageNumber: number): number {
  return Math.max(0, pageNumber - 1);
}

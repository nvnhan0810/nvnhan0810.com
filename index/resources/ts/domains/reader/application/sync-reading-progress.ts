import {
  ProgressSyncStatus,
  type ProgressSyncStatus as SyncStatus,
  type ReadingProgress,
} from "../domain/reading-progress";
import {
  ProgressConflictError,
  type ReadingProgressHttp,
} from "../infrastructure/reading-progress-http";

const DEBOUNCE_MS = 750;

export type SyncReadingProgress = {
  notifyPageIndex: (pageIndex: number) => void;
  flush: () => Promise<void>;
  destroy: () => void;
  getRevision: () => number;
  getStatus: () => SyncStatus;
};

type Options = {
  documentId: string;
  initial: ReadingProgress | null;
  http: ReadingProgressHttp;
  onStatusChange?: (status: SyncStatus) => void;
};

export function createSyncReadingProgress(options: Options): SyncReadingProgress {
  let revision = options.initial?.revision ?? 0;
  let pendingPageIndex: number | null = null;
  let lastSentPageIndex: number | null = options.initial?.pageIndex ?? null;
  let status: SyncStatus = ProgressSyncStatus.Idle;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let inFlight: AbortController | null = null;
  let destroyed = false;
  let useBaseRevision = revision > 0;

  const setStatus = (next: SyncStatus): void => {
    status = next;
    options.onStatusChange?.(next);
  };

  const clearTimer = (): void => {
    if (timer !== null) {
      clearTimeout(timer);
      timer = null;
    }
  };

  const putNow = async (pageIndex: number): Promise<void> => {
    if (destroyed) {
      return;
    }

    if (pageIndex === lastSentPageIndex && status === ProgressSyncStatus.Saved) {
      return;
    }

    inFlight?.abort();
    const controller = new AbortController();
    inFlight = controller;
    setStatus(ProgressSyncStatus.Saving);

    const body: { page_index: number; base_revision?: number } = {
      page_index: pageIndex,
    };
    if (useBaseRevision && revision > 0) {
      body.base_revision = revision;
    }

    try {
      const saved = await options.http.put(options.documentId, body, controller.signal);
      if (destroyed || controller.signal.aborted) {
        return;
      }
      revision = saved.revision;
      lastSentPageIndex = saved.pageIndex;
      useBaseRevision = true;
      setStatus(ProgressSyncStatus.Saved);
    } catch (error: unknown) {
      if (controller.signal.aborted || destroyed) {
        return;
      }
      if (error instanceof ProgressConflictError) {
        // LWW: drop base_revision and retry once with server tip.
        if (error.serverRevision !== null) {
          revision = error.serverRevision;
        }
        useBaseRevision = false;
        try {
          const saved = await options.http.put(
            options.documentId,
            { page_index: pageIndex },
            controller.signal,
          );
          if (destroyed || controller.signal.aborted) {
            return;
          }
          revision = saved.revision;
          lastSentPageIndex = saved.pageIndex;
          useBaseRevision = true;
          setStatus(ProgressSyncStatus.Saved);
          return;
        } catch {
          if (!controller.signal.aborted && !destroyed) {
            setStatus(ProgressSyncStatus.Error);
          }
          return;
        }
      }
      setStatus(ProgressSyncStatus.Error);
    } finally {
      if (inFlight === controller) {
        inFlight = null;
      }
    }
  };

  const schedule = (): void => {
    clearTimer();
    timer = setTimeout(() => {
      timer = null;
      const pageIndex = pendingPageIndex;
      if (pageIndex === null) {
        return;
      }
      void putNow(pageIndex);
    }, DEBOUNCE_MS);
  };

  return {
    notifyPageIndex(pageIndex: number): void {
      if (destroyed || pageIndex < 0) {
        return;
      }
      pendingPageIndex = pageIndex;
      if (pageIndex === lastSentPageIndex) {
        return;
      }
      schedule();
    },

    async flush(): Promise<void> {
      clearTimer();
      const pageIndex = pendingPageIndex;
      if (pageIndex === null) {
        return;
      }
      await putNow(pageIndex);
    },

    destroy(): void {
      destroyed = true;
      clearTimer();
      inFlight?.abort();
      inFlight = null;
    },

    getRevision(): number {
      return revision;
    },

    getStatus(): SyncStatus {
      return status;
    },
  };
}

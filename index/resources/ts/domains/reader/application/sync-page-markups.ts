import {
  emptyPageMarkup,
  type MarkupItem,
  type PageMarkup,
} from "../domain/page-markup";
import {
  MarkupConflictError,
  type PageMarkupHttp,
  type PageMarkupSnapshot,
} from "../infrastructure/page-markup-http";

const DEBOUNCE_MS = 600;

export type PageMarkupsState = {
  byPage: Map<number, PageMarkup>;
  revisions: Map<number, number>;
};

export type SyncPageMarkups = {
  getMarkup: (pageIndex: number) => PageMarkup;
  setItems: (pageIndex: number, items: MarkupItem[]) => void;
  upsertItem: (pageIndex: number, item: MarkupItem) => void;
  removeItem: (pageIndex: number, itemId: string) => void;
  flush: () => Promise<void>;
  destroy: () => void;
};

type Options = {
  documentId: string;
  http: PageMarkupHttp;
  initial: PageMarkupSnapshot[];
  onChange?: (state: PageMarkupsState) => void;
};

export function createSyncPageMarkups(options: Options): SyncPageMarkups {
  const byPage = new Map<number, PageMarkup>();
  const revisions = new Map<number, number>();
  const dirty = new Set<number>();
  const timers = new Map<number, ReturnType<typeof setTimeout>>();
  const inFlight = new Map<number, AbortController>();
  let destroyed = false;

  for (const snapshot of options.initial) {
    byPage.set(snapshot.pageIndex, snapshot.markup);
    revisions.set(snapshot.pageIndex, snapshot.revision);
  }

  const emit = (): void => {
    options.onChange?.({
      byPage: new Map(byPage),
      revisions: new Map(revisions),
    });
  };

  const clearTimer = (pageIndex: number): void => {
    const timer = timers.get(pageIndex);
    if (timer !== undefined) {
      clearTimeout(timer);
      timers.delete(pageIndex);
    }
  };

  const putPage = async (pageIndex: number): Promise<void> => {
    if (destroyed) {
      return;
    }
    const markup = byPage.get(pageIndex) ?? emptyPageMarkup();
    inFlight.get(pageIndex)?.abort();
    const controller = new AbortController();
    inFlight.set(pageIndex, controller);

    const baseRevision = revisions.get(pageIndex) ?? 0;
    try {
      const saved = await options.http.put(
        options.documentId,
        pageIndex,
        markup,
        baseRevision > 0 ? baseRevision : null,
        controller.signal,
      );
      if (destroyed || controller.signal.aborted) {
        return;
      }
      byPage.set(pageIndex, saved.markup);
      revisions.set(pageIndex, saved.revision);
      dirty.delete(pageIndex);
      emit();
    } catch (error: unknown) {
      if (controller.signal.aborted || destroyed) {
        return;
      }
      if (error instanceof MarkupConflictError) {
        try {
          const saved = await options.http.put(
            options.documentId,
            pageIndex,
            markup,
            null,
            controller.signal,
          );
          if (destroyed || controller.signal.aborted) {
            return;
          }
          byPage.set(pageIndex, saved.markup);
          revisions.set(pageIndex, saved.revision);
          dirty.delete(pageIndex);
          emit();
        } catch {
          // leave dirty for retry on next edit/flush
        }
      }
    } finally {
      if (inFlight.get(pageIndex) === controller) {
        inFlight.delete(pageIndex);
      }
    }
  };

  const schedule = (pageIndex: number): void => {
    dirty.add(pageIndex);
    clearTimer(pageIndex);
    timers.set(
      pageIndex,
      setTimeout(() => {
        timers.delete(pageIndex);
        void putPage(pageIndex);
      }, DEBOUNCE_MS),
    );
  };

  return {
    getMarkup(pageIndex) {
      return byPage.get(pageIndex) ?? emptyPageMarkup();
    },

    setItems(pageIndex, items) {
      if (destroyed || pageIndex < 0) {
        return;
      }
      byPage.set(pageIndex, { version: 1, items });
      emit();
      schedule(pageIndex);
    },

    upsertItem(pageIndex, item) {
      if (destroyed || pageIndex < 0) {
        return;
      }
      const current = byPage.get(pageIndex) ?? emptyPageMarkup();
      const nextItems = [...current.items.filter((entry) => entry.id !== item.id), item];
      byPage.set(pageIndex, { version: 1, items: nextItems });
      emit();
      schedule(pageIndex);
    },

    removeItem(pageIndex, itemId) {
      if (destroyed || pageIndex < 0) {
        return;
      }
      const current = byPage.get(pageIndex) ?? emptyPageMarkup();
      byPage.set(pageIndex, {
        version: 1,
        items: current.items.filter((entry) => entry.id !== itemId),
      });
      emit();
      schedule(pageIndex);
    },

    async flush() {
      for (const pageIndex of [...timers.keys()]) {
        clearTimer(pageIndex);
      }
      const pages = [...dirty];
      await Promise.all(pages.map((pageIndex) => putPage(pageIndex)));
    },

    destroy() {
      destroyed = true;
      for (const pageIndex of [...timers.keys()]) {
        clearTimer(pageIndex);
      }
      for (const controller of inFlight.values()) {
        controller.abort();
      }
      inFlight.clear();
    },
  };
}

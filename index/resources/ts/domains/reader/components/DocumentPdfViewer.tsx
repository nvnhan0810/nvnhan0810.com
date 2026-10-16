import { Button } from "@/ts/components/ui/button";
import { router } from "@inertiajs/react";
import {
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  Maximize2,
  Minimize2,
} from "lucide-react";
import {
  getDocument,
  type PDFDocumentProxy,
  type RenderTask,
} from "pdfjs-dist";
import { useEffect, useRef, useState } from "react";
import { createSyncReadingProgress } from "../application/sync-reading-progress";
import {
  pageIndexToPdfPageNumber,
  pdfPageNumberToPageIndex,
  ProgressSyncStatus,
  type ProgressSyncStatus as SyncStatus,
  type ReadingProgress,
} from "../domain/reading-progress";
import { ensurePdfWorker } from "../infrastructure/pdf-worker";
import type { ReadingProgressHttp } from "../infrastructure/reading-progress-http";

type Props = {
  documentId: string;
  url: string;
  title: string;
  listUrl: string;
  initialProgress: ReadingProgress | null;
  progressHttp: ReadingProgressHttp;
};

const SCALE = 1.25;

const DocumentPdfViewer = ({
  documentId,
  url,
  title,
  listUrl,
  initialProgress,
  progressHttp,
}: Props) => {
  const shellRef = useRef<HTMLDivElement | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const pageRefs = useRef<Map<number, HTMLCanvasElement>>(new Map());
  const pdfRef = useRef<PDFDocumentProxy | null>(null);
  const renderTasksRef = useRef<Map<number, RenderTask>>(new Map());
  const syncRef = useRef<ReturnType<typeof createSyncReadingProgress> | null>(null);
  const restoredRef = useRef(false);
  const initialPageRef = useRef(pageIndexToPdfPageNumber(initialProgress?.pageIndex ?? 0));

  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [numPages, setNumPages] = useState(0);
  const [currentPage, setCurrentPage] = useState(initialPageRef.current);
  const [syncStatus, setSyncStatus] = useState<SyncStatus>(ProgressSyncStatus.Idle);
  const [pdfReady, setPdfReady] = useState(0);
  const [isFullscreen, setIsFullscreen] = useState(false);

  const isSaving = syncStatus === ProgressSyncStatus.Saving;
  const isSaved = syncStatus === ProgressSyncStatus.Saved;
  const isError = syncStatus === ProgressSyncStatus.Error;

  useEffect(() => {
    initialPageRef.current = pageIndexToPdfPageNumber(initialProgress?.pageIndex ?? 0);
  }, [initialProgress?.pageIndex]);

  useEffect(() => {
    const onFullscreenChange = (): void => {
      setIsFullscreen(document.fullscreenElement === shellRef.current);
    };
    document.addEventListener("fullscreenchange", onFullscreenChange);
    return () => document.removeEventListener("fullscreenchange", onFullscreenChange);
  }, []);

  useEffect(() => {
    const sync = createSyncReadingProgress({
      documentId,
      initial: initialProgress,
      http: progressHttp,
      onStatusChange: setSyncStatus,
    });
    syncRef.current = sync;

    const onVisibility = (): void => {
      if (document.visibilityState === "hidden") {
        void sync.flush();
      }
    };
    document.addEventListener("visibilitychange", onVisibility);

    return () => {
      document.removeEventListener("visibilitychange", onVisibility);
      void sync.flush().finally(() => {
        sync.destroy();
      });
      syncRef.current = null;
    };
  }, [documentId, initialProgress, progressHttp]);

  useEffect(() => {
    let cancelled = false;
    let loadingTask: ReturnType<typeof getDocument> | null = null;

    const load = async (): Promise<void> => {
      setLoading(true);
      setFailed(false);
      restoredRef.current = false;
      setPdfReady(0);

      try {
        await ensurePdfWorker();
        if (cancelled) {
          return;
        }

        // Presigned Seaweed URLs are cross-origin; cookies must not be sent
        // (CORS is * + credentials would fail). Same-origin proxy needs cookies.
        let withCredentials = false;
        try {
          withCredentials =
            new URL(url, window.location.href).origin === window.location.origin;
        } catch {
          withCredentials = false;
        }

        loadingTask = getDocument({
          url,
          withCredentials,
        });
        const pdf = await loadingTask.promise;
        if (cancelled) {
          await loadingTask.destroy();
          return;
        }

        pdfRef.current = pdf;
        setNumPages(pdf.numPages);
        setLoading(false);
        setPdfReady((value) => value + 1);
      } catch {
        if (!cancelled) {
          setFailed(true);
          setLoading(false);
        }
      }
    };

    void load();

    return () => {
      cancelled = true;
      for (const task of renderTasksRef.current.values()) {
        task.cancel();
      }
      renderTasksRef.current.clear();
      pdfRef.current = null;
      if (loadingTask !== null) {
        void loadingTask.destroy();
      }
    };
  }, [url]);

  useEffect(() => {
    const pdf = pdfRef.current;
    if (!pdf || loading || failed || numPages <= 0 || pdfReady === 0) {
      return;
    }

    let cancelled = false;

    const renderPage = async (pageNumber: number): Promise<void> => {
      const canvas = pageRefs.current.get(pageNumber);
      if (!canvas || cancelled) {
        return;
      }

      const existing = renderTasksRef.current.get(pageNumber);
      if (existing) {
        existing.cancel();
        renderTasksRef.current.delete(pageNumber);
      }

      const page = await pdf.getPage(pageNumber);
      if (cancelled) {
        return;
      }

      const viewport = page.getViewport({ scale: SCALE });
      const context = canvas.getContext("2d");
      if (!context) {
        return;
      }

      canvas.height = viewport.height;
      canvas.width = viewport.width;

      const task = page.render({
        canvasContext: context,
        viewport,
        canvas,
      });
      renderTasksRef.current.set(pageNumber, task);
      try {
        await task.promise;
      } catch {
        // Cancelled render is expected when the document unloads.
      } finally {
        renderTasksRef.current.delete(pageNumber);
      }
    };

    const run = async (): Promise<void> => {
      await new Promise<void>((resolve) => {
        requestAnimationFrame(() => resolve());
      });
      if (cancelled) {
        return;
      }

      const concurrency = 3;
      let next = 1;
      const workers: Promise<void>[] = [];

      const worker = async (): Promise<void> => {
        while (next <= numPages && !cancelled) {
          const pageNumber = next;
          next += 1;
          await renderPage(pageNumber);
        }
      };

      for (let i = 0; i < concurrency; i += 1) {
        workers.push(worker());
      }
      await Promise.all(workers);

      if (!cancelled && !restoredRef.current) {
        const target = Math.min(Math.max(initialPageRef.current, 1), numPages);
        const canvas = pageRefs.current.get(target);
        if (canvas) {
          restoredRef.current = true;
          canvas.scrollIntoView({ block: "start" });
          setCurrentPage(target);
        }
      }
    };

    void run();

    return () => {
      cancelled = true;
    };
  }, [pdfReady, numPages, loading, failed]);

  useEffect(() => {
    const root = containerRef.current;
    if (!root || numPages <= 0 || loading) {
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        let best: { page: number; ratio: number } | null = null;
        for (const entry of entries) {
          if (!entry.isIntersecting) {
            continue;
          }
          const pageAttr = entry.target.getAttribute("data-page");
          if (pageAttr === null) {
            continue;
          }
          const page = Number.parseInt(pageAttr, 10);
          if (!Number.isFinite(page)) {
            continue;
          }
          if (best === null || entry.intersectionRatio > best.ratio) {
            best = { page, ratio: entry.intersectionRatio };
          }
        }
        if (best === null) {
          return;
        }
        setCurrentPage(best.page);
        syncRef.current?.notifyPageIndex(pdfPageNumberToPageIndex(best.page));
      },
      {
        root,
        threshold: [0.35, 0.55, 0.75],
      },
    );

    const nodes = root.querySelectorAll("[data-page]");
    nodes.forEach((node) => observer.observe(node));

    return () => observer.disconnect();
  }, [numPages, loading, pdfReady]);

  const goToPage = (pageNumber: number): void => {
    if (pageNumber < 1 || (numPages > 0 && pageNumber > numPages)) {
      return;
    }
    const canvas = pageRefs.current.get(pageNumber);
    canvas?.scrollIntoView({ block: "start", behavior: "smooth" });
    setCurrentPage(pageNumber);
    syncRef.current?.notifyPageIndex(pdfPageNumberToPageIndex(pageNumber));
  };

  const toggleFullscreen = async (): Promise<void> => {
    const shell = shellRef.current;
    if (!shell) {
      return;
    }
    try {
      if (document.fullscreenElement === shell) {
        await document.exitFullscreen();
      } else {
        await shell.requestFullscreen();
      }
    } catch {
      // Browser denied fullscreen — ignore.
    }
  };

  const pages = numPages > 0 ? Array.from({ length: numPages }, (_, i) => i + 1) : [];
  const statusText = isSaving
    ? "Đang lưu…"
    : isError
      ? "Lưu thất bại"
      : isSaved
        ? "Đã đồng bộ"
        : "";

  return (
    <div
      ref={shellRef}
      className="flex h-dvh w-full flex-col bg-background text-foreground"
    >
      <header className="flex shrink-0 flex-wrap items-center gap-2 border-b border-border bg-background/95 px-2 py-2 backdrop-blur supports-[backdrop-filter]:bg-background/80">
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => router.get(listUrl)}
          aria-label="Quay lại danh sách"
        >
          <ArrowLeft className="h-4 w-4" />
        </Button>

        <div className="flex min-w-0 flex-1 items-center justify-center gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={currentPage <= 1 || loading || failed}
            onClick={() => goToPage(currentPage - 1)}
            aria-label="Trang trước"
          >
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <span className="min-w-[7rem] truncate text-center text-sm text-foreground">
            {loading ? "…" : `${currentPage} / ${numPages || "—"}`}
          </span>
          <Button
            type="button"
            variant="outline"
            size="sm"
            disabled={numPages === 0 || currentPage >= numPages || loading || failed}
            onClick={() => goToPage(currentPage + 1)}
            aria-label="Trang sau"
          >
            <ChevronRight className="h-4 w-4" />
          </Button>
        </div>

        <p
          className={`hidden text-xs sm:block ${isError ? "text-red-400" : "text-muted-foreground"}`}
          aria-live="polite"
        >
          {statusText}
        </p>

        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => {
            void toggleFullscreen();
          }}
          aria-label={isFullscreen ? "Thoát toàn màn hình" : "Toàn màn hình"}
        >
          {isFullscreen ? (
            <Minimize2 className="h-4 w-4" />
          ) : (
            <Maximize2 className="h-4 w-4" />
          )}
        </Button>
      </header>

      <div
        ref={containerRef}
        className="relative min-h-0 flex-1 overflow-y-auto bg-muted/30"
        aria-label={title}
      >
        {loading && (
          <p className="px-4 py-16 text-center text-sm text-muted-foreground">
            Đang tải PDF… (file lớn có thể mất vài phút)
          </p>
        )}
        {failed && (
          <div className="px-4 py-16 text-center text-sm text-red-400">
            Không hiển thị được PDF trong trang.
            <div className="mt-2">
              <a href={url} target="_blank" rel="noreferrer" className="text-blue-400 underline">
                Mở tab mới
              </a>
            </div>
          </div>
        )}
        {!loading &&
          !failed &&
          pages.map((pageNumber) => (
            <div
              key={pageNumber}
              data-page={pageNumber}
              className="flex justify-center border-b border-border/40 py-3"
            >
              <canvas
                ref={(el) => {
                  if (el) {
                    pageRefs.current.set(pageNumber, el);
                  } else {
                    pageRefs.current.delete(pageNumber);
                  }
                }}
                className="max-w-full shadow-sm"
              />
            </div>
          ))}
      </div>
    </div>
  );
};

export default DocumentPdfViewer;

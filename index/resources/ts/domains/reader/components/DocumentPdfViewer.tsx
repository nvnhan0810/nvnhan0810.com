import { Button } from "@/ts/components/ui/button";
import { router } from "@inertiajs/react";
import {
  ArrowLeft,
  ChevronLeft,
  ChevronRight,
  Highlighter,
  Maximize2,
  Minimize2,
  MousePointer2,
  StickyNote,
} from "lucide-react";
import {
  getDocument,
  TextLayer,
  type PDFDocumentProxy,
  type PDFPageProxy,
  type RenderTask,
} from "pdfjs-dist";
import "pdfjs-dist/web/pdf_viewer.css";
import { useEffect, useRef, useState, type RefObject } from "react";
import { createSyncPageMarkups } from "../application/sync-page-markups";
import { createSyncReadingProgress } from "../application/sync-reading-progress";
import { emptyPageInk, type PageInk } from "../domain/page-ink";
import {
  emptyPageMarkup,
  MarkupTool,
  type MarkupItem,
  type MarkupTool as Tool,
  type PageMarkup,
} from "../domain/page-markup";
import {
  pageIndexToPdfPageNumber,
  pdfPageNumberToPageIndex,
  ProgressSyncStatus,
  type ProgressSyncStatus as SyncStatus,
  type ReadingProgress,
} from "../domain/reading-progress";
import { createPageInkHttp } from "../infrastructure/page-ink-http";
import { createPageMarkupHttp } from "../infrastructure/page-markup-http";
import { ensurePdfWorker } from "../infrastructure/pdf-worker";
import type { ReadingProgressHttp } from "../infrastructure/reading-progress-http";
import PageInkLayer from "./PageInkLayer";
import PageMarkupLayer from "./PageMarkupLayer";

type Props = {
  documentId: string;
  url: string;
  title: string;
  listUrl: string;
  initialProgress: ReadingProgress | null;
  progressHttp: ReadingProgressHttp;
};

const BASE_SCALE = 1.35;
/** How many pages around the viewport to keep rendered. */
const RENDER_RADIUS = 2;
const PLACEHOLDER_HEIGHT_PX = 1100;
const markupHttp = createPageMarkupHttp();
const inkHttp = createPageInkHttp();

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
  const pageShellRefs = useRef<Map<number, HTMLDivElement>>(new Map());
  const pageRefs = useRef<Map<number, HTMLCanvasElement>>(new Map());
  const textLayerRefs = useRef<Map<number, HTMLDivElement>>(new Map());
  const pageRootRefs = useRef<Map<number, HTMLDivElement>>(new Map());
  const pdfRef = useRef<PDFDocumentProxy | null>(null);
  const renderTasksRef = useRef<Map<number, RenderTask>>(new Map());
  const textLayersRef = useRef<Map<number, TextLayer>>(new Map());
  const renderedPagesRef = useRef<Set<number>>(new Set());
  const renderingPagesRef = useRef<Set<number>>(new Set());
  const wantedPagesRef = useRef<Set<number>>(new Set());
  const pageSizeCacheRef = useRef<Map<number, { width: number; height: number }>>(new Map());
  const defaultPageSizeRef = useRef<{ width: number; height: number } | null>(null);
  const syncRef = useRef<ReturnType<typeof createSyncReadingProgress> | null>(null);
  const markupSyncRef = useRef<ReturnType<typeof createSyncPageMarkups> | null>(null);
  const restoredRef = useRef(false);
  const initialPageRef = useRef(pageIndexToPdfPageNumber(initialProgress?.pageIndex ?? 0));
  const requestPagesRef = useRef<(pages: Set<number>) => void>(() => {
    // set by render effect
  });

  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [numPages, setNumPages] = useState(0);
  const [currentPage, setCurrentPage] = useState(initialPageRef.current);
  const [syncStatus, setSyncStatus] = useState<SyncStatus>(ProgressSyncStatus.Idle);
  const [pdfReady, setPdfReady] = useState(0);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [tool, setTool] = useState<Tool>(MarkupTool.Navigate);
  const [markupsByPage, setMarkupsByPage] = useState<Map<number, PageMarkup>>(new Map());
  const [markupsReady, setMarkupsReady] = useState(false);
  const [inksByPage, setInksByPage] = useState<Map<number, PageInk>>(new Map());
  const [inksReady, setInksReady] = useState(false);
  const [renderedPages, setRenderedPages] = useState<Set<number>>(() => new Set());
  const [pageHeights, setPageHeights] = useState<Map<number, number>>(() => new Map());

  const isSaving = syncStatus === ProgressSyncStatus.Saving;
  const isSaved = syncStatus === ProgressSyncStatus.Saved;
  const isError = syncStatus === ProgressSyncStatus.Error;
  const isTextTool = tool === MarkupTool.HighlightText;
  const isNoteTool = tool === MarkupTool.NoteRegion;

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
        void markupSyncRef.current?.flush();
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

    const loadMarkups = async (): Promise<void> => {
      setMarkupsReady(false);
      try {
        const initial = await markupHttp.list(documentId);
        if (cancelled) {
          return;
        }
        const sync = createSyncPageMarkups({
          documentId,
          http: markupHttp,
          initial,
          onChange: (state) => setMarkupsByPage(state.byPage),
        });
        markupSyncRef.current?.destroy();
        markupSyncRef.current = sync;
        const map = new Map<number, PageMarkup>();
        for (const snapshot of initial) {
          map.set(snapshot.pageIndex, snapshot.markup);
        }
        setMarkupsByPage(map);
        setMarkupsReady(true);
      } catch {
        if (!cancelled) {
          const sync = createSyncPageMarkups({
            documentId,
            http: markupHttp,
            initial: [],
            onChange: (state) => setMarkupsByPage(state.byPage),
          });
          markupSyncRef.current?.destroy();
          markupSyncRef.current = sync;
          setMarkupsByPage(new Map());
          setMarkupsReady(true);
        }
      }
    };

    void loadMarkups();

    return () => {
      cancelled = true;
      const sync = markupSyncRef.current;
      markupSyncRef.current = null;
      if (sync) {
        void sync.flush().finally(() => sync.destroy());
      }
    };
  }, [documentId]);

  useEffect(() => {
    let cancelled = false;

    const loadInks = async (): Promise<void> => {
      setInksReady(false);
      try {
        const snapshots = await inkHttp.list(documentId);
        if (cancelled) {
          return;
        }
        const map = new Map<number, PageInk>();
        for (const snapshot of snapshots) {
          map.set(snapshot.pageIndex, snapshot.ink);
        }
        setInksByPage(map);
        setInksReady(true);
      } catch {
        if (!cancelled) {
          setInksByPage(new Map());
          setInksReady(true);
        }
      }
    };

    void loadInks();

    return () => {
      cancelled = true;
    };
  }, [documentId]);

  useEffect(() => {
    let cancelled = false;
    let loadingTask: ReturnType<typeof getDocument> | null = null;

    const load = async (): Promise<void> => {
      setLoading(true);
      setFailed(false);
      restoredRef.current = false;
      renderedPagesRef.current.clear();
      renderingPagesRef.current.clear();
      wantedPagesRef.current.clear();
      pageSizeCacheRef.current.clear();
      defaultPageSizeRef.current = null;
      setRenderedPages(new Set());
      setPageHeights(new Map());
      setPdfReady(0);

      try {
        await ensurePdfWorker();
        if (cancelled) {
          return;
        }

        let withCredentials = false;
        try {
          withCredentials =
            new URL(url, window.location.href).origin === window.location.origin;
        } catch {
          withCredentials = false;
        }

        // Range requests: jump to mid-doc without waiting for full download.
        loadingTask = getDocument({
          url,
          withCredentials,
          disableAutoFetch: true,
          disableStream: false,
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
      for (const layer of textLayersRef.current.values()) {
        layer.cancel();
      }
      textLayersRef.current.clear();
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

    const fitScaleFor = (pageWidthAtBase: number): number => {
      const containerWidth = containerRef.current?.clientWidth ?? pageWidthAtBase;
      const usable = Math.max(280, containerWidth - 32);
      return Math.min(BASE_SCALE, (usable / pageWidthAtBase) * BASE_SCALE);
    };

    const viewportForPage = async (
      page: PDFPageProxy,
    ): Promise<{ width: number; height: number; viewport: ReturnType<PDFPageProxy["getViewport"]> }> => {
      const baseViewport = page.getViewport({ scale: BASE_SCALE });
      const scale = fitScaleFor(baseViewport.width);
      const viewport = page.getViewport({ scale });
      return { width: viewport.width, height: viewport.height, viewport };
    };

    const markRendered = (pageNumber: number): void => {
      renderedPagesRef.current.add(pageNumber);
      setRenderedPages(new Set(renderedPagesRef.current));
    };

    const releasePage = (pageNumber: number): void => {
      renderTasksRef.current.get(pageNumber)?.cancel();
      renderTasksRef.current.delete(pageNumber);
      textLayersRef.current.get(pageNumber)?.cancel();
      textLayersRef.current.delete(pageNumber);
      renderingPagesRef.current.delete(pageNumber);
      renderedPagesRef.current.delete(pageNumber);

      const canvas = pageRefs.current.get(pageNumber);
      if (canvas) {
        const context = canvas.getContext("2d");
        context?.clearRect(0, 0, canvas.width, canvas.height);
        canvas.width = 0;
        canvas.height = 0;
      }
      const textLayerDiv = textLayerRefs.current.get(pageNumber);
      textLayerDiv?.replaceChildren();

      setRenderedPages(new Set(renderedPagesRef.current));
    };

    const renderPage = async (pageNumber: number): Promise<void> => {
      if (
        cancelled ||
        renderedPagesRef.current.has(pageNumber) ||
        renderingPagesRef.current.has(pageNumber)
      ) {
        return;
      }

      const canvas = pageRefs.current.get(pageNumber);
      const textLayerDiv = textLayerRefs.current.get(pageNumber);
      if (!canvas || !textLayerDiv) {
        return;
      }

      renderingPagesRef.current.add(pageNumber);

      try {
        const page = await pdf.getPage(pageNumber);
        if (cancelled || !wantedPagesRef.current.has(pageNumber)) {
          return;
        }

        const { width, height, viewport } = await viewportForPage(page);
        pageSizeCacheRef.current.set(pageNumber, { width, height });
        if (defaultPageSizeRef.current === null) {
          defaultPageSizeRef.current = { width, height };
        }
        setPageHeights((prev) => {
          const next = new Map(prev);
          next.set(pageNumber, height);
          return next;
        });

        const context = canvas.getContext("2d");
        if (!context) {
          return;
        }

        canvas.height = height;
        canvas.width = width;
        canvas.style.width = `${width}px`;
        canvas.style.height = `${height}px`;

        const existing = renderTasksRef.current.get(pageNumber);
        if (existing) {
          existing.cancel();
          renderTasksRef.current.delete(pageNumber);
        }
        textLayersRef.current.get(pageNumber)?.cancel();

        const task = page.render({
          canvasContext: context,
          viewport,
          canvas,
        });
        renderTasksRef.current.set(pageNumber, task);
        try {
          await task.promise;
        } catch {
          return;
        } finally {
          renderTasksRef.current.delete(pageNumber);
        }

        if (cancelled || !wantedPagesRef.current.has(pageNumber)) {
          return;
        }

        textLayerDiv.replaceChildren();
        textLayerDiv.style.width = `${width}px`;
        textLayerDiv.style.height = `${height}px`;

        const textContent = await page.getTextContent();
        if (cancelled || !wantedPagesRef.current.has(pageNumber)) {
          return;
        }

        const textLayer = new TextLayer({
          textContentSource: textContent,
          container: textLayerDiv,
          viewport,
        });
        textLayersRef.current.set(pageNumber, textLayer);
        try {
          await textLayer.render();
        } catch {
          // cancelled ok
        }

        if (!cancelled && wantedPagesRef.current.has(pageNumber)) {
          markRendered(pageNumber);
        }
      } finally {
        renderingPagesRef.current.delete(pageNumber);
      }
    };

    const syncWantedPages = (wanted: Set<number>): void => {
      wantedPagesRef.current = wanted;

      for (const pageNumber of [...renderedPagesRef.current]) {
        if (!wanted.has(pageNumber)) {
          releasePage(pageNumber);
        }
      }

      for (const pageNumber of wanted) {
        void renderPage(pageNumber);
      }
    };

    requestPagesRef.current = syncWantedPages;

    const pagesAround = (center: number): Set<number> => {
      const wanted = new Set<number>();
      for (let page = center - RENDER_RADIUS; page <= center + RENDER_RADIUS; page += 1) {
        if (page >= 1 && page <= numPages) {
          wanted.add(page);
        }
      }
      return wanted;
    };

    const jumpToSavedPage = async (): Promise<void> => {
      const target = Math.min(Math.max(initialPageRef.current, 1), numPages);
      setCurrentPage(target);

      // Measure page 1 once so placeholders match real aspect before we scroll.
      try {
        const probePage = await pdf.getPage(Math.min(target, numPages));
        if (!cancelled) {
          const { width, height } = await viewportForPage(probePage);
          defaultPageSizeRef.current = { width, height };
          setPageHeights((prev) => {
            const next = new Map(prev);
            next.set(target, height);
            if (!next.has(1)) {
              next.set(1, height);
            }
            return next;
          });
        }
      } catch {
        // keep default placeholder
      }

      // Wait until page shells are in the DOM.
      await new Promise<void>((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
      });
      if (cancelled) {
        return;
      }

      // Jump to placeholder immediately — do not wait for full PDF paint.
      const shell = pageShellRefs.current.get(target);
      if (shell && !restoredRef.current) {
        restoredRef.current = true;
        shell.scrollIntoView({ block: "start" });
      }

      syncWantedPages(pagesAround(target));
    };

    void jumpToSavedPage();

    const root = containerRef.current;
    if (!root) {
      return () => {
        cancelled = true;
      };
    }

    const visibilityObserver = new IntersectionObserver(
      (entries) => {
        let best: { page: number; ratio: number } | null = null;
        const visible = new Set<number>();

        for (const entry of entries) {
          const pageAttr = entry.target.getAttribute("data-page");
          if (pageAttr === null) {
            continue;
          }
          const page = Number.parseInt(pageAttr, 10);
          if (!Number.isFinite(page)) {
            continue;
          }
          if (entry.isIntersecting) {
            visible.add(page);
            if (best === null || entry.intersectionRatio > best.ratio) {
              best = { page, ratio: entry.intersectionRatio };
            }
          }
        }

        if (best !== null) {
          setCurrentPage(best.page);
          syncRef.current?.notifyPageIndex(pdfPageNumberToPageIndex(best.page));
          syncWantedPages(pagesAround(best.page));
        } else if (visible.size > 0) {
          const firstVisible = Math.min(...visible);
          syncWantedPages(pagesAround(firstVisible));
        }
      },
      {
        root,
        // Prefetch a bit outside the viewport so scroll feels instant.
        rootMargin: "800px 0px",
        threshold: [0, 0.15, 0.35, 0.55, 0.75],
      },
    );

    const observeAll = (): void => {
      for (const shell of pageShellRefs.current.values()) {
        visibilityObserver.observe(shell);
      }
    };

    // Shells mount with numPages; observe on next frame.
    const observeRaf = requestAnimationFrame(() => {
      observeAll();
    });

    return () => {
      cancelled = true;
      cancelAnimationFrame(observeRaf);
      visibilityObserver.disconnect();
    };
  }, [pdfReady, numPages, loading, failed]);

  const goToPage = (pageNumber: number): void => {
    if (pageNumber < 1 || (numPages > 0 && pageNumber > numPages)) {
      return;
    }
    setCurrentPage(pageNumber);
    syncRef.current?.notifyPageIndex(pdfPageNumberToPageIndex(pageNumber));

    const wanted = new Set<number>();
    for (let page = pageNumber - RENDER_RADIUS; page <= pageNumber + RENDER_RADIUS; page += 1) {
      if (page >= 1 && page <= numPages) {
        wanted.add(page);
      }
    }
    requestPagesRef.current(wanted);

    const shell = pageShellRefs.current.get(pageNumber);
    shell?.scrollIntoView({ block: "start", behavior: "smooth" });
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
      // ignore
    }
  };

  const upsertMarkupItem = (pageIndex: number, item: MarkupItem): void => {
    markupSyncRef.current?.upsertItem(pageIndex, item);
  };

  const removeMarkupItem = (pageIndex: number, itemId: string): void => {
    markupSyncRef.current?.removeItem(pageIndex, itemId);
  };

  const pageRootRefFor = (pageNumber: number): RefObject<HTMLDivElement | null> => {
    return {
      get current() {
        return pageRootRefs.current.get(pageNumber) ?? null;
      },
    };
  };

  const pages = numPages > 0 ? Array.from({ length: numPages }, (_, i) => i + 1) : [];
  const defaultHeight = defaultPageSizeRef.current?.height ?? PLACEHOLDER_HEIGHT_PX;
  const statusText = isSaving
    ? "Đang lưu…"
    : isError
      ? "Lưu thất bại"
      : isSaved
        ? "Đã đồng bộ"
        : "";
  const toolHint = isTextTool
    ? "Bôi đen chữ để highlight"
    : isNoteTool
      ? "Kéo vùng rồi gõ trực tiếp trong đó"
      : statusText;

  return (
    <div ref={shellRef} className="flex h-dvh w-full flex-col bg-background text-foreground">
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

        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant={tool === MarkupTool.Navigate ? "secondary" : "ghost"}
            size="sm"
            onClick={() => setTool(MarkupTool.Navigate)}
            aria-label="Xem / cuộn"
            aria-pressed={tool === MarkupTool.Navigate}
            title="Xem"
          >
            <MousePointer2 className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant={isTextTool ? "secondary" : "ghost"}
            size="sm"
            onClick={() => setTool(MarkupTool.HighlightText)}
            aria-label="Highlight từ"
            aria-pressed={isTextTool}
            title="Highlight từ (bôi đen chữ)"
            disabled={!markupsReady || loading || failed}
          >
            <Highlighter className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant={isNoteTool ? "secondary" : "ghost"}
            size="sm"
            onClick={() => setTool(MarkupTool.NoteRegion)}
            aria-label="Ghi chú vùng"
            aria-pressed={isNoteTool}
            title="Kéo vùng và gõ nội dung"
            disabled={!markupsReady || loading || failed}
          >
            <StickyNote className="h-4 w-4" />
          </Button>
        </div>

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
          className={`hidden max-w-[14rem] truncate text-xs sm:block ${isError ? "text-red-400" : "text-muted-foreground"}`}
          aria-live="polite"
        >
          {toolHint}
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
          {isFullscreen ? <Minimize2 className="h-4 w-4" /> : <Maximize2 className="h-4 w-4" />}
        </Button>
      </header>

      <div
        ref={containerRef}
        className="relative min-h-0 flex-1 overflow-auto bg-muted/30"
        aria-label={title}
      >
        {loading && (
          <p className="px-4 py-16 text-center text-sm text-muted-foreground">
            Đang tải PDF…
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
          pages.map((pageNumber) => {
            const pageIndex = pdfPageNumberToPageIndex(pageNumber);
            const markup = markupsByPage.get(pageIndex) ?? emptyPageMarkup();
            const ink = inksByPage.get(pageIndex) ?? emptyPageInk();
            const isRendered = renderedPages.has(pageNumber);
            const height = pageHeights.get(pageNumber) ?? defaultHeight;
            const cachedSize = pageSizeCacheRef.current.get(pageNumber) ?? defaultPageSizeRef.current;
            const inkWidth = cachedSize?.width ?? 0;
            const inkHeight = cachedSize?.height ?? height;

            return (
              <div
                key={pageNumber}
                ref={(el) => {
                  if (el) {
                    pageShellRefs.current.set(pageNumber, el);
                  } else {
                    pageShellRefs.current.delete(pageNumber);
                  }
                }}
                data-page={pageNumber}
                className="flex justify-center border-b border-border/40 py-3"
              >
                <div
                  ref={(el) => {
                    if (el) {
                      pageRootRefs.current.set(pageNumber, el);
                    } else {
                      pageRootRefs.current.delete(pageNumber);
                    }
                  }}
                  className="relative shadow-sm"
                  style={{
                    minHeight: `${height}px`,
                    width: cachedSize !== null ? `${cachedSize.width}px` : undefined,
                    maxWidth: "100%",
                  }}
                >
                  {!isRendered && (
                    <div
                      className="flex items-center justify-center text-xs text-muted-foreground"
                      style={{ height: `${height}px` }}
                    >
                      Trang {pageNumber}
                    </div>
                  )}
                  <canvas
                    ref={(el) => {
                      if (el) {
                        pageRefs.current.set(pageNumber, el);
                      } else {
                        pageRefs.current.delete(pageNumber);
                      }
                    }}
                    className={`block ${isRendered ? "" : "hidden"}`}
                  />
                  <div
                    ref={(el) => {
                      if (el) {
                        textLayerRefs.current.set(pageNumber, el);
                      } else {
                        textLayerRefs.current.delete(pageNumber);
                      }
                    }}
                    className={`textLayer absolute left-0 top-0 z-10 ${
                      isRendered ? "" : "hidden"
                    } ${isTextTool ? "select-text" : "pointer-events-none select-none"}`}
                  />
                  {inksReady && isRendered && (
                    <PageInkLayer ink={ink} width={inkWidth} height={inkHeight} />
                  )}
                  {markupsReady && isRendered && (
                    <PageMarkupLayer
                      pageIndex={pageIndex}
                      pageRootRef={pageRootRefFor(pageNumber)}
                      items={markup.items}
                      tool={tool}
                      canInteract={!loading && !failed}
                      onUpsert={upsertMarkupItem}
                      onRemove={removeMarkupItem}
                    />
                  )}
                </div>
              </div>
            );
          })}
      </div>
    </div>
  );
};

export default DocumentPdfViewer;

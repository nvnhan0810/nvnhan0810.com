import {
  useEffect,
  useRef,
  useState,
  type CSSProperties,
  type PointerEvent,
  type RefObject,
} from "react";
import { X } from "lucide-react";
import {
  boundingUnion,
  createHighlightItem,
  MarkupTool,
  selectionToNormalizedRects,
  type MarkupItem,
  type MarkupRect,
  type MarkupTool as Tool,
} from "../domain/page-markup";

type Props = {
  pageIndex: number;
  pageRootRef: RefObject<HTMLDivElement | null>;
  items: MarkupItem[];
  tool: Tool;
  canInteract: boolean;
  onUpsert: (pageIndex: number, item: MarkupItem) => void;
  onRemove: (pageIndex: number, itemId: string) => void;
};

type DragState = {
  startX: number;
  startY: number;
  currentX: number;
  currentY: number;
};

const noteTextClass =
  "whitespace-pre-wrap break-words text-[12px] leading-snug text-neutral-900 [text-shadow:0_0_2px_#fff,0_0_3px_#fff]";

const PageMarkupLayer = ({
  pageIndex,
  pageRootRef,
  items,
  tool,
  canInteract,
  onUpsert,
  onRemove,
}: Props) => {
  const layerRef = useRef<HTMLDivElement | null>(null);
  const editorRef = useRef<HTMLDivElement | null>(null);
  const noteInputRef = useRef<HTMLTextAreaElement | null>(null);
  const [drag, setDrag] = useState<DragState | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [noteDraft, setNoteDraft] = useState("");
  const noteDraftRef = useRef(noteDraft);
  const itemsRef = useRef(items);
  const editingIdRef = useRef(editingId);

  noteDraftRef.current = noteDraft;
  itemsRef.current = items;
  editingIdRef.current = editingId;

  const editingItem = items.find((item) => item.id === editingId) ?? null;
  const isNoteTool = tool === MarkupTool.NoteRegion;
  const isTextTool = tool === MarkupTool.HighlightText;

  useEffect(() => {
    if (editingItem === null) {
      setNoteDraft("");
      return;
    }
    setNoteDraft(editingItem.note ?? "");
  }, [editingItem]);

  useEffect(() => {
    if (editingId === null) {
      return;
    }
    noteInputRef.current?.focus();
  }, [editingId]);

  useEffect(() => {
    if (!canInteract || !isTextTool) {
      return;
    }

    const onMouseUp = (): void => {
      const pageRoot = pageRootRef.current;
      if (pageRoot === null) {
        return;
      }
      const rects = selectionToNormalizedRects(pageRoot);
      if (rects.length === 0) {
        return;
      }
      const selectedText = window.getSelection()?.toString().trim() ?? "";
      const item = createHighlightItem(rects, {
        note: selectedText === "" ? null : selectedText,
      });
      onUpsert(pageIndex, item);
      window.getSelection()?.removeAllRanges();
      setEditingId(item.id);
    };

    document.addEventListener("mouseup", onMouseUp);
    return () => document.removeEventListener("mouseup", onMouseUp);
  }, [canInteract, isTextTool, onUpsert, pageIndex, pageRootRef]);

  const commitNote = (): void => {
    const id = editingIdRef.current;
    if (id === null) {
      return;
    }
    const item = itemsRef.current.find((entry) => entry.id === id) ?? null;
    if (item === null) {
      setEditingId(null);
      return;
    }
    const nextNote = noteDraftRef.current.trim() === "" ? null : noteDraftRef.current.trim();
    if (nextNote !== item.note) {
      onUpsert(pageIndex, {
        ...item,
        note: nextNote,
        updatedAt: new Date().toISOString(),
      });
    }
    setEditingId(null);
  };

  // Click outside editor → done.
  useEffect(() => {
    if (editingId === null) {
      return;
    }

    const onPointerDown = (event: globalThis.PointerEvent): void => {
      const editor = editorRef.current;
      const target = event.target;
      if (!(target instanceof Node)) {
        return;
      }
      if (editor !== null && editor.contains(target)) {
        return;
      }
      commitNote();
    };

    document.addEventListener("pointerdown", onPointerDown, true);
    return () => document.removeEventListener("pointerdown", onPointerDown, true);
  }, [editingId, onUpsert, pageIndex]);

  const pointerToLocal = (clientX: number, clientY: number): { x: number; y: number } | null => {
    const root = layerRef.current;
    if (!root) {
      return null;
    }
    const bounds = root.getBoundingClientRect();
    if (bounds.width <= 0 || bounds.height <= 0) {
      return null;
    }
    return {
      x: (clientX - bounds.left) / bounds.width,
      y: (clientY - bounds.top) / bounds.height,
    };
  };

  const onPointerDown = (event: PointerEvent<HTMLDivElement>): void => {
    if (!canInteract || !isNoteTool || event.button !== 0) {
      return;
    }
    const local = pointerToLocal(event.clientX, event.clientY);
    if (local === null) {
      return;
    }
    event.currentTarget.setPointerCapture(event.pointerId);
    if (editingId !== null) {
      commitNote();
    }
    setDrag({
      startX: local.x,
      startY: local.y,
      currentX: local.x,
      currentY: local.y,
    });
  };

  const onPointerMove = (event: PointerEvent<HTMLDivElement>): void => {
    if (drag === null) {
      return;
    }
    const local = pointerToLocal(event.clientX, event.clientY);
    if (local === null) {
      return;
    }
    setDrag({ ...drag, currentX: local.x, currentY: local.y });
  };

  const onPointerUp = (event: PointerEvent<HTMLDivElement>): void => {
    if (drag === null) {
      return;
    }
    event.currentTarget.releasePointerCapture(event.pointerId);
    const x = Math.min(drag.startX, drag.currentX);
    const y = Math.min(drag.startY, drag.currentY);
    const w = Math.abs(drag.currentX - drag.startX);
    const h = Math.abs(drag.currentY - drag.startY);
    setDrag(null);

    if (w < 0.02 || h < 0.015) {
      return;
    }

    const rect: MarkupRect = { x, y, w, h };
    const item = createHighlightItem([rect], { note: "" });
    onUpsert(pageIndex, item);
    setEditingId(item.id);
    setNoteDraft("");
  };

  const draftStyle = (): CSSProperties | null => {
    if (drag === null) {
      return null;
    }
    const x = Math.min(drag.startX, drag.currentX);
    const y = Math.min(drag.startY, drag.currentY);
    const w = Math.abs(drag.currentX - drag.startX);
    const h = Math.abs(drag.currentY - drag.startY);
    return {
      left: `${x * 100}%`,
      top: `${y * 100}%`,
      width: `${w * 100}%`,
      height: `${h * 100}%`,
    };
  };

  const editBounds = editingItem !== null ? boundingUnion(editingItem.rects) : null;
  const draft = draftStyle();

  const layerPointerClass = isNoteTool
    ? "pointer-events-auto cursor-crosshair"
    : "pointer-events-none";

  return (
    <div
      ref={layerRef}
      className={`absolute inset-0 z-20 ${layerPointerClass}`}
      onPointerDown={onPointerDown}
      onPointerMove={onPointerMove}
      onPointerUp={onPointerUp}
      onPointerCancel={() => setDrag(null)}
    >
      {items.map((item) => {
        const union = boundingUnion(item.rects);
        return (
          <div key={item.id} className="contents">
            {item.rects.map((rect, rectIndex) => (
              <div
                key={`${item.id}-r-${rectIndex}`}
                className="pointer-events-none absolute mix-blend-multiply"
                style={{
                  left: `${rect.x * 100}%`,
                  top: `${rect.y * 100}%`,
                  width: `${rect.w * 100}%`,
                  height: `${rect.h * 100}%`,
                  backgroundColor: item.color,
                  opacity: 0.45,
                }}
              />
            ))}
            {union !== null && item.id !== editingId && (
              <button
                type="button"
                className={`pointer-events-auto absolute overflow-hidden border-0 bg-transparent p-0.5 text-left ${noteTextClass}`}
                style={{
                  left: `${union.x * 100}%`,
                  top: `${union.y * 100}%`,
                  width: `${union.w * 100}%`,
                  height: `${Math.max(union.h, 0.03) * 100}%`,
                }}
                onClick={(event) => {
                  event.stopPropagation();
                  setEditingId(item.id);
                }}
                onPointerDown={(event) => event.stopPropagation()}
              >
                {item.note !== null && item.note !== "" ? (
                  <span className="line-clamp-8">{item.note}</span>
                ) : (
                  <span className="text-neutral-500 [text-shadow:0_0_2px_#fff]">Nhấn để ghi…</span>
                )}
              </button>
            )}
          </div>
        );
      })}

      {draft !== null && (
        <div
          className="pointer-events-none absolute border border-dashed border-amber-700/70 bg-transparent"
          style={draft}
        />
      )}

      {editingItem !== null && editBounds !== null && (
        <div
          ref={editorRef}
          className="pointer-events-auto absolute z-30 bg-transparent"
          style={{
            left: `${editBounds.x * 100}%`,
            top: `${editBounds.y * 100}%`,
            width: `${Math.max(editBounds.w, 0.18) * 100}%`,
            height: `${Math.max(editBounds.h, 0.08) * 100}%`,
            minWidth: "6rem",
            minHeight: "2.5rem",
          }}
          onPointerDown={(event) => event.stopPropagation()}
        >
          <button
            type="button"
            className="absolute -right-1 -top-1 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-black/70 text-white hover:bg-black"
            aria-label="Xóa ghi chú"
            onPointerDown={(event) => event.stopPropagation()}
            onClick={(event) => {
              event.stopPropagation();
              onRemove(pageIndex, editingItem.id);
              setEditingId(null);
            }}
          >
            <X className="h-3 w-3" />
          </button>
          <textarea
            ref={noteInputRef}
            className={`h-full w-full resize-none border-0 bg-transparent p-1 outline-none placeholder:text-neutral-500 ${noteTextClass}`}
            value={noteDraft}
            onChange={(event) => setNoteDraft(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Escape") {
                event.preventDefault();
                commitNote();
              }
            }}
            placeholder="Gõ tại đây…"
          />
        </div>
      )}
    </div>
  );
};

export default PageMarkupLayer;

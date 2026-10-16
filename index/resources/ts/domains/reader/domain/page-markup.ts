export const MARKUP_FORMAT = "reader.markup.v1" as const;
export const MARKUP_VERSION = 1 as const;

export const MarkupItemType = {
  Highlight: "highlight",
} as const;

export type MarkupItemType = (typeof MarkupItemType)[keyof typeof MarkupItemType];

export const MarkupTool = {
  Navigate: "navigate",
  /** Select words on the PDF text layer → highlight. */
  HighlightText: "highlight_text",
  /** Drag a region on the page and type the note inside it. */
  NoteRegion: "note_region",
} as const;

export type MarkupTool = (typeof MarkupTool)[keyof typeof MarkupTool];

/** Normalized page rect — origin top-left, units 0–1 of page width/height. */
export type MarkupRect = {
  x: number;
  y: number;
  w: number;
  h: number;
};

export type MarkupItem = {
  id: string;
  type: MarkupItemType;
  rects: MarkupRect[];
  color: string;
  note: string | null;
  createdAt: string;
  updatedAt: string;
};

export type PageMarkup = {
  version: typeof MARKUP_VERSION;
  items: MarkupItem[];
};

export const DEFAULT_HIGHLIGHT_COLOR = "#F7E08A";

export function emptyPageMarkup(): PageMarkup {
  return { version: MARKUP_VERSION, items: [] };
}

function clamp01(value: number): number {
  if (!Number.isFinite(value)) {
    return 0;
  }
  if (value < 0) {
    return 0;
  }
  if (value > 1) {
    return 1;
  }
  return value;
}

export function normalizeRect(rect: MarkupRect): MarkupRect {
  const x = clamp01(rect.x);
  const y = clamp01(rect.y);
  const w = clamp01(rect.w);
  const h = clamp01(rect.h);
  return {
    x,
    y,
    w: Math.min(w, 1 - x),
    h: Math.min(h, 1 - y),
  };
}

export function parseMarkupRect(data: unknown): MarkupRect | null {
  if (typeof data !== "object" || data === null) {
    return null;
  }
  const record = data as Record<string, unknown>;
  const x = record.x;
  const y = record.y;
  const w = record.w;
  const h = record.h;
  if (
    typeof x !== "number" ||
    typeof y !== "number" ||
    typeof w !== "number" ||
    typeof h !== "number"
  ) {
    return null;
  }
  return normalizeRect({ x, y, w, h });
}

export function parseMarkupItem(data: unknown): MarkupItem | null {
  if (typeof data !== "object" || data === null) {
    return null;
  }
  const record = data as Record<string, unknown>;
  const id = typeof record.id === "string" ? record.id : null;
  const type = record.type;
  const color = typeof record.color === "string" ? record.color : DEFAULT_HIGHLIGHT_COLOR;
  const note =
    record.note === null || record.note === undefined
      ? null
      : typeof record.note === "string"
        ? record.note
        : null;
  const createdAt =
    typeof record.created_at === "string"
      ? record.created_at
      : typeof record.createdAt === "string"
        ? record.createdAt
        : null;
  const updatedAt =
    typeof record.updated_at === "string"
      ? record.updated_at
      : typeof record.updatedAt === "string"
        ? record.updatedAt
        : null;
  const rectsRaw = record.rects;
  if (
    id === null ||
    type !== MarkupItemType.Highlight ||
    !Array.isArray(rectsRaw) ||
    createdAt === null ||
    updatedAt === null
  ) {
    return null;
  }

  const rects: MarkupRect[] = [];
  for (const entry of rectsRaw) {
    const rect = parseMarkupRect(entry);
    if (rect !== null && rect.w > 0 && rect.h > 0) {
      rects.push(rect);
    }
  }
  if (rects.length === 0) {
    return null;
  }

  return {
    id,
    type: MarkupItemType.Highlight,
    rects,
    color,
    note,
    createdAt,
    updatedAt,
  };
}

export function parsePageMarkup(data: unknown): PageMarkup {
  if (typeof data !== "object" || data === null) {
    return emptyPageMarkup();
  }
  const record = data as Record<string, unknown>;
  if (record.version !== MARKUP_VERSION || !Array.isArray(record.items)) {
    return emptyPageMarkup();
  }
  const items: MarkupItem[] = [];
  for (const entry of record.items) {
    const item = parseMarkupItem(entry);
    if (item !== null) {
      items.push(item);
    }
  }
  return { version: MARKUP_VERSION, items };
}

export function pageMarkupToApiPayload(markup: PageMarkup): {
  version: number;
  items: Array<{
    id: string;
    type: string;
    rects: MarkupRect[];
    color: string;
    note: string | null;
    created_at: string;
    updated_at: string;
  }>;
} {
  return {
    version: markup.version,
    items: markup.items.map((item) => ({
      id: item.id,
      type: item.type,
      rects: item.rects,
      color: item.color,
      note: item.note,
      created_at: item.createdAt,
      updated_at: item.updatedAt,
    })),
  };
}

export function createHighlightItem(
  rects: MarkupRect[],
  options?: { color?: string; note?: string | null },
): MarkupItem {
  const now = new Date().toISOString();
  return {
    id: crypto.randomUUID(),
    type: MarkupItemType.Highlight,
    rects: rects.map(normalizeRect),
    color: options?.color ?? DEFAULT_HIGHLIGHT_COLOR,
    note: options?.note ?? null,
    createdAt: now,
    updatedAt: now,
  };
}

export function clientRectToNormalized(
  pageEl: HTMLElement,
  client: { left: number; top: number; width: number; height: number },
): MarkupRect {
  const bounds = pageEl.getBoundingClientRect();
  if (bounds.width <= 0 || bounds.height <= 0) {
    return { x: 0, y: 0, w: 0, h: 0 };
  }
  return normalizeRect({
    x: (client.left - bounds.left) / bounds.width,
    y: (client.top - bounds.top) / bounds.height,
    w: client.width / bounds.width,
    h: client.height / bounds.height,
  });
}

/** Build normalized rects from the current DOM selection within a page element. */
export function selectionToNormalizedRects(pageEl: HTMLElement): MarkupRect[] {
  const selection = window.getSelection();
  if (selection === null || selection.isCollapsed || selection.rangeCount === 0) {
    return [];
  }

  const range = selection.getRangeAt(0);
  if (!pageEl.contains(range.commonAncestorContainer)) {
    return [];
  }

  const rects: MarkupRect[] = [];
  for (const domRect of Array.from(range.getClientRects())) {
    if (domRect.width < 1 || domRect.height < 1) {
      continue;
    }
    const normalized = clientRectToNormalized(pageEl, domRect);
    if (normalized.w > 0 && normalized.h > 0) {
      rects.push(normalized);
    }
  }
  return rects;
}

export function boundingUnion(rects: MarkupRect[]): MarkupRect | null {
  if (rects.length === 0) {
    return null;
  }
  let minX = 1;
  let minY = 1;
  let maxX = 0;
  let maxY = 0;
  for (const rect of rects) {
    minX = Math.min(minX, rect.x);
    minY = Math.min(minY, rect.y);
    maxX = Math.max(maxX, rect.x + rect.w);
    maxY = Math.max(maxY, rect.y + rect.h);
  }
  return normalizeRect({
    x: minX,
    y: minY,
    w: maxX - minX,
    h: maxY - minY,
  });
}

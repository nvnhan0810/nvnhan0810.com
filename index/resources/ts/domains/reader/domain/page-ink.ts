export const INK_FORMAT = "reader.ink.v1" as const;
export const INK_VERSION = 1 as const;

export type InkPoint = readonly [number, number];

export type InkStroke = {
  color: string;
  width: number;
  opacity: number;
  points: InkPoint[];
};

export type PageInk = {
  version: typeof INK_VERSION;
  strokes: InkStroke[];
};

const DEFAULT_INK_COLOR = "#1A1A1A";

export function emptyPageInk(): PageInk {
  return { version: INK_VERSION, strokes: [] };
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

function parseInkPoint(data: unknown): InkPoint | null {
  if (!Array.isArray(data) || data.length < 2) {
    return null;
  }
  const x = data[0];
  const y = data[1];
  if (typeof x !== "number" || typeof y !== "number") {
    return null;
  }
  return [clamp01(x), clamp01(y)];
}

function parseInkStroke(data: unknown): InkStroke | null {
  if (typeof data !== "object" || data === null) {
    return null;
  }
  const record = data as Record<string, unknown>;
  const color = typeof record.color === "string" ? record.color : DEFAULT_INK_COLOR;
  const width = typeof record.width === "number" && Number.isFinite(record.width) ? record.width : 0;
  const opacityRaw = record.opacity;
  const opacity =
    typeof opacityRaw === "number" && Number.isFinite(opacityRaw) ? clamp01(opacityRaw) : 1;
  const pointsRaw = record.points;
  if (!Array.isArray(pointsRaw) || width <= 0) {
    return null;
  }

  const points: InkPoint[] = [];
  for (const entry of pointsRaw) {
    const point = parseInkPoint(entry);
    if (point !== null) {
      points.push(point);
    }
  }
  if (points.length === 0) {
    return null;
  }

  return { color, width, opacity, points };
}

export function parsePageInk(data: unknown): PageInk {
  if (typeof data !== "object" || data === null) {
    return emptyPageInk();
  }
  const record = data as Record<string, unknown>;
  if (record.version !== INK_VERSION || !Array.isArray(record.strokes)) {
    return emptyPageInk();
  }
  const strokes: InkStroke[] = [];
  for (const entry of record.strokes) {
    const stroke = parseInkStroke(entry);
    if (stroke !== null) {
      strokes.push(stroke);
    }
  }
  return { version: INK_VERSION, strokes };
}

export function drawPageInk(
  ctx: CanvasRenderingContext2D,
  ink: PageInk,
  pageWidth: number,
  pageHeight: number,
): void {
  if (pageWidth <= 0 || pageHeight <= 0 || ink.strokes.length === 0) {
    return;
  }

  ctx.save();
  ctx.lineCap = "round";
  ctx.lineJoin = "round";

  for (const stroke of ink.strokes) {
    if (stroke.points.length === 0) {
      continue;
    }
    ctx.strokeStyle = stroke.color;
    ctx.globalAlpha = stroke.opacity;
    ctx.lineWidth = stroke.width * pageWidth;
    ctx.beginPath();
    stroke.points.forEach(([x, y], index) => {
      const px = x * pageWidth;
      const py = y * pageHeight;
      if (index === 0) {
        ctx.moveTo(px, py);
      } else {
        ctx.lineTo(px, py);
      }
    });
    ctx.stroke();
  }

  ctx.restore();
}

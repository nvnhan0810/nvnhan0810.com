import { useEffect, useRef } from "react";
import { drawPageInk, type PageInk } from "../domain/page-ink";

type Props = {
  ink: PageInk;
  /** CSS pixel size of the page surface (canvas / page root). */
  width: number;
  height: number;
};

/**
 * Read-only freehand overlay (`reader.ink.v1`).
 * Sits under markup highlights; no pencil UI.
 */
const PageInkLayer = ({ ink, width, height }: Props) => {
  const canvasRef = useRef<HTMLCanvasElement | null>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (canvas === null || width <= 0 || height <= 0) {
      return;
    }

    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(width * dpr));
    canvas.height = Math.max(1, Math.floor(height * dpr));
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    const ctx = canvas.getContext("2d");
    if (ctx === null) {
      return;
    }

    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);
    drawPageInk(ctx, ink, width, height);
  }, [ink, width, height]);

  if (width <= 0 || height <= 0 || ink.strokes.length === 0) {
    return null;
  }

  return (
    <canvas
      ref={canvasRef}
      className="pointer-events-none absolute inset-0 z-[15]"
      aria-hidden
    />
  );
};

export default PageInkLayer;

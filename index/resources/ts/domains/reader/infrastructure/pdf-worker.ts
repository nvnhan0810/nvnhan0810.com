import { GlobalWorkerOptions } from "pdfjs-dist";
import pdfWorkerUrl from "pdfjs-dist/build/pdf.worker.min.mjs?url";

let setupPromise: Promise<void> | null = null;

/**
 * pdf.js loads the worker as `type: "module"`. Some hosts serve Vite `.mjs`
 * assets as `application/octet-stream`, which browsers reject. Fetch the
 * asset (fetch does not enforce module MIME) and expose a blob URL with the
 * correct JavaScript MIME type.
 */
export function ensurePdfWorker(): Promise<void> {
  if (setupPromise !== null) {
    return setupPromise;
  }

  setupPromise = (async (): Promise<void> => {
    try {
      const response = await fetch(pdfWorkerUrl, { credentials: "same-origin" });
      if (!response.ok) {
        GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
        return;
      }
      const source = await response.text();
      const blob = new Blob([source], { type: "text/javascript" });
      GlobalWorkerOptions.workerSrc = URL.createObjectURL(blob);
    } catch {
      GlobalWorkerOptions.workerSrc = pdfWorkerUrl;
    }
  })();

  return setupPromise;
}

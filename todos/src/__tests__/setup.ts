import { vi } from "vitest";
Object.defineProperty(window, "matchMedia", {
  value: vi.fn().mockImplementation(() => ({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })),
});
window.scrollTo = vi.fn();

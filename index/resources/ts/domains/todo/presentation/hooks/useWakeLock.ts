import { useEffect, useRef } from "react";

type WakeLockSentinel = Awaited<ReturnType<WakeLock["request"]>>;

type Args = {
  shouldKeepScreenOn?: boolean;
};

/**
 * Keeps the device screen awake while `shouldKeepScreenOn` is true.
 * Re-acquires after tab/PWA becomes visible again (browser releases on hide).
 */
export const useWakeLock = ({ shouldKeepScreenOn = false }: Args): void => {
  const wakeLockRef = useRef<WakeLockSentinel | null>(null);

  useEffect(() => {
    let cancelled = false;

    const releaseWakeLock = async (): Promise<void> => {
      const current = wakeLockRef.current;
      wakeLockRef.current = null;
      if (current === null) {
        return;
      }
      try {
        await current.release();
      } catch {
        // Already released by the platform.
      }
    };

    const requestWakeLock = async (): Promise<void> => {
      if (!("wakeLock" in navigator)) {
        return;
      }
      if (document.visibilityState !== "visible") {
        return;
      }
      if (wakeLockRef.current !== null) {
        return;
      }

      try {
        const sentinel = await navigator.wakeLock.request("screen");
        if (cancelled || !shouldKeepScreenOn) {
          await sentinel.release().catch(() => undefined);
          return;
        }
        wakeLockRef.current = sentinel;
        sentinel.addEventListener("release", () => {
          if (wakeLockRef.current === sentinel) {
            wakeLockRef.current = null;
          }
        });
      } catch {
        wakeLockRef.current = null;
      }
    };

    const onVisibilityChange = (): void => {
      if (document.visibilityState === "visible" && shouldKeepScreenOn) {
        void requestWakeLock();
      }
    };

    if (shouldKeepScreenOn) {
      void requestWakeLock();
    } else {
      void releaseWakeLock();
    }

    document.addEventListener("visibilitychange", onVisibilityChange);

    return () => {
      cancelled = true;
      document.removeEventListener("visibilitychange", onVisibilityChange);
      void releaseWakeLock();
    };
  }, [shouldKeepScreenOn]);
};

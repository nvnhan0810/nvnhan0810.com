import { useCallback, useEffect, useRef, useState } from "react";
import {
  canUseWebPush,
  getCurrentPushSubscription,
  isIosSafari,
  isStandaloneDisplay,
  registerMatrixServiceWorker,
  sendWebPushPresence,
  subscribeWebPush,
  unsubscribeWebPush,
} from "../../infrastructure/webPushClient";

export type WebPushUiState = {
  configured: boolean;
  supported: boolean;
  subscribed: boolean;
  busy: boolean;
  needsHomeScreen: boolean;
  error: string | null;
  enable: () => Promise<void>;
  disable: () => Promise<void>;
};

type Args = {
  configured: boolean;
  publicKey: string | null;
  subscribeUrl: string;
  unsubscribeUrl: string;
  presenceUrl: string;
  /** Only heartbeats while Matrix page is mounted */
  enablePresence: boolean;
};

export const useWebPush = ({
  configured,
  publicKey,
  subscribeUrl,
  unsubscribeUrl,
  presenceUrl,
  enablePresence,
}: Args): WebPushUiState => {
  const [supported, setSupported] = useState(false);
  const [subscribed, setSubscribed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const presenceUrlRef = useRef(presenceUrl);
  presenceUrlRef.current = presenceUrl;

  const refreshSubscription = useCallback(async (): Promise<void> => {
    if (!canUseWebPush()) {
      setSupported(false);
      setSubscribed(false);
      return;
    }
    setSupported(true);
    await registerMatrixServiceWorker();
    const current = await getCurrentPushSubscription();
    setSubscribed(current !== null);
  }, []);

  useEffect(() => {
    void refreshSubscription().catch(() => {
      setSupported(canUseWebPush());
    });
  }, [refreshSubscription]);

  useEffect(() => {
    if (!enablePresence || !subscribed) {
      return;
    }

    const beat = (): void => {
      const focused =
        document.visibilityState === "visible" && document.hasFocus();
      void sendWebPushPresence(presenceUrlRef.current, focused).catch(() => {
        // Ignore transient network errors.
      });
    };

    beat();
    const intervalId = window.setInterval(beat, 20_000);
    const onVis = (): void => beat();
    const onFocus = (): void => beat();
    const onBlur = (): void => {
      void sendWebPushPresence(presenceUrlRef.current, false).catch(() => undefined);
    };

    document.addEventListener("visibilitychange", onVis);
    window.addEventListener("focus", onFocus);
    window.addEventListener("blur", onBlur);

    return () => {
      window.clearInterval(intervalId);
      document.removeEventListener("visibilitychange", onVis);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener("blur", onBlur);
      void sendWebPushPresence(presenceUrlRef.current, false).catch(() => undefined);
    };
  }, [enablePresence, subscribed]);

  const needsHomeScreen = isIosSafari() && !isStandaloneDisplay();

  const enable = useCallback(async (): Promise<void> => {
    setError(null);
    if (!configured || publicKey === null || publicKey === "") {
      setError("Server chưa cấu hình VAPID keys");
      return;
    }
    if (needsHomeScreen) {
      setError("Trên iPhone: Share → Add to Home Screen, rồi mở từ icon để bật noti");
      return;
    }
    setBusy(true);
    try {
      await subscribeWebPush(publicKey, {
        subscribe: subscribeUrl,
        unsubscribe: unsubscribeUrl,
        presence: presenceUrl,
      });
      setSubscribed(true);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Không bật được thông báo";
      setError(message);
    } finally {
      setBusy(false);
    }
  }, [
    configured,
    publicKey,
    needsHomeScreen,
    subscribeUrl,
    unsubscribeUrl,
    presenceUrl,
  ]);

  const disable = useCallback(async (): Promise<void> => {
    setError(null);
    setBusy(true);
    try {
      await unsubscribeWebPush({
        subscribe: subscribeUrl,
        unsubscribe: unsubscribeUrl,
        presence: presenceUrl,
      });
      setSubscribed(false);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Không tắt được thông báo";
      setError(message);
    } finally {
      setBusy(false);
    }
  }, [subscribeUrl, unsubscribeUrl, presenceUrl]);

  return {
    configured,
    supported,
    subscribed,
    busy,
    needsHomeScreen,
    error,
    enable,
    disable,
  };
};

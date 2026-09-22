import { useCallback, useEffect, useRef, useState } from "react";
import {
  canUseWebPush,
  fetchWebPushStatus,
  getCurrentPushSubscription,
  isIosSafari,
  isStandaloneDisplay,
  registerMatrixServiceWorker,
  sendWebPushPresence,
  subscribeWebPush,
  syncExistingWebPushSubscription,
  unsubscribeWebPush,
} from "../../infrastructure/webPushClient";

export type WebPushUiState = {
  configured: boolean;
  supported: boolean;
  subscribed: boolean;
  serverSubscriptionCount: number;
  busy: boolean;
  needsHomeScreen: boolean;
  error: string | null;
  enable: () => Promise<void>;
  disable: () => Promise<void>;
  /** Idempotent: ensure this device is subscribed + saved on server (call on Start). */
  ensureSubscribed: () => Promise<boolean>;
};

type Args = {
  configured: boolean;
  publicKey: string | null;
  subscribeUrl: string;
  unsubscribeUrl: string;
  presenceUrl: string;
  statusUrl: string;
  /** Only heartbeats while Matrix page is mounted */
  enablePresence: boolean;
};

export const useWebPush = ({
  configured,
  publicKey,
  subscribeUrl,
  unsubscribeUrl,
  presenceUrl,
  statusUrl,
  enablePresence,
}: Args): WebPushUiState => {
  const [supported, setSupported] = useState(false);
  const [subscribed, setSubscribed] = useState(false);
  const [serverSubscriptionCount, setServerSubscriptionCount] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const presenceUrlRef = useRef(presenceUrl);
  const subscribeUrlRef = useRef(subscribeUrl);
  const statusUrlRef = useRef(statusUrl);
  presenceUrlRef.current = presenceUrl;
  subscribeUrlRef.current = subscribeUrl;
  statusUrlRef.current = statusUrl;

  const refreshServerStatus = useCallback(async (): Promise<void> => {
    try {
      const status = await fetchWebPushStatus(statusUrlRef.current);
      setServerSubscriptionCount(status.subscriptionCount);
    } catch {
      // Ignore — local subscription state remains source for UI toggle.
    }
  }, []);

  const refreshSubscription = useCallback(async (): Promise<void> => {
    if (!canUseWebPush()) {
      setSupported(false);
      setSubscribed(false);
      return;
    }
    setSupported(true);
    await registerMatrixServiceWorker();
    const current = await getCurrentPushSubscription();
    if (current !== null) {
      // Re-sync to server in case permission was granted but POST never landed.
      try {
        await syncExistingWebPushSubscription(subscribeUrlRef.current);
        setSubscribed(true);
      } catch {
        setSubscribed(true);
        setError("Đã có quyền noti local nhưng chưa lưu được lên server — bấm Bật Web Push lại");
      }
    } else {
      setSubscribed(false);
    }
    await refreshServerStatus();
  }, [refreshServerStatus]);

  useEffect(() => {
    void refreshSubscription().catch(() => {
      setSupported(canUseWebPush());
    });
  }, [refreshSubscription]);

  useEffect(() => {
    if (!enablePresence || !subscribed) {
      return;
    }

    const beat = (focused: boolean): void => {
      void sendWebPushPresence(presenceUrlRef.current, focused).catch(() => {
        // Ignore transient network errors.
      });
    };

    const syncFocus = (): void => {
      const focused =
        document.visibilityState === "visible" && document.hasFocus();
      beat(focused);
    };

    syncFocus();
    const intervalId = window.setInterval(syncFocus, 10_000);
    const onVis = (): void => syncFocus();
    const onFocus = (): void => beat(true);
    const onBlur = (): void => beat(false);
    const onPageHide = (): void => beat(false);
    const onFreeze = (): void => beat(false);

    document.addEventListener("visibilitychange", onVis);
    window.addEventListener("focus", onFocus);
    window.addEventListener("blur", onBlur);
    window.addEventListener("pagehide", onPageHide);
    document.addEventListener("freeze", onFreeze);

    return () => {
      window.clearInterval(intervalId);
      document.removeEventListener("visibilitychange", onVis);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener("blur", onBlur);
      window.removeEventListener("pagehide", onPageHide);
      document.removeEventListener("freeze", onFreeze);
      beat(false);
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
      await refreshServerStatus();
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
    refreshServerStatus,
  ]);

  const ensureSubscribed = useCallback(async (): Promise<boolean> => {
    if (!configured || publicKey === null || publicKey === "") {
      return false;
    }
    if (needsHomeScreen) {
      return false;
    }
    if (!canUseWebPush()) {
      return false;
    }
    try {
      await subscribeWebPush(publicKey, {
        subscribe: subscribeUrl,
        unsubscribe: unsubscribeUrl,
        presence: presenceUrl,
      });
      setSubscribed(true);
      setError(null);
      await refreshServerStatus();
      return true;
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Không bật được Web Push";
      setError(message);
      return false;
    }
  }, [
    configured,
    publicKey,
    needsHomeScreen,
    subscribeUrl,
    unsubscribeUrl,
    presenceUrl,
    refreshServerStatus,
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
      await refreshServerStatus();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : "Không tắt được thông báo";
      setError(message);
    } finally {
      setBusy(false);
    }
  }, [subscribeUrl, unsubscribeUrl, presenceUrl, refreshServerStatus]);

  return {
    configured,
    supported,
    subscribed,
    serverSubscriptionCount,
    busy,
    needsHomeScreen,
    error,
    enable,
    disable,
    ensureSubscribed,
  };
};

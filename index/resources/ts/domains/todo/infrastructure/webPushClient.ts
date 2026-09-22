import { jsonFetch } from "@/ts/utils/jsonFetch";

const SERVICE_WORKER_URL = "/sw.js";

export type WebPushSubscribeUrls = {
  subscribe: string;
  unsubscribe: string;
  presence: string;
  status?: string;
};

const urlBase64ToUint8Array = (base64String: string): Uint8Array => {
  const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = window.atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; i += 1) {
    output[i] = raw.charCodeAt(i);
  }
  return output;
};

export const isStandaloneDisplay = (): boolean => {
  if (typeof window === "undefined") {
    return false;
  }
  const media = window.matchMedia("(display-mode: standalone)").matches;
  const iosStandalone =
    "standalone" in window.navigator &&
    (window.navigator as Navigator & { standalone?: boolean }).standalone === true;
  return media || iosStandalone;
};

export const isIosSafari = (): boolean => {
  if (typeof window === "undefined") {
    return false;
  }
  const ua = window.navigator.userAgent;
  const iOS =
    /iPad|iPhone|iPod/.test(ua) ||
    (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  const webkit = /WebKit/.test(ua);
  return iOS && webkit;
};

export const canUseWebPush = (): boolean =>
  typeof window !== "undefined" &&
  "serviceWorker" in navigator &&
  "PushManager" in window &&
  "Notification" in window;

export const registerMatrixServiceWorker = async (): Promise<ServiceWorkerRegistration | null> => {
  if (!canUseWebPush()) {
    return null;
  }
  return navigator.serviceWorker.register(SERVICE_WORKER_URL, { scope: "/" });
};

export const getCurrentPushSubscription = async (): Promise<PushSubscription | null> => {
  if (!canUseWebPush()) {
    return null;
  }
  const registration = await navigator.serviceWorker.ready;
  return registration.pushManager.getSubscription();
};

const persistSubscription = async (
  subscription: PushSubscription,
  subscribeUrl: string,
): Promise<void> => {
  const json = subscription.toJSON();
  if (!json.endpoint || !json.keys?.p256dh || !json.keys?.auth) {
    throw new Error("Subscription không hợp lệ");
  }

  await jsonFetch(subscribeUrl, {
    method: "POST",
    body: {
      endpoint: json.endpoint,
      keys: {
        p256dh: json.keys.p256dh,
        auth: json.keys.auth,
      },
      contentEncoding:
        PushManager.supportedContentEncodings?.includes("aes128gcm")
          ? "aes128gcm"
          : (PushManager.supportedContentEncodings?.[0] ?? "aes128gcm"),
    },
  });
};

/**
 * If the browser already has a PushSubscription, re-POST it to the server.
 * Fixes the common case: permission granted locally but never saved in DB.
 */
export const syncExistingWebPushSubscription = async (
  subscribeUrl: string,
): Promise<boolean> => {
  const subscription = await getCurrentPushSubscription();
  if (subscription === null) {
    return false;
  }
  await persistSubscription(subscription, subscribeUrl);
  return true;
};

export const subscribeWebPush = async (
  publicKey: string,
  urls: WebPushSubscribeUrls,
): Promise<PushSubscription> => {
  const registration = await registerMatrixServiceWorker();
  if (registration === null) {
    throw new Error("Trình duyệt không hỗ trợ Web Push");
  }
  await navigator.serviceWorker.ready;

  const permission = await Notification.requestPermission();
  if (permission !== "granted") {
    throw new Error("Bạn đã từ chối quyền thông báo");
  }

  let subscription = await registration.pushManager.getSubscription();
  if (subscription === null) {
    subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicKey) as BufferSource,
    });
  }

  await persistSubscription(subscription, urls.subscribe);
  return subscription;
};

export const unsubscribeWebPush = async (urls: WebPushSubscribeUrls): Promise<void> => {
  const subscription = await getCurrentPushSubscription();
  if (subscription === null) {
    return;
  }
  const endpoint = subscription.endpoint;
  await subscription.unsubscribe();
  await jsonFetch(urls.unsubscribe, {
    method: "DELETE",
    body: { endpoint },
  });
};

export const sendWebPushPresence = async (
  presenceUrl: string,
  focused: boolean,
): Promise<void> => {
  const subscription = await getCurrentPushSubscription();
  if (subscription === null) {
    return;
  }
  await jsonFetch(presenceUrl, {
    method: "POST",
    body: {
      endpoint: subscription.endpoint,
      focused,
    },
  });
};

export type WebPushServerStatus = {
  configured: boolean;
  subscriptionCount: number;
};

export const fetchWebPushStatus = async (
  statusUrl: string,
): Promise<WebPushServerStatus> => {
  const raw = await jsonFetch<Record<string, unknown>>(statusUrl, { method: "GET" });
  return {
    configured: raw.configured === true,
    subscriptionCount:
      typeof raw.subscriptionCount === "number" ? raw.subscriptionCount : 0,
  };
};

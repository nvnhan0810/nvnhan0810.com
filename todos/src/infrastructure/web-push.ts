import { request, action } from "./todos-api";
export async function currentSubscription() {
  if (!("serviceWorker" in navigator) || !("PushManager" in window))
    return null;
  const registration = await navigator.serviceWorker.getRegistration();
  return (await registration?.pushManager.getSubscription()) ?? null;
}
export async function enableNotifications() {
  if (
    !("serviceWorker" in navigator) ||
    !("PushManager" in window) ||
    !("Notification" in window)
  )
    throw new Error(
      "Trình duyệt chưa hỗ trợ Web Push. Trên iPhone, hãy thêm app vào màn hình chính.",
    );
  const permission = await Notification.requestPermission();
  if (permission !== "granted")
    throw new Error("Bạn chưa cấp quyền thông báo.");
  const config = await request<{ publicKey: string; configured: boolean }>(
    "/web-push/public-key",
  );
  if (!config.configured)
    throw new Error("Web Push chưa được cấu hình trên server.");
  await navigator.serviceWorker.register("/sw.js");
  const registration = await navigator.serviceWorker.ready;
  const padded = config.publicKey.replaceAll("-", "+").replaceAll("_", "/");
  const key = Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
  const subscription =
    (await registration.pushManager.getSubscription()) ??
    (await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: key,
    }));
  await action("/matrix/web-push/subscribe", "POST", {
    subscription: subscription.toJSON(),
    userAgent: navigator.userAgent,
  });
  return subscription;
}
export async function disableNotifications() {
  const subscription = await currentSubscription();
  if (!subscription) return;
  await action("/matrix/web-push/subscribe", "DELETE", {
    endpoint: subscription.endpoint,
  });
  await subscription.unsubscribe();
}

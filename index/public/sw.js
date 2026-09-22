/* global self, clients */
const MATRIX_PATH = "/matrix";
const DEFAULT_ICON = "/images/android-chrome-192x192.png";

self.addEventListener("install", (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

/**
 * Absolute URL for notification assets (iOS is picky about relative/GIF icons).
 * @param {string | undefined} path
 */
const toAbsoluteUrl = (path) => {
  if (!path) {
    return self.location.origin + DEFAULT_ICON;
  }
  if (/^https?:\/\//i.test(path)) {
    return path;
  }
  return self.location.origin + (path.startsWith("/") ? path : `/${path}`);
};

self.addEventListener("push", (event) => {
  // iOS/WebKit: EVERY push MUST call showNotification before waitUntil settles.
  // Never early-return based on focused clients — backgrounded PWAs can still
  // report a "focused" window and that would swallow the banner.
  event.waitUntil(
    (async () => {
      let payload = {
        title: "Pomodoro",
        body: "Phase đã kết thúc — mở Matrix để tiếp tục.",
        url: MATRIX_PATH,
        tag: "todo-pomodoro-phase",
        icon: DEFAULT_ICON,
      };

      try {
        if (event.data) {
          const parsed = event.data.json();
          payload = {
            ...payload,
            ...parsed,
            url: typeof parsed.url === "string" ? parsed.url : MATRIX_PATH,
            icon:
              typeof parsed.icon === "string" && !/\.gif(\?|$)/i.test(parsed.icon)
                ? parsed.icon
                : DEFAULT_ICON,
          };
        }
      } catch {
        // Keep defaults when payload is missing / invalid.
      }

      await self.registration.showNotification(payload.title || "Pomodoro", {
        body: payload.body || "",
        icon: toAbsoluteUrl(payload.icon),
        badge: toAbsoluteUrl("/images/favicon-32x32.png"),
        tag: payload.tag || "todo-pomodoro-phase",
        data: { url: payload.url || MATRIX_PATH },
      });
    })(),
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const targetUrl = event.notification.data?.url || MATRIX_PATH;

  event.waitUntil(
    (async () => {
      const windowClients = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });

      for (const client of windowClients) {
        try {
          const path = new URL(client.url).pathname;
          if (path === MATRIX_PATH || path.startsWith(`${MATRIX_PATH}/`)) {
            await client.focus();
            if ("navigate" in client && typeof client.navigate === "function") {
              await client.navigate(targetUrl);
            }
            return;
          }
        } catch {
          // Try next client.
        }
      }

      await self.clients.openWindow(targetUrl);
    })(),
  );
});

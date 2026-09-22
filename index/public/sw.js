/* global self, clients */
const MATRIX_PATH = "/matrix";
const FOCUS_CHECK_PATH_PREFIX = "/matrix";

self.addEventListener("install", (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

/**
 * @param {ReadonlyArray<WindowClient>} windowClients
 */
const hasFocusedMatrix = (windowClients) =>
  windowClients.some((client) => {
    if (!client.focused) {
      return false;
    }
    try {
      const path = new URL(client.url).pathname;
      return path === FOCUS_CHECK_PATH_PREFIX || path.startsWith(`${FOCUS_CHECK_PATH_PREFIX}/`);
    } catch {
      return false;
    }
  });

self.addEventListener("push", (event) => {
  event.waitUntil(
    (async () => {
      const windowClients = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });

      if (hasFocusedMatrix(windowClients)) {
        return;
      }

      let payload = {
        title: "Pomodoro",
        body: "Phase đã kết thúc — mở Matrix để tiếp tục.",
        url: MATRIX_PATH,
        tag: "todo-pomodoro-phase",
      };

      try {
        if (event.data) {
          const parsed = event.data.json();
          payload = {
            ...payload,
            ...parsed,
            url: typeof parsed.url === "string" ? parsed.url : MATRIX_PATH,
          };
        }
      } catch {
        // Keep defaults when payload is missing / invalid.
      }

      await self.registration.showNotification(payload.title, {
        body: payload.body,
        icon: payload.icon || "/images/android-chrome-192x192.png",
        badge: "/images/favicon-32x32.png",
        tag: payload.tag || "todo-pomodoro-phase",
        renotify: true,
        requireInteraction: true,
        data: { url: payload.url },
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

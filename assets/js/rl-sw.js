/*
=========================
BUTTERFLY SERVICE WORKER
Served at the site root (see RL_Assets::serve_service_worker()) so
its push scope covers the whole site, not just /wp-content/.../assets.
__RL_ICON_URL__ / __RL_BADGE_URL__ are replaced with real, absolute
URLs by that same PHP handler before this reaches the browser — this
file has no other way to know the plugin/site URL.
=========================
*/

self.addEventListener("push", function (event) {

    var data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: "Butterfly", body: event.data ? event.data.text() : "" };
    }

    var title = data.title || "Butterfly";

    var options = {
        body: data.body || "",
        icon: "__RL_ICON_URL__",
        badge: "__RL_BADGE_URL__",
        data: { url: data.url || "/" }
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", function (event) {

    event.notification.close();

    var url = (event.notification.data && event.notification.data.url) || "/";

    event.waitUntil(
        clients.matchAll({ type: "window", includeUncontrolled: true }).then(function (windowClients) {

            for (var i = 0; i < windowClients.length; i++) {
                if (windowClients[i].url === url && "focus" in windowClients[i]) {
                    return windowClients[i].focus();
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});

document.addEventListener("DOMContentLoaded", function () {

    /* ===========================
    PUSH NOTIFICATION OPT-IN
    Wires the on/off switch on the Settings tab (SPA-only — standalone
    pages like the restaurant page or My Entries have no toggle of
    their own, they just link to Settings) to the browser's Push API.
    Service worker registration and the in-context notify prompt below
    still run on every page regardless of whether a toggle element is
    present, so the prompt keeps working site-wide even though the
    switch itself only exists in one place now. No-ops entirely if the
    browser doesn't support push, or if the admin hasn't generated
    VAPID keys yet (RL_PUSH.vapidPublicKey empty).
    =========================== */

    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
        return;
    }

    if (typeof RL_PUSH === "undefined" || !RL_PUSH.vapidPublicKey) {
        return;
    }

    var toggles = document.querySelectorAll("#rl-push-toggle");

    function urlBase64ToUint8Array(base64String) {
        var padding = "=".repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);

        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }

    function arrayBufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = "";

        for (var i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }

        return window.btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
    }

    function setToggleState(subscribed) {
        toggles.forEach(function (toggle) {
            toggle.dataset.subscribed = subscribed ? "1" : "0";
            toggle.setAttribute("aria-checked", subscribed ? "true" : "false");
        });
    }

    function postToServer(action, extra) {
        var body = new URLSearchParams(Object.assign({
            action: action,
            nonce: RL_PUSH.nonce
        }, extra || {}));

        return fetch(RL_PUSH.ajaxUrl, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: body
        });
    }

    // Shared subscribe flow — used by both the hamburger-menu toggle
    // and the in-context prompt's "Allow" button.
    function subscribe(registration, onDone) {
        Notification.requestPermission().then(function (permission) {

            if (permission !== "granted") {
                // Most commonly means notifications are already
                // blocked for this site (Chrome won't re-prompt
                // once denied — it just resolves "denied"
                // immediately with no visible dialog). Surfaced
                // here instead of failing silently, since that
                // silence is indistinguishable from a bug.
                window.alert(
                    permission === "denied"
                        ? "Notifications are blocked for this site in your browser settings. Click the icon next to the address bar to allow them, then try again."
                        : "Notification permission wasn't granted."
                );
                if (onDone) onDone(false);
                return;
            }

            registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(RL_PUSH.vapidPublicKey)
            }).then(function (subscription) {

                postToServer("rl_push_subscribe", {
                    endpoint: subscription.endpoint,
                    p256dh: arrayBufferToBase64Url(subscription.getKey("p256dh")),
                    auth: arrayBufferToBase64Url(subscription.getKey("auth"))
                }).then(function () {
                    setToggleState(true);
                    if (onDone) onDone(true);
                });

            }).catch(function (err) {
                console.error("Butterfly push subscribe failed:", err);
                window.alert("Couldn't enable notifications: " + err.message);
                if (onDone) onDone(false);
            });
        });
    }

    // In-context prompt — shown once, the first time a customer's
    // earned points, instead of only being reachable via the
    // hamburger menu where most people never open it.
    function markPrompted() {
        postToServer("rl_mark_notify_prompted");
    }

    function showNotifyPrompt(registration) {
        if (document.getElementById("rl-notify-prompt")) return;

        var wrap = document.createElement("div");
        wrap.id = "rl-notify-prompt";
        wrap.className = "rl-notify-prompt-overlay";
        wrap.innerHTML =
            '<div class="rl-notify-prompt-sheet">' +
            '<div class="rl-notify-prompt-icon">' +
            '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke-linecap="round" stroke-linejoin="round"/>' +
            '<path d="M13.7 21a2 2 0 0 1-3.4 0" stroke-linecap="round"/>' +
            "</svg></div>" +
            "<h4>Never miss a prize</h4>" +
            "<p>We&rsquo;ll let you know about new giveaways and if you win &mdash; nothing else.</p>" +
            '<div class="rl-notify-prompt-actions">' +
            '<button type="button" class="rl-notify-prompt-later">Not now</button>' +
            '<button type="button" class="rl-notify-prompt-allow">Allow</button>' +
            "</div></div>";

        document.body.appendChild(wrap);
        requestAnimationFrame(function () {
            wrap.classList.add("show");
        });

        function close() {
            markPrompted();
            wrap.classList.remove("show");
            setTimeout(function () {
                wrap.remove();
            }, 200);
        }

        wrap.querySelector(".rl-notify-prompt-later").addEventListener("click", close);
        wrap.querySelector(".rl-notify-prompt-allow").addEventListener("click", function () {
            subscribe(registration, function () {
                close();
            });
        });
    }

    navigator.serviceWorker.register("/rl-sw.js", { scope: "/" }).then(function (registration) {

        registration.pushManager.getSubscription().then(function (existing) {
            setToggleState(!!existing);

            if (!existing && typeof RL_PUSH !== "undefined" && RL_PUSH.showNotifyPrompt) {
                showNotifyPrompt(registration);
            }
        });

        if (toggles.length) {
            toggles.forEach(function (toggle) {

                toggle.addEventListener("click", function () {

                    if (toggle.dataset.subscribed === "1") {

                        registration.pushManager.getSubscription().then(function (existing) {

                            if (!existing) {
                                setToggleState(false);
                                return;
                            }

                            var endpoint = existing.endpoint;

                            existing.unsubscribe().then(function () {
                                setToggleState(false);
                                postToServer("rl_push_unsubscribe", { endpoint: endpoint });
                            });
                        });

                        return;
                    }

                    subscribe(registration);
                });
            });
        }
    });
});

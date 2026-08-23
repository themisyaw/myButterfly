// Add-to-home-screen: a soft auto-shown banner after a customer's 2nd
// visit (dismissible, never shown again once dismissed or installed),
// PLUS a "Download" control on the Settings tab so installing is
// reachable any time, not only from that one-time popup. Both paths
// share the same underlying browser install flow. Visit counting and
// dismissal live in localStorage only; nothing here talks to the server.
(function () {
  "use strict";

  var STORAGE_VISITS = "rl_visit_count";
  var STORAGE_DISMISSED = "rl_install_dismissed";
  var VISITS_BEFORE_PROMPT = 2;

  function isStandalone() {
    return (
      (window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
      window.navigator.standalone === true
    );
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent) && !window.MSStream;
  }

  function getVisitCount() {
    var count = parseInt(window.localStorage.getItem(STORAGE_VISITS) || "0", 10);
    count = isNaN(count) ? 0 : count;
    count += 1;
    window.localStorage.setItem(STORAGE_VISITS, String(count));
    return count;
  }

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
    } else {
      callback();
    }
  }

  function isDismissed() {
    return window.localStorage.getItem(STORAGE_DISMISSED) === "1";
  }

  var alreadyInstalled = isStandalone();
  var deferredPrompt = null;
  // Tallied once per page load regardless of platform/eligibility —
  // this is the "how many times has this person visited" count the
  // auto-banner's threshold is based on.
  var visitCount = getVisitCount();

  function dismissBanner() {
    window.localStorage.setItem(STORAGE_DISMISSED, "1");
    var banner = document.getElementById("rl-install-banner");
    if (banner) banner.remove();
  }

  function buildBanner(onAddClick, addLabel, subtitleText) {
    if (document.getElementById("rl-install-banner")) return;

    var banner = document.createElement("div");
    banner.id = "rl-install-banner";
    banner.className = "rl-install-banner";
    banner.innerHTML =
      '<div class="rl-install-banner-icon">' +
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
      '<path d="M12 3v13m0 0-4-4m4 4 4-4M5 19h14" stroke-linecap="round" stroke-linejoin="round"/>' +
      "</svg></div>" +
      '<div class="rl-install-banner-text">' +
      "<strong>Add Butterfly to your Home Screen</strong>" +
      "<span>" + subtitleText + "</span>" +
      "</div>" +
      '<button type="button" class="rl-install-banner-go">' + addLabel + "</button>" +
      '<button type="button" class="rl-install-banner-close" aria-label="Dismiss">&times;</button>';

    banner.querySelector(".rl-install-banner-close").addEventListener("click", dismissBanner);
    banner.querySelector(".rl-install-banner-go").addEventListener("click", onAddClick);

    document.body.appendChild(banner);
    requestAnimationFrame(function () {
      banner.classList.add("show");
    });
  }

  // Shared by the banner's "Add" button and the Settings tab's
  // "Download" button — whichever one the customer used.
  function triggerInstall(onDone) {
    if (alreadyInstalled) {
      if (onDone) onDone();
      return;
    }

    if (isIos()) {
      // No programmatic install on iOS Safari — only a manual step.
      window.alert('On iPhone/iPad: tap the Share icon, then "Add to Home Screen".');
      if (onDone) onDone();
      return;
    }

    if (!deferredPrompt) {
      // Chrome/Android hasn't offered installability yet on this
      // visit (it decides this itself, based on its own engagement
      // heuristics) — nothing to prompt with yet.
      window.alert("Your browser hasn't offered to install the app yet — try again in a moment, or use your browser's own “Add to Home Screen” option.");
      if (onDone) onDone();
      return;
    }

    deferredPrompt.prompt();
    deferredPrompt.userChoice.finally(function () {
      deferredPrompt = null;
      updateSettingsButton();
      if (onDone) onDone();
    });
  }

  function updateSettingsButton() {
    var btn = document.getElementById("rl-settings-install-btn");
    if (!btn) return;

    if (alreadyInstalled) {
      btn.textContent = "Installed";
      btn.disabled = true;
    } else {
      btn.textContent = isIos() ? "How to install" : "Download";
      btn.disabled = false;
    }
  }

  onReady(function () {
    var btn = document.getElementById("rl-settings-install-btn");
    if (!btn) return;

    updateSettingsButton();
    btn.addEventListener("click", function () {
      triggerInstall();
    });
  });

  if (alreadyInstalled) {
    return;
  }

  if (isIos()) {
    if (!isDismissed() && visitCount >= VISITS_BEFORE_PROMPT) {
      onReady(function () {
        buildBanner(
          dismissBanner,
          "Got it",
          "Tap the Share icon, then “Add to Home Screen”"
        );
      });
    }
    return;
  }

  // Always listen (not gated behind visit count) so the Settings
  // "Download" button has something to work with as soon as the
  // browser decides the app is installable, on any visit — not only
  // during the auto-banner's own visit-2+ window.
  window.addEventListener("beforeinstallprompt", function (event) {
    event.preventDefault();
    deferredPrompt = event;
    updateSettingsButton();

    if (!isDismissed() && visitCount >= VISITS_BEFORE_PROMPT) {
      onReady(function () {
        buildBanner(
          function () {
            triggerInstall(dismissBanner);
          },
          "Add",
          "Opens like an app, one tap from now on"
        );
      });
    }
  });

  window.addEventListener("appinstalled", function () {
    alreadyInstalled = true;
    deferredPrompt = null;
    dismissBanner();
    updateSettingsButton();
  });
})();

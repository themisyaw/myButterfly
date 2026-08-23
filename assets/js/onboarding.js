// First-run onboarding slides — shown once for a new customer (the
// overlay only exists in the DOM at all if the server decided it
// hasn't been seen yet). Skip or reaching the last slide both mark it
// seen server-side so it doesn't come back on the next visit.
document.addEventListener("DOMContentLoaded", function () {
  var overlay = document.getElementById("rl-onboarding");
  if (!overlay) return;

  var slides = overlay.querySelectorAll(".rl-onboarding-slide");
  var dots = overlay.querySelectorAll(".rl-onboarding-dot");
  var nextBtn = document.getElementById("rl-onboarding-next");
  var skipBtn = document.getElementById("rl-onboarding-skip");
  var current = 0;

  function goTo(index) {
    current = index;
    slides.forEach(function (slide, i) {
      slide.classList.toggle("active", i === index);
    });
    dots.forEach(function (dot, i) {
      dot.classList.toggle("active", i === index);
    });
    if (nextBtn) {
      nextBtn.textContent = index === slides.length - 1 ? "Get started" : "Next";
    }
  }

  function finish() {
    overlay.classList.add("rl-onboarding-closing");
    setTimeout(function () {
      overlay.remove();
    }, 200);

    if (typeof RL_ONBOARDING === "undefined") return;

    fetch(RL_ONBOARDING.ajaxUrl, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: "action=rl_mark_onboarded&nonce=" + encodeURIComponent(RL_ONBOARDING.nonce),
    }).catch(function () {
      // Non-fatal — worst case the slides show again next visit.
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function () {
      if (current < slides.length - 1) {
        goTo(current + 1);
      } else {
        finish();
      }
    });
  }

  if (skipBtn) {
    skipBtn.addEventListener("click", finish);
  }
});

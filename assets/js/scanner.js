// Global scanner instance tracker to prevent camera leaks
let scannerInstance = null;

document.addEventListener("DOMContentLoaded", function () {
  initScanner();
});

// Native UI Toast Notification Helper
function showToast(message, type = "success") {
  let toast = document.getElementById("rl-toast-notification");

  if (!toast) {
    toast = document.createElement("div");
    toast.id = "rl-toast-notification";
    document.body.appendChild(toast);
  }

  toast.className = `rl-toast rl-toast-${type} show`;
  toast.innerText = message;

  setTimeout(() => {
    toast.classList.remove("show");
  }, 3000);
}

// Custom Native Confirmation Modal Helper. title/message are treated
// as plain text (escaped here) since callers build them from
// customer/item names that ultimately come from an AJAX response.
function showConfirmModal(title, message, onConfirm) {
  let existingModal = document.getElementById("rl-confirm-modal");
  if (existingModal) existingModal.remove();

  const modalHtml = `
    <div id="rl-confirm-modal" class="rl-modal-overlay">
      <div class="rl-modal-card">
        <h3>${escapeHtml(title)}</h3>
        <p>${escapeHtml(message)}</p>
        <div class="rl-modal-actions">
          <button id="rl-modal-cancel" class="rl-btn-cancel">Cancel</button>
          <button id="rl-modal-confirm" class="rl-btn-confirm">Confirm</button>
        </div>
      </div>
    </div>
  `;

  document.body.insertAdjacentHTML("beforeend", modalHtml);

  const modal = document.getElementById("rl-confirm-modal");
  const cancelBtn = document.getElementById("rl-modal-cancel");
  const confirmBtn = document.getElementById("rl-modal-confirm");

  requestAnimationFrame(() => modal.classList.add("show"));

  function closeModal() {
    modal.classList.remove("show");
    setTimeout(() => modal.remove(), 250);
  }

  cancelBtn.addEventListener("click", closeModal);

  confirmBtn.addEventListener("click", () => {
    closeModal();
    onConfirm();
  });
}

// Returns the location currently selected for scanning (from the
// location switcher if one is rendered, otherwise the localized default).
function currentLocationId() {
  const select = document.getElementById("rl-location-select");
  if (select && select.value) {
    return select.value;
  }
  return RL_AJAX.location_id || "";
}

// Helper function to initialize or restart the camera scanner
function initScanner() {
  const scannerElement = document.getElementById("rl-scanner");

  if (scannerElement) {
    if (scannerInstance) {
      try {
        scannerInstance.clear();
      } catch (err) {
        // Fallback catch if scanner canvas was already removed
      }
    }

    scannerInstance = new Html5Qrcode("rl-scanner");

    scannerInstance.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: 250 },
      function (decodedText) {
        scannerInstance.stop();

        fetch(RL_AJAX.ajax_url, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body:
            "action=rl_find_customer" +
            "&token=" +
            encodeURIComponent(decodedText) +
            "&location_id=" +
            encodeURIComponent(currentLocationId()) +
            "&nonce=" +
            RL_AJAX.nonce
        })
          .then((response) => response.json())
          .then((data) => {
            if (!data.success) {
              document.getElementById("rl-scanner-result").innerHTML =
                data.message;
              showToast(data.message || "Customer not found", "error");
              return;
            }

            RL.customer = data.customer;
            RL.redeemItems = data.redeem_items;

            renderCustomer();
          });
      }
    );
  }
}

// Function to clear active customer data and restart the scanner camera
function resetScanner() {
  if (typeof RL !== "undefined") {
    RL.customer = null;
    RL.redeemItems = null;
  }

  const container = document.getElementById("rl-scanner-result");
  if (container) {
    container.innerHTML = "";
  }

  const scannerTitle = document.querySelector(".rl-scanner-title");
  const scannerElement = document.getElementById("rl-scanner");

  if (scannerTitle) scannerTitle.style.display = "";
  if (scannerElement) scannerElement.style.display = "";

  initScanner();
}

// Escapes text for safe use inside innerHTML — the customer's name/
// email and item titles/descriptions all come from AJAX responses,
// which wp_send_json() only JSON-encodes, not HTML-escapes. Some of
// these values are also interpolated into quoted attributes (e.g.
// data-title="...", src="...") below, so quotes are escaped
// explicitly too — the browser's own text->innerHTML serialization
// (div.textContent -> div.innerHTML) only escapes &, <, > and leaves
// quote characters untouched, which would otherwise let a value
// containing a `"` break out of the attribute.
function escapeHtml(value) {
  const div = document.createElement("div");
  div.textContent = value === null || value === undefined ? "" : String(value);
  return div.innerHTML.replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function renderCustomer() {
  const container = document.getElementById("rl-scanner-result");

  if (!container) return;

  const scannerTitle = document.querySelector(".rl-scanner-title");
  const scannerElement = document.getElementById("rl-scanner");

  if (scannerTitle) scannerTitle.style.display = "none";
  if (scannerElement) scannerElement.style.display = "none";

  // Parse points to Float for clean display
  let displayPoints = parseFloat(RL.customer.points || 0);
  // Format to max 2 decimal places, trimming trailing zeroes if whole number
  let formattedPoints = Number.isInteger(displayPoints)
    ? displayPoints
    : displayPoints.toFixed(2);

  let html = `
<svg style="position: absolute; width: 0; height: 0; overflow: hidden;" version="1.1" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="rlStarGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#2dd4bf" stop-opacity="1" />
      <stop offset="100%" stop-color="#0f766e" stop-opacity="1" />
    </linearGradient>
  </defs>
</svg>

<div class="rl-customer-card">
  <div class="card-header">
    <h2>${escapeHtml(RL.customer.name)}</h2>
    <p>${escapeHtml(RL.customer.email)}</p>
  </div>

  <h3 class="points-display">
    <svg class="rl-spark-icon" viewBox="0 0 24 24">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    <span>
      <span class="points-num">${formattedPoints}</span>
      <span class="points-lbl">Points</span>
    </span>
  </h3>

  <hr>

  <div class="card-actions">
    <button class="rl-show-add">+ Add Points</button>

    <div id="rl-add-form" style="display:none;">
      <input type="number" id="rl-points-value" placeholder="Points (e.g. 1.90)" step="0.01" min="0" inputmode="decimal">
      <input type="text" id="rl-points-note" placeholder="Reason...">
      <button class="rl-save-points" data-id="${RL.customer.id}">Save Points</button>
    </div>
  </div>

  <hr>

  <h3 class="redeem-title">Redeem Menu</h3>
`;

  // RL.redeemItems is grouped by category — [{ category: {name,...}|null, items: [...] }]
  const groups = Array.isArray(RL.redeemItems) ? RL.redeemItems : [];
  const showCategoryLabels = groups.length > 1 || (groups[0] && groups[0].category);

  groups.forEach((group) => {
    const label = group.category ? group.category.name : "More";

    if (showCategoryLabels) {
      html += `<div class="rl-scan-menu-category-title">${escapeHtml(label)}</div>`;
    }

    group.items.forEach((item) => {
      let affordable = RL.customer.points >= item.points_cost;
      let thumb = item.image
        ? `<img src="${escapeHtml(item.image)}" alt="">`
        : `<span class="rl-scan-menu-thumb-fallback">🎁</span>`;

      html += `
    <div class="rl-scan-menu-row${affordable ? "" : " is-locked"}">
      <div class="rl-scan-menu-thumb">${thumb}</div>
      <div class="rl-scan-menu-info">
        <strong>${escapeHtml(item.title)}</strong>
        ${item.description ? `<span class="rl-scan-menu-desc">${escapeHtml(item.description)}</span>` : ""}
        <span class="rl-scan-menu-cost">${item.points_cost} pts</span>
      </div>
      <button class="rl-redeem-button" data-id="${item.id}" data-title="${escapeHtml(item.title)}" ${affordable ? "" : "disabled"}>
        ${affordable ? "Redeem" : "Locked"}
      </button>
    </div>
  `;
    });
  });

  html += `</div>`;

  container.innerHTML = html;
}

document.addEventListener("click", function (e) {
  /* NAV CENTER BUTTON CLICK */
  if (e.target.closest(".rl-nav-center")) {
    resetScanner();
    return;
  }

  /* ADD POINTS FORM TOGGLE */
  if (e.target.classList.contains("rl-show-add")) {
    document.getElementById("rl-add-form").style.display = "block";
  }

  /* SAVE ADD POINTS */
  if (e.target.classList.contains("rl-save-points") && !e.target.disabled) {
    let button = e.target;
    let customer = button.dataset.id;
    let pointsInput = document.getElementById("rl-points-value").value;
    let points = parseFloat(pointsInput);
    let note = document.getElementById("rl-points-note").value;

    // Validate positive decimal number
    if (isNaN(points) || points <= 0) {
      showToast("Please enter a valid positive points amount.", "error");
      return;
    }

    // Disabled for the duration of the request so a double-tap can't
    // fire two Add Points calls for the same entry.
    button.disabled = true;

    fetch(RL_AJAX.ajax_url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "action=rl_add_points" +
        "&customer_id=" + customer +
        "&points=" + points +
        "&note=" + encodeURIComponent(note) +
        "&location_id=" + encodeURIComponent(currentLocationId()) +
        "&nonce=" + RL_AJAX.nonce
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          showToast(data.message || "Points added successfully!", "success");
          RL.customer.points = data.points;
          renderCustomer();
        } else {
          showToast(data.message || "Failed to add points.", "error");
          button.disabled = false;
        }
      })
      .catch(() => {
        showToast("Network error — please try again.", "error");
        button.disabled = false;
      });
  }

  /* REDEEM ITEM WITH CUSTOM MODAL */
  if (e.target.classList.contains("rl-redeem-button") && !e.target.disabled) {
    let button = e.target;
    let item = button.dataset.id;
    let itemTitle = button.dataset.title || "this reward";

    showConfirmModal(
      "Confirm Redemption",
      `Are you sure you want to redeem "${itemTitle}" for ${RL.customer.name}?`,
      function () {
        // Disabled for the duration of the request so a double-tap
        // (or a fast re-open-and-confirm) can't fire two redeem
        // calls for the same item before the first one returns.
        button.disabled = true;

        fetch(RL_AJAX.ajax_url, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body:
            "action=rl_redeem_item" +
            "&customer_id=" + RL.customer.id +
            "&item_id=" + item +
            "&location_id=" + encodeURIComponent(currentLocationId()) +
            "&nonce=" + RL_AJAX.nonce
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              showToast(data.message || "Item redeemed successfully!", "success");
              RL.customer.points = data.points;
              renderCustomer();
            } else {
              showToast(data.message || "Redemption failed.", "error");
              button.disabled = false;
            }
          })
          .catch(() => {
            showToast("Network error — please try again.", "error");
            button.disabled = false;
          });
      }
    );
  }
});
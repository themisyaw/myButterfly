document.addEventListener("DOMContentLoaded", function () {

    /* ===========================
    CUSTOMER'S OWN QR CODE
    Rendered entirely client-side (via the qrcodejs library) from the
    token already embedded in the page — nothing is sent to any
    third-party service to generate this, unlike an <img> pointed at
    an external QR image API.
    =========================== */

    var container = document.getElementById("rl-qr-code-container");

    if (!container) {
        return;
    }

    var token = container.dataset.token;

    if (!token || typeof QRCode === "undefined") {
        return;
    }

    new QRCode(container, {
        text: token,
        width: 240,
        height: 240,
        colorDark: "#111827",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
    });

});

document.addEventListener(
"DOMContentLoaded",
function(){


/* ===========================
TAB SWITCHING
=========================== */


const tabs = document.querySelectorAll(
    ".rl-nav-item, .rl-nav-center"
);


/* ===========================
DEEP-LINK VIA #hash
Standalone pages outside the tabbed SPA (e.g. a restaurant's own
page) link their nav buttons back here as plain URLs — a QR button
there points at "/#qr". On load, honor that hash and switch straight
to the matching tab instead of always landing on Home.
=========================== */

if(window.location.hash){

    let hashTab = window.location.hash.replace("#", "");

    let hashTarget = document.getElementById("rl-" + hashTab + "-tab");

    if(hashTarget){

        document
        .querySelectorAll(".rl-tab")
        .forEach(function(section){
            section.classList.remove("active");
        });

        hashTarget.classList.add("active");

        document
        .querySelectorAll(".rl-nav-item")
        .forEach(function(item){
            item.classList.remove("active");

            if(item.dataset.tab === hashTab){
                item.classList.add("active");
            }
        });

    }

}


tabs.forEach(function(button){


    button.addEventListener(
    "click",
    function(){


        let tab = this.dataset.tab;


        document
        .querySelectorAll(".rl-tab")
        .forEach(function(section){

            section.classList.remove("active");

        });



        let active = document.getElementById(
            "rl-" + tab + "-tab"
        );


        if(active){

            active.classList.add("active");

        }



        document
        .querySelectorAll(".rl-nav-item")
        .forEach(function(item){

            item.classList.remove("active");

        });



        if(this.classList.contains("rl-nav-item")){

            this.classList.add("active");

        }


        if(tab === "home"){

            refreshHomePoints();

        }


    });

});



/* ===========================
HOME TAB — REFRESH POINTS ON RETURN
A staff member can add points to this customer while this exact
page/tab is already open (it happened while they were on the QR tab
having their code scanned, or just backgrounded) — nothing here
re-fetches on its own otherwise, since tab-switching above is purely
client-side and every tab's content was rendered once at page load.
Re-fetches just the raw point counts (not full markup — the Home
tab's cards carry real business logic, next-reward targets, progress
bars, "ready to redeem" chips, that isn't worth re-implementing here
a second time) and patches them into the header total + each
restaurant card's badge, whenever the customer taps back to Home or
the app regains focus while already there.
=========================== */


let rlHomeRefreshInFlight = false;
let rlHomeRefreshLastRun = 0;
const RL_HOME_REFRESH_MIN_INTERVAL_MS = 3000;

function refreshHomePoints(){

    if(typeof RL_HOME_REFRESH === "undefined") return;

    if(rlHomeRefreshInFlight) return;

    let now = Date.now();

    if(now - rlHomeRefreshLastRun < RL_HOME_REFRESH_MIN_INTERVAL_MS) return;

    rlHomeRefreshLastRun = now;
    rlHomeRefreshInFlight = true;

    fetch(RL_HOME_REFRESH.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body:
            "action=rl_customer_points_summary" +
            "&nonce=" + encodeURIComponent(RL_HOME_REFRESH.nonce)
    })
    .then(function(response){ return response.json(); })
    .then(function(data){

        if(!data || !data.success) return;

        let headerPointsNumber = document.querySelector(".rl-header-points .points-number");

        if(headerPointsNumber){

            let parts = Number(data.total_points).toFixed(2).split(".");
            headerPointsNumber.innerHTML = parts[0] + ".<small>" + parts[1] + "</small>";

        }

        (data.wallets || []).forEach(function(wallet){

            let badge = document.querySelector(
                '.rl-reward-price[data-scope-type="' + wallet.scope_type + '"][data-scope-id="' + wallet.scope_id + '"]'
            );

            if(badge){

                let points = Number(wallet.points);
                let display = Number.isInteger(points) ? points : points.toFixed(2);
                badge.textContent = "⭐ " + display;

            }

        });

        let quickActionCount = document.getElementById("rl-quick-action-entries-count");
        let activeEntries = Number(data.active_entries || 0);

        if(quickActionCount){

            if(activeEntries > 0){

                quickActionCount.textContent = activeEntries;
                quickActionCount.style.display = "";

            } else {

                quickActionCount.style.display = "none";

            }

        }

    })
    .catch(function(){
        // Silent — this is a background convenience refresh, not
        // something worth surfacing an error toast for. Worst case,
        // the number stays as it was until the next tap/focus.
    })
    .finally(function(){
        rlHomeRefreshInFlight = false;
    });

}

document.addEventListener("visibilitychange", function(){

    if(document.visibilityState !== "visible") return;

    let homeTab = document.getElementById("rl-home-tab");

    if(homeTab && homeTab.classList.contains("active")){

        refreshHomePoints();

    }

});





/* ===========================
MORE MENU
=========================== */


// Target both the original menu button and the restaurant dashboard button
const menuBtns = document.querySelectorAll(
    "#rl-more-btn, #rl-more-restaurant-dash-btn"
);


const sheet = document.querySelector(
    ".rl-menu-sheet"
);


const overlay = document.querySelector(
    ".rl-menu-overlay"
);



function closeMenu(){

    if(sheet){

        sheet.classList.remove("active");

    }


    if(overlay){

        overlay.classList.remove("active");

    }

}



if(menuBtns.length > 0){

    menuBtns.forEach(function(btn){

        btn.addEventListener(
        "click",
        function(e){

            e.preventDefault();

            const isOpen = sheet && sheet.classList.contains("active");


            if(isOpen){

                closeMenu();

            }
            else if(sheet && overlay){

                sheet.classList.add("active");

                overlay.classList.add("active");

            }


        });

    });

}




if(overlay){


    overlay.addEventListener(
    "click",
    function(){

        closeMenu();

    });


}





/* ===========================
ACTIVITY FROM MENU
=========================== */


const menuTabs = document.querySelectorAll(
    ".rl-sheet-item[data-tab], .rl-quick-action[data-tab], .rl-home-activity-see-all[data-tab]"
);



menuTabs.forEach(function(button){


    button.addEventListener(
    "click",
    function(e){

        e.preventDefault();

        let tab = this.dataset.tab;



        document
        .querySelectorAll(".rl-tab")
        .forEach(function(section){

            section.classList.remove("active");

        });



        let active = document.getElementById(
            "rl-" + tab + "-tab"
        );



        if(active){

            active.classList.add("active");

        }



        closeMenu();


    });


});



/* ===========================
MY ENTRIES - ACTIVE / ENDED SWITCH
=========================== */


const entriesChips = document.querySelectorAll(
    "#rl-entries-filters .rl-filter-chip"
);


const activeEntriesView = document.getElementById("rl-entries-active-view");
const endedEntriesView = document.getElementById("rl-entries-ended-view");


entriesChips.forEach(function(chip){

    chip.addEventListener(
    "click",
    function(){

        entriesChips.forEach(function(c){
            c.classList.remove("active");
        });

        chip.classList.add("active");

        if(chip.dataset.view === "ended"){

            if(activeEntriesView) activeEntriesView.style.display = "none";
            if(endedEntriesView) endedEntriesView.style.display = "block";

        }
        else{

            if(endedEntriesView) endedEntriesView.style.display = "none";
            if(activeEntriesView) activeEntriesView.style.display = "block";

        }

    });

});




/* ===========================
INVITE FRIENDS — COPY LINK
=========================== */


const copyBtn = document.getElementById("rl-invite-copy-btn");


if(copyBtn){

    copyBtn.addEventListener(
    "click",
    function(){


        const link = copyBtn.dataset.link;

        const hint = document.getElementById("rl-invite-hint");


        function showCopied(){

            if(hint){

                hint.textContent = copyBtn.dataset.copiedText || "Link copied — go ahead and share it.";

            }

        }


        if(navigator.clipboard && navigator.clipboard.writeText){

            navigator.clipboard.writeText(link).then(showCopied);

        }
        else{

            const temp = document.createElement("textarea");

            temp.value = link;

            document.body.appendChild(temp);

            temp.select();

            document.execCommand("copy");

            document.body.removeChild(temp);

            showCopied();

        }


    });

}


});
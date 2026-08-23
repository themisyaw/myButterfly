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


    });

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
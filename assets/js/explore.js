document.addEventListener("DOMContentLoaded", function () {
  function escapeHtml(str) {
    return String(str == null ? "" : str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  // Straight-line distance between two coordinates, in kilometers.
  function haversineKm(lat1, lng1, lat2, lng2) {
    var R = 6371;
    var dLat = ((lat2 - lat1) * Math.PI) / 180;
    var dLng = ((lng2 - lng1) * Math.PI) / 180;
    var a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLng / 2) *
        Math.sin(dLng / 2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
  }

  function formatDistanceKm(km) {
    if (km < 1) {
      return Math.round(km * 1000) + " m away";
    }
    return km.toFixed(1) + " km away";
  }

  // Shared browser-geolocation lookup — several widgets on the page
  // (prize card distances, the restaurant page's own distance line)
  // all want "where is the user right now", so this asks the browser
  // exactly once per page load and hands the result to every caller
  // that registered before it resolved. Since this runs fresh on
  // every page load/navigation, distances are naturally recalculated
  // whenever the user's location has changed and they open a
  // restaurant or refresh the page — there's no stale cross-page
  // caching, just the browser's own short-lived position cache.
  var geoRequested = false;
  var geoSettled = false;
  var geoCallbacks = [];
  var geoErrorCallbacks = [];

  // Once resolved, this holds { lat, lng } for the rest of the page's
  // life — lets anything built *after* the browser answers (e.g. a
  // map popup opened later) pick up the user's location immediately
  // instead of only reacting to the original callback.
  var knownUserLocation = null;

  // onError (optional) lets a caller with a "loading…" placeholder
  // clean itself up (e.g. hide a spinner) if location access is
  // denied or unavailable, instead of spinning forever.
  function withUserLocation(callback, onError) {
    if (knownUserLocation) {
      callback(knownUserLocation.lat, knownUserLocation.lng);
      return;
    }

    if (geoSettled) {
      // Already tried and failed — tell this caller right away
      // rather than leaving it stuck on its loading state.
      if (onError) onError();
      return;
    }

    geoCallbacks.push(callback);

    if (onError) {
      geoErrorCallbacks.push(onError);
    }

    if (geoRequested) {
      return;
    }

    geoRequested = true;

    if (!navigator.geolocation) {
      geoSettled = true;
      geoErrorCallbacks.forEach(function (cb) {
        cb();
      });
      geoErrorCallbacks = [];
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        var lat = pos.coords.latitude;
        var lng = pos.coords.longitude;

        geoSettled = true;
        knownUserLocation = { lat: lat, lng: lng };

        geoCallbacks.forEach(function (cb) {
          cb(lat, lng);
        });

        geoCallbacks = [];
        geoErrorCallbacks = [];
      },
      function () {
        // Denied or unavailable — let every waiting caller know so
        // they can drop their loading state instead of spinning
        // forever; server-rendered defaults (e.g. map bounds) stay.
        geoSettled = true;

        geoErrorCallbacks.forEach(function (cb) {
          cb();
        });

        geoCallbacks = [];
        geoErrorCallbacks = [];
      },
      { timeout: 8000, maximumAge: 300000 }
    );
  }

  // Every map marker uses the butterfly logo as its icon. Locations
  // with an active prize running right now get a small gift badge
  // layered on top so they still stand out from the plain markers.
  var logoUrl = typeof RL_EXPLORE !== "undefined" ? RL_EXPLORE.logoUrl : "";

  var butterflyIcon =
    typeof L !== "undefined" && logoUrl
      ? L.divIcon({
          className: "rl-map-butterfly-marker",
          html:
            '<div class="rl-map-butterfly-marker-inner"><img src="' +
            escapeHtml(logoUrl) +
            '" alt=""></div>',
          iconSize: [36, 36],
          iconAnchor: [18, 36],
          popupAnchor: [0, -32]
        })
      : null;

  var prizeIcon =
    typeof L !== "undefined" && logoUrl
      ? L.divIcon({
          className: "rl-map-butterfly-marker rl-map-butterfly-marker-prize",
          html:
            '<div class="rl-map-butterfly-marker-inner"><img src="' +
            escapeHtml(logoUrl) +
            '" alt=""><span class="rl-map-prize-dot">🎁</span></div>',
          iconSize: [36, 36],
          iconAnchor: [18, 36],
          popupAnchor: [0, -32]
        })
      : null;

  /**
   * Builds an independent list/map toggle "section controller" for a
   * given set of DOM ids. Two of these run on the same page at once
   * (Restaurants tab + Prizes tab), each with its own Leaflet map
   * instance and its own marker registry, so they need to be fully
   * isolated from one another rather than sharing globals.
   *
   * @param {Object} opts
   * @param {string} opts.filtersId   id of the .rl-filter-chips container
   * @param {string} opts.listViewId  id of the list view wrapper
   * @param {string} opts.mapViewId   id of the map view wrapper
   * @param {string} opts.mapId       id of the element Leaflet mounts into
   * @param {function} opts.getLocations returns the array of locations
   *   (each needs id/name/brand/lat/lng/prizes) to plot on this map
   * @param {string} opts.emptyMessage shown when getLocations() is empty
   */
  function createMapSection(opts) {
    var filters = document.getElementById(opts.filtersId);

    if (!filters) {
      return null;
    }

    var chips = filters.querySelectorAll(".rl-filter-chip");
    var listView = document.getElementById(opts.listViewId);
    var mapView = document.getElementById(opts.mapViewId);
    var mapEl = document.getElementById(opts.mapId);

    var searchInput = opts.searchInputId ? document.getElementById(opts.searchInputId) : null;

    var state = {
      map: null,
      mapInitialized: false,
      markersByLocationId: {},
      locationsById: {},
      userMarker: null,
      pendingUserLocation: null,
      currentListFilter: "list",
      searchQuery: ""
    };

    var userIcon =
      typeof L !== "undefined"
        ? L.divIcon({
            className: "rl-map-user-marker",
            html: '<div class="rl-map-user-marker-inner"></div>',
            iconSize: [20, 20],
            iconAnchor: [10, 10]
          })
        : null;

    // Drops (or moves) a "you are here" pin. Safe to call before the
    // map exists yet — the location is remembered and applied as
    // soon as initMap() actually builds the map. recenter=true also
    // pans/zooms the map to the user, which is what a "near me" map
    // should do the moment it learns where "me" is — but only for a
    // live update, not the very first build (initMap()'s own
    // fitBounds already framed the user alongside the pins there).
    function setUserLocation(lat, lng, recenter) {
      if (!state.map) {
        state.pendingUserLocation = { lat: lat, lng: lng };
        return;
      }

      if (state.userMarker) {
        state.userMarker.setLatLng([lat, lng]);
        return;
      }

      state.userMarker = L.marker([lat, lng], {
        icon: userIcon,
        zIndexOffset: 1000
      })
        .addTo(state.map)
        .bindPopup("You are here");

      if (recenter) {
        state.map.setView([lat, lng], 13);
      }
    }

    // Rebuilds every open popup's content so it includes (or updates)
    // the distance line, once the user's location becomes known —
    // covers the case where a map was opened before geolocation
    // resolved, so its popups were first built without a distance.
    function refreshPopupDistances(lat, lng) {
      Object.keys(state.markersByLocationId).forEach(function (id) {
        var marker = state.markersByLocationId[id];
        var loc = state.locationsById[id];

        if (marker && loc) {
          marker.setPopupContent(buildPopupHtml(loc, { lat: lat, lng: lng }));
        }
      });
    }

    function buildPopupHtml(loc, userLoc) {
      // A standalone restaurant's brand name and its one location's
      // name are the same string — only show the brand as a separate
      // line when it actually differs (a real chain), otherwise this
      // would just print the restaurant's name twice.
      var html =
        loc.brand && loc.brand !== loc.name
          ? "<strong>" + escapeHtml(loc.brand) + "</strong><br>" + escapeHtml(loc.name)
          : "<strong>" + escapeHtml(loc.name) + "</strong>";

      if (userLoc) {
        var km = haversineKm(userLoc.lat, userLoc.lng, loc.lat, loc.lng);
        html +=
          '<div class="rl-popup-distance">📍 ' + formatDistanceKm(km) + "</div>";
      }

      if (loc.prizes && loc.prizes.length) {
        html += '<div class="rl-map-prize-tags">';

        loc.prizes.forEach(function (title) {
          html +=
            '<div class="rl-map-prize-tag">' +
            '<span class="rl-map-prize-tag-badge">Open</span>' +
            '<span class="rl-map-prize-tag-name">🎁 ' +
            escapeHtml(title) +
            "</span></div>";
        });

        html += "</div>";
      }

      return html;
    }

    function initMap() {
      if (!mapEl || typeof L === "undefined" || typeof RL_EXPLORE === "undefined") {
        return;
      }

      var locations = opts.getLocations();

      if (!locations.length) {
        mapEl.innerHTML =
          '<p class="rl-explore-map-empty">' +
          escapeHtml(opts.emptyMessage || "No locations have a map pin yet.") +
          "</p>";
        return;
      }

      state.map = L.map(mapEl, { scrollWheelZoom: false });

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19
      }).addTo(state.map);

      var bounds = [];

      if (state.pendingUserLocation) {
        bounds.push([state.pendingUserLocation.lat, state.pendingUserLocation.lng]);
      }

      var userLocAtInit = state.pendingUserLocation || knownUserLocation;

      locations.forEach(function (loc) {
        var hasPrizes = !!(loc.prizes && loc.prizes.length);

        var icon = hasPrizes && prizeIcon ? prizeIcon : butterflyIcon;

        var marker = L.marker(
          [loc.lat, loc.lng],
          icon ? { icon: icon } : undefined
        ).addTo(state.map);

        marker.bindPopup(buildPopupHtml(loc, userLocAtInit));

        if (loc.id) {
          state.markersByLocationId[loc.id] = marker;
          state.locationsById[loc.id] = loc;
        }

        bounds.push([loc.lat, loc.lng]);
      });

      if (bounds.length === 1) {
        state.map.setView(bounds[0], 14);
      } else {
        state.map.fitBounds(bounds, { padding: [30, 30] });
      }

      if (state.pendingUserLocation) {
        var pending = state.pendingUserLocation;
        state.pendingUserLocation = null;
        // No recenter here — fitBounds()/setView() above already
        // framed the user alongside the pins.
        setUserLocation(pending.lat, pending.lng, false);
      }
    }

    function showMapView() {
      chips.forEach(function (c) {
        c.classList.remove("active");
      });

      var mapChip = filters.querySelector('.rl-filter-chip[data-view="map"]');

      if (mapChip) {
        mapChip.classList.add("active");
      }

      if (listView) listView.style.display = "none";
      if (mapView) mapView.style.display = "block";

      if (!state.mapInitialized) {
        initMap();
        state.mapInitialized = true;
      } else if (state.map) {
        state.map.invalidateSize();
      }
    }

    // "All" vs "Visited" both use the same list view — only the
    // filter changes which cards inside it are visible. Cards without
    // a data-visited attribute (e.g. on the Prizes tab, which has no
    // such chip) are unaffected by the visited check either way. The
    // search box (Restaurants tab only) layers on top of whichever
    // chip is active rather than replacing it, so typing a name while
    // "Visited" is selected still only searches within visited places.
    function applyListFilter(filter) {
      if (!listView) {
        return;
      }

      state.currentListFilter = filter;

      var query = state.searchQuery;

      listView.querySelectorAll(".rl-reward-item-card").forEach(function (card) {
        var passesVisited =
          filter !== "visited" || !card.hasAttribute("data-visited") || card.dataset.visited === "1";

        var passesSearch = !query || (card.dataset.search || "").indexOf(query) !== -1;

        card.style.display = passesVisited && passesSearch ? "" : "none";
      });
    }

    if (searchInput) {
      searchInput.addEventListener("input", function () {
        state.searchQuery = searchInput.value.trim().toLowerCase();
        applyListFilter(state.currentListFilter);
      });
    }

    function showListView(clickedChip) {
      chips.forEach(function (c) {
        c.classList.remove("active");
      });

      if (clickedChip) {
        clickedChip.classList.add("active");
      }

      if (mapView) mapView.style.display = "none";
      if (listView) listView.style.display = "block";

      applyListFilter(clickedChip ? clickedChip.dataset.view : "list");
    }

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        if (chip.dataset.view === "map") {
          showMapView();
        } else {
          showListView(chip);
        }
      });
    });

    return {
      state: state,
      showMapView: showMapView,
      setUserLocation: setUserLocation,
      refreshPopupDistances: refreshPopupDistances,
      invalidateSize: function () {
        if (state.map) {
          state.map.invalidateSize();
        }
      }
    };
  }

  // Restaurants tab: every public location.
  var restaurantsSectionCtrl = createMapSection({
    filtersId: "rl-explore-filters",
    listViewId: "rl-explore-list-view",
    mapViewId: "rl-explore-map-view",
    mapId: "rl-explore-map",
    searchInputId: "rl-explore-search",
    emptyMessage: "No restaurant locations have a map pin yet.",
    getLocations: function () {
      return (typeof RL_EXPLORE !== "undefined" && RL_EXPLORE.locations) || [];
    }
  });

  // Prizes tab: only locations with an active prize running right now.
  var prizesSectionCtrl = createMapSection({
    filtersId: "rl-prizes-filters",
    listViewId: "rl-prizes-list-view",
    mapViewId: "rl-prizes-map-view",
    mapId: "rl-prizes-map",
    emptyMessage: "No active prizes have a map pin yet.",
    getLocations: function () {
      var all = (typeof RL_EXPLORE !== "undefined" && RL_EXPLORE.locations) || [];
      return all.filter(function (loc) {
        return loc.prizes && loc.prizes.length;
      });
    }
  });

  // Drop a "you are here" pin on both maps once the browser hands
  // back a location — works whether a given map has been opened
  // (and therefore built) yet or not, via setUserLocation()'s own
  // pending-location handling. Also refreshes any already-open
  // popups so they pick up a distance line.
  if (restaurantsSectionCtrl || prizesSectionCtrl) {
    withUserLocation(function (userLat, userLng) {
      if (restaurantsSectionCtrl) {
        restaurantsSectionCtrl.setUserLocation(userLat, userLng, true);
        restaurantsSectionCtrl.refreshPopupDistances(userLat, userLng);
      }

      if (prizesSectionCtrl) {
        prizesSectionCtrl.setUserLocation(userLat, userLng, true);
        prizesSectionCtrl.refreshPopupDistances(userLat, userLng);
      }
    });
  }

  // Restaurants tab: once we know where the user is, reorder the
  // "All" list so the closest restaurants show up first. Cards
  // without a map pin (no lat/lng to measure from) are left at the
  // end, in their original order, rather than guessed at.
  var explorelistView = document.getElementById("rl-explore-list-view");

  if (explorelistView) {
    withUserLocation(function (userLat, userLng) {
      var cards = Array.prototype.slice.call(
        explorelistView.querySelectorAll(".rl-reward-item-card")
      );

      cards.sort(function (a, b) {
        var aLat = parseFloat(a.dataset.lat);
        var aLng = parseFloat(a.dataset.lng);
        var bLat = parseFloat(b.dataset.lat);
        var bLng = parseFloat(b.dataset.lng);

        var aHasPin = !isNaN(aLat) && !isNaN(aLng);
        var bHasPin = !isNaN(bLat) && !isNaN(bLng);

        if (!aHasPin && !bHasPin) return 0;
        if (!aHasPin) return 1;
        if (!bHasPin) return -1;

        return (
          haversineKm(userLat, userLng, aLat, aLng) -
          haversineKm(userLat, userLng, bLat, bLng)
        );
      });

      cards.forEach(function (card) {
        explorelistView.appendChild(card);
      });
    });
  }

  // Prizes tab: how far away each prize is. Asks for the browser's
  // location once; if the user allows it, works out the nearest
  // location each prize applies to and shows the distance on its
  // card, also re-pointing that card's "view on map" button at
  // whichever pin turned out to be closest. If location access is
  // denied or unavailable, the distance line just stays hidden and
  // the map button keeps its server-rendered default location.
  var prizeMapButtons = document.querySelectorAll(".rl-prize-view-on-map-btn[data-locations]");

  // Fills in a distance element's number only — the icon and layout
  // around it were already rendered server-side with a spinner in
  // place of the figure, so nothing shifts or pops in once the real
  // number is ready; only that one span's content changes.
  function setDistanceValue(el, km) {
    var valueEl = el.querySelector(".rl-distance-value");
    (valueEl || el).textContent = formatDistanceKm(km);
  }

  // If location access is denied/unavailable, drop the whole element
  // rather than leave its spinner turning forever.
  function hideDistanceEl(el) {
    el.style.display = "none";
  }

  if (prizeMapButtons.length) {
    withUserLocation(
      function (userLat, userLng) {
        prizeMapButtons.forEach(function (btn) {
          var locations;

          try {
            locations = JSON.parse(btn.dataset.locations || "[]");
          } catch (e) {
            locations = [];
          }

          var card = btn.closest(".rl-prize-card");
          var distEl = card ? card.querySelector(".rl-prize-card-distance") : null;

          if (!locations.length) {
            if (distEl) hideDistanceEl(distEl);
            return;
          }

          var nearest = null;
          var nearestKm = Infinity;

          locations.forEach(function (loc) {
            var km = haversineKm(userLat, userLng, loc.lat, loc.lng);

            if (km < nearestKm) {
              nearestKm = km;
              nearest = loc;
            }
          });

          if (!nearest) {
            if (distEl) hideDistanceEl(distEl);
            return;
          }

          btn.dataset.locationId = nearest.id;
          btn.dataset.lat = nearest.lat;
          btn.dataset.lng = nearest.lng;

          if (distEl) {
            setDistanceValue(distEl, nearestKm);
          }
        });
      },
      function () {
        prizeMapButtons.forEach(function (btn) {
          var card = btn.closest(".rl-prize-card");
          var distEl = card ? card.querySelector(".rl-prize-card-distance") : null;
          if (distEl) hideDistanceEl(distEl);
        });
      }
    );
  }

  // Restaurant page: distance from the user to this specific
  // restaurant. Same shared geolocation lookup as the prize cards.
  // This element doubles as the "Get Directions" link, so unlike
  // hideDistanceEl() elsewhere, a missing/denied location only hides
  // the distance figure — the directions link itself must stay
  // usable either way, since opening Google Maps doesn't depend on
  // this page ever having known where the visitor is.
  var restaurantDistanceEls = document.querySelectorAll(".rl-restaurant-distance[data-lat]");

  function hideDistanceFigureOnly(el) {
    var icon = el.querySelector(".rl-distance-icon");
    var value = el.querySelector(".rl-distance-value");
    if (icon) icon.style.display = "none";
    if (value) value.style.display = "none";
  }

  if (restaurantDistanceEls.length) {
    withUserLocation(
      function (userLat, userLng) {
        restaurantDistanceEls.forEach(function (el) {
          var lat = parseFloat(el.dataset.lat);
          var lng = parseFloat(el.dataset.lng);

          if (isNaN(lat) || isNaN(lng)) {
            hideDistanceFigureOnly(el);
            return;
          }

          setDistanceValue(el, haversineKm(userLat, userLng, lat, lng));
        });
      },
      function () {
        restaurantDistanceEls.forEach(hideDistanceFigureOnly);
      }
    );
  }

  // Restaurant page: a small single-pin map for this one location —
  // no list/map toggle needed since there's only ever one place to
  // show here, unlike the Explore/Prizes tabs' createMapSection().
  var singleMapEl = document.getElementById("rl-restaurant-single-map");

  if (singleMapEl && typeof L !== "undefined") {
    var singleLat = parseFloat(singleMapEl.dataset.lat);
    var singleLng = parseFloat(singleMapEl.dataset.lng);

    if (!isNaN(singleLat) && !isNaN(singleLng)) {
      var singleMap = L.map(singleMapEl, { scrollWheelZoom: false }).setView(
        [singleLat, singleLng],
        15
      );

      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19
      }).addTo(singleMap);

      L.marker([singleLat, singleLng], butterflyIcon ? { icon: butterflyIcon } : undefined)
        .addTo(singleMap)
        .bindPopup(escapeHtml(singleMapEl.dataset.name || ""));
    }
  }

  // Restaurant page: menu category tabs — "All" plus one per
  // category, toggling which .rl-menu-category-group sections show.
  var menuTabsEl = document.getElementById("rl-menu-category-tabs");

  if (menuTabsEl) {
    var menuChips = menuTabsEl.querySelectorAll(".rl-filter-chip");
    var menuGroups = document.querySelectorAll(".rl-menu-category-group");

    menuChips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        menuChips.forEach(function (c) {
          c.classList.remove("active");
        });
        chip.classList.add("active");

        var target = chip.dataset.category;

        menuGroups.forEach(function (group) {
          group.style.display =
            target === "all" || group.dataset.category === target ? "block" : "none";
        });
      });
    });
  }

  // "View on map" button on each prize card: switches to the Prizes
  // tab's own map sub-view and zooms to whichever location that
  // button currently targets (nearest one, once geolocation resolves).
  prizeMapButtons.forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      e.preventDefault();

      if (!prizesSectionCtrl) {
        return;
      }

      var locationId = btn.dataset.locationId;
      var lat = parseFloat(btn.dataset.lat);
      var lng = parseFloat(btn.dataset.lng);

      prizesSectionCtrl.showMapView();

      window.setTimeout(function () {
        var map = prizesSectionCtrl.state.map;

        if (!map) {
          return;
        }

        var marker = prizesSectionCtrl.state.markersByLocationId[locationId];

        if (marker) {
          map.setView(marker.getLatLng(), 17);
          marker.openPopup();
        } else if (!isNaN(lat) && !isNaN(lng)) {
          map.setView([lat, lng], 17);
        }
      }, 60);
    });
  });

  // The same restaurant card design (and its "view on map" button)
  // also appears on the Home tab, inside a different SPA tab section
  // than the Restaurants tab's own map. Switching that map's sub-view
  // to "map" only toggles visibility *within* the Restaurants tab's
  // own container — it does nothing if that whole tab isn't the one
  // currently showing. So make sure the Restaurants tab is active
  // first, the same way the "more" menu's Restaurants item does.
  function activateRestaurantsTab() {
    var restaurantsTab = document.getElementById("rl-restaurants-tab");

    if (!restaurantsTab || restaurantsTab.classList.contains("active")) {
      return;
    }

    document.querySelectorAll(".rl-tab").forEach(function (section) {
      section.classList.remove("active");
    });

    restaurantsTab.classList.add("active");

    document.querySelectorAll(".rl-nav-item").forEach(function (item) {
      item.classList.remove("active");
    });
  }

  // "View on map" button on each restaurant list card: switches to
  // the Restaurants tab's map sub-view (if not already there) and
  // zooms straight to that location's pin, opening its popup.
  document.querySelectorAll(".rl-view-on-map-btn").forEach(function (btn) {
    btn.addEventListener("click", function (e) {
      // The button now sits on top of the card's header photo, which
      // is itself inside the card's <a> link — stop the click from
      // also triggering that link's navigation.
      e.preventDefault();
      e.stopPropagation();

      if (!restaurantsSectionCtrl) {
        return;
      }

      var locationId = btn.dataset.locationId;
      var lat = parseFloat(btn.dataset.lat);
      var lng = parseFloat(btn.dataset.lng);

      activateRestaurantsTab();
      restaurantsSectionCtrl.showMapView();

      // A short delay lets the map container finish becoming visible
      // (and initMap()/invalidateSize() finish sizing it) before we
      // pan — Leaflet can't reliably set the view of a container that
      // was display:none a moment ago.
      window.setTimeout(function () {
        var map = restaurantsSectionCtrl.state.map;

        if (!map) {
          return;
        }

        var marker = restaurantsSectionCtrl.state.markersByLocationId[locationId];

        if (marker) {
          map.setView(marker.getLatLng(), 17);
          marker.openPopup();
        } else if (!isNaN(lat) && !isNaN(lng)) {
          map.setView([lat, lng], 17);
        }
      }, 60);
    });
  });

  // Guest /explore page only: top-level toggle between the Prizes
  // and Restaurants sections. Re-sizes whichever section's map is
  // currently showing since Leaflet can't measure itself while its
  // container was hidden.
  var sectionChips = document.querySelectorAll(
    "#rl-explore-section-tabs .rl-filter-chip"
  );
  var prizesSection = document.getElementById("rl-explore-prizes-section");
  var restaurantsSection = document.getElementById(
    "rl-explore-restaurants-section"
  );

  sectionChips.forEach(function (chip) {
    chip.addEventListener("click", function () {
      sectionChips.forEach(function (c) {
        c.classList.remove("active");
      });
      chip.classList.add("active");

      var section = chip.dataset.section;

      if (section === "restaurants") {
        if (prizesSection) prizesSection.style.display = "none";
        if (restaurantsSection) restaurantsSection.style.display = "block";

        if (restaurantsSectionCtrl) {
          restaurantsSectionCtrl.invalidateSize();
        }
      } else {
        if (restaurantsSection) restaurantsSection.style.display = "none";
        if (prizesSection) prizesSection.style.display = "block";

        if (prizesSectionCtrl) {
          prizesSectionCtrl.invalidateSize();
        }
      }
    });
  });
});

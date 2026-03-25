/**
 * sidebar-accordion.js
 *
 * Accordion behaviour for the nav sidebar.
 * - Uses native <details> elements — no custom state needed.
 * - Closes all other open <details> when one opens (one-open-at-a-time).
 * - Keeps the active group open on page load based on .is-active links.
 * - Safe to call multiple times (uses a guard flag).
 *
 * Usage: place this script at the bottom of <body>, or call
 *   initSidebarAccordion() after the DOM is ready.
 */

(function () {
  'use strict';

  function initSidebarAccordion() {
    const nav = document.getElementById('layoutNav');
    if (!nav || nav._accordionInit) return;
    nav._accordionInit = true;

    const allDetails = Array.from(nav.querySelectorAll('.nav-details'));

    /* ── Close all siblings when one opens ───────────── */
    allDetails.forEach(function (det) {
      det.addEventListener('toggle', function () {
        if (!det.open) return;            // only act on open events
        allDetails.forEach(function (other) {
          if (other !== det && other.open) {
            other.open = false;           // close sibling
          }
        });
      });
    });

    /* ── Open the group that contains the active link ── */
    const activeLink = nav.querySelector('.side-link.is-active');
    if (activeLink) {
      const parentDetails = activeLink.closest('.nav-details');
      if (parentDetails) {
        parentDetails.open = true;

        // Also mark the parent nav-item as has-active for CSS colour hooks
        const parentItem = parentDetails.closest('.nav-item');
        if (parentItem) parentItem.classList.add('has-active');
      }
    }
  }

  /* Auto-init when DOM is ready */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebarAccordion);
  } else {
    initSidebarAccordion();
  }

  /* Expose for manual re-init (e.g. after AJAX nav swap) */
  window.initSidebarAccordion = initSidebarAccordion;
})();
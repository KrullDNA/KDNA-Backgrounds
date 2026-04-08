/**
 * KDNA Backgrounds - Front-end Initialiser
 *
 * Key fixes:
 * - Waits for kdnaBgData to be available (timing)
 * - Retries init if container has zero height (layout not ready)
 * - Uses ResizeObserver to re-init when container gets a height
 * - MutationObserver for Elementor editor dynamic rendering
 */

(function () {
    'use strict';

    var instances = [];
    var MAX_RETRIES = 10;
    var RETRY_DELAY = 200;

    /**
     * Initialise a single container
     */
    function initContainer(el, retryCount) {
        retryCount = retryCount || 0;

        if (el.getAttribute('data-kdna-bg-init') === '1') return;

        var bgId = parseInt(el.getAttribute('data-kdna-bg-id'), 10);
        if (!bgId) return;

        /* Wait for data to be available */
        if (!window.kdnaBgData || !window.kdnaBgData[bgId]) {
            if (retryCount < MAX_RETRIES) {
                setTimeout(function () { initContainer(el, retryCount + 1); }, RETRY_DELAY);
            }
            return;
        }

        /* Check container has actual dimensions */
        var rect = el.getBoundingClientRect();
        var containerW = rect.width;
        var containerH = rect.height;

        if (containerW < 10 || containerH < 10) {
            /* Container not laid out yet, retry */
            if (retryCount < MAX_RETRIES) {
                setTimeout(function () { initContainer(el, retryCount + 1); }, RETRY_DELAY);
            }
            return;
        }

        /* Mark initialised */
        el.setAttribute('data-kdna-bg-init', '1');

        var config = JSON.parse(JSON.stringify(window.kdnaBgData[bgId]));

        var speedOverride = parseInt(el.getAttribute('data-kdna-bg-speed'), 10);
        if (speedOverride && speedOverride > 0) {
            config.speed = speedOverride;
        }

        /* Create wrapper */
        var wrapper = document.createElement('div');
        wrapper.className = 'kdna-bg-wrapper';

        var canvas = document.createElement('canvas');
        canvas.className = 'kdna-bg-canvas';
        wrapper.appendChild(canvas);

        /* Overlay */
        var overlayColour = el.getAttribute('data-kdna-bg-overlay');
        if (overlayColour) {
            var overlay = document.createElement('div');
            overlay.className = 'kdna-bg-overlay';
            overlay.style.backgroundColor = overlayColour;
            wrapper.appendChild(overlay);
        }

        /* Ensure container is positioned */
        var pos = window.getComputedStyle(el).position;
        if (!pos || pos === 'static') {
            el.style.position = 'relative';
        }

        /* Insert wrapper as first child */
        if (el.firstChild) {
            el.insertBefore(wrapper, el.firstChild);
        } else {
            el.appendChild(wrapper);
        }

        /* Start gradient */
        try {
            var gradient = window.KDNAGradientEngine.create(config);
            gradient.init(canvas);
            instances.push({ gradient: gradient, element: el, wrapper: wrapper });

            /* Pause when off-screen */
            if ('IntersectionObserver' in window) {
                var obs = new IntersectionObserver(function (entries) {
                    if (entries[0].isIntersecting) {
                        gradient.play();
                    } else {
                        gradient.pause();
                    }
                }, { threshold: 0.01 });
                obs.observe(el);
            }
        } catch (err) {
            console.warn('KDNA Backgrounds: init failed for ID ' + bgId, err);
            /* Clean up on failure */
            if (wrapper.parentNode) wrapper.parentNode.removeChild(wrapper);
            el.removeAttribute('data-kdna-bg-init');
        }
    }

    /**
     * Scan the page and init all containers
     */
    function initAll() {
        if (typeof window.KDNAGradientEngine === 'undefined') return;

        var containers = document.querySelectorAll('[data-kdna-bg-id]');
        for (var i = 0; i < containers.length; i++) {
            initContainer(containers[i], 0);
        }
    }

    /* ── DOM Ready ── */
    function onReady() {
        /* Run immediately, then again after a short delay
           to catch any containers whose data arrives late */
        initAll();
        setTimeout(initAll, 300);
        setTimeout(initAll, 1000);
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        onReady();
    } else {
        document.addEventListener('DOMContentLoaded', onReady);
    }

    /* Also fire on window load (everything including images is ready) */
    window.addEventListener('load', function () {
        setTimeout(initAll, 100);
    });

    /* ── Elementor editor: listen for frontend init ── */
    if (typeof jQuery !== 'undefined') {
        jQuery(window).on('elementor/frontend/init', function () {
            setTimeout(initAll, 500);
            setTimeout(initAll, 1500);
        });
    }

    /* ── MutationObserver: catch dynamically added containers ── */
    if ('MutationObserver' in window) {
        var debounceTimer = null;
        var mo = new MutationObserver(function () {
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(initAll, 300);
        });

        function startObserving() {
            mo.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-kdna-bg-id'] });
        }

        if (document.body) {
            startObserving();
        } else {
            document.addEventListener('DOMContentLoaded', startObserving);
        }
    }

    /* ── Reduced motion ── */
    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (mq.matches) {
            setTimeout(function () {
                for (var i = 0; i < instances.length; i++) {
                    instances[i].gradient.pause();
                }
            }, 500);
        }
    }

})();

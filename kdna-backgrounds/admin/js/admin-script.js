(function ($) {
    'use strict';

    var MAX_COLOURS = 10;
    var currentGradient = null;
    var refreshTimer = null;

    /**
     * Read every form field and return a gradient config object.
     */
    /* Read a numeric field, falling back to def only when missing/NaN
       (a literal 0 is a valid value for the shape sliders, so we cannot
       use the "|| def" shorthand here). */
    function numVal(sel, def) {
        var v = parseFloat($(sel).val());
        return isNaN(v) ? def : v;
    }

    function buildConfigFromDOM() {
        var colours = [];
        $('#kdna-bg-colour-list .kdna-bg-colour-picker').each(function () {
            var val = $(this).val();
            if (val) colours.push(val);
        });
        if (colours.length < 2) colours = ['#0a2463', '#1e6bff', '#3d8bff'];

        return {
            colours:         colours,
            speed:           parseFloat($('#kdna_bg_speed').val()) || 5,
            amplitude:       parseInt($('#kdna_bg_amplitude').val(), 10) || 320,
            density:         parseFloat($('#kdna_bg_density').val()) || 6,
            seed:            parseInt($('#kdna_bg_seed').val(), 10) || 5,
            darkenTop:       $('#kdna_bg_darken_top').is(':checked'),
            glassType:       $('#kdna_bg_glass_type').val() || 'none',
            refractStrength: numVal('#kdna_bg_refract_strength', 0),
            refractScale:    numVal('#kdna_bg_refract_scale', 12),
            refractSpeed:    numVal('#kdna_bg_refract_speed', 5),
            ribCount:        numVal('#kdna_bg_rib_count', 40),
            ribAngle:        numVal('#kdna_bg_rib_angle', 90),
            ribSharp:        numVal('#kdna_bg_rib_sharp', 0),
            ribHiWidth:      numVal('#kdna_bg_rib_hi_width', 25),
            ribHiStrength:   numVal('#kdna_bg_rib_hi_strength', 40),
            ribShWidth:      numVal('#kdna_bg_rib_sh_width', 50),
            ribShStrength:   numVal('#kdna_bg_rib_sh_strength', 60),
            shapeStyle:      $('#kdna_bg_shape_style').val() || 'wash',
            dominantBg:      $('#kdna_bg_dominant_bg').is(':checked'),
            stretch:         numVal('#kdna_bg_stretch', 0),
            radiateSpeed:    numVal('#kdna_bg_radiate', 45),
            ringCount:       numVal('#kdna_bg_ring_count', 1),
            shapeCount:      numVal('#kdna_bg_shape_count', 1),
            colorBlend:      numVal('#kdna_bg_color_blend', 70),
            drift:           numVal('#kdna_bg_drift', 40),
            bandBgColor:     $('#kdna_bg_band_bg_colour').val() || '',
            bandMin:         numVal('#kdna_bg_band_min', 25),
            bandMax:         numVal('#kdna_bg_band_max', 60),
            bandVary:        numVal('#kdna_bg_band_vary', 50),
            bandMove:        numVal('#kdna_bg_band_move', 40),
            bandFade:        numVal('#kdna_bg_band_fade', 0),
            bandFadeVar:     numVal('#kdna_bg_band_fade_var', 50),
            flowAmount:      numVal('#kdna_bg_flow_amount', 0),
            flowAngle:       numVal('#kdna_bg_flow_angle', 0),
            definition:      numVal('#kdna_bg_definition', 40),
            spread:          numVal('#kdna_bg_spread', 50),
            sheen:           numVal('#kdna_bg_sheen', 0),
            grain:           numVal('#kdna_bg_grain', 0)
        };
    }

    /**
     * Show only the shape controls relevant to the selected shape style
     * (the Stretch control applies to Concentric style only).
     */
    function updateShapeVisibility() {
        var style = $('#kdna_bg_shape_style').val() || 'wash';
        $('.kdna-bg-shape-row').each(function () {
            var groups = ($(this).attr('data-shape-group') || '').split(' ');
            $(this).toggle(groups.indexOf(style) !== -1);
        });
    }

    /**
     * Show only the controls relevant to the selected glass type.
     * Each glass row declares which type(s) it belongs to via
     * data-glass-group (space separated, e.g. "liquid fluted").
     */
    function updateGlassVisibility() {
        var type = $('#kdna_bg_glass_type').val() || 'none';
        $('.kdna-bg-glass-row').each(function () {
            var groups = ($(this).attr('data-glass-group') || '').split(' ');
            $(this).toggle(type !== 'none' && groups.indexOf(type) !== -1);
        });
    }

    /**
     * Destroy the current gradient, create a fresh canvas, and render
     * a new gradient from the current form values.
     * Debounced at 200ms so colour-picker drags don't thrash the GPU.
     */
    function refreshPreview() {
        if (refreshTimer) clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function () {
            if (typeof window.KDNAGradientEngine === 'undefined') return;
            var container = document.getElementById('kdna-bg-preview-container');
            if (!container) return;

            if (currentGradient) {
                currentGradient.destroy();
                currentGradient = null;
            }

            /* Replace the canvas so the old WebGL context can be GC'd */
            var old = document.getElementById('kdna-bg-preview-canvas');
            if (old) old.parentNode.removeChild(old);

            var canvas = document.createElement('canvas');
            canvas.id = 'kdna-bg-preview-canvas';
            container.appendChild(canvas);

            var cfg = buildConfigFromDOM();
            currentGradient = window.KDNAGradientEngine.create(cfg);
            currentGradient.init(canvas);
        }, 200);
    }

    /* ── Colour pickers ── */
    function initPickers($scope) {
        $scope.find('.kdna-bg-colour-picker').each(function () {
            if (!$(this).closest('.wp-picker-container').length) {
                $(this).wpColorPicker({
                    change: function () {
                        updateNumbering();
                        refreshPreview();
                    },
                    clear: function () {
                        updateNumbering();
                        refreshPreview();
                    }
                });
            }
        });
    }

    /* ── Row numbering + add-button state ── */
    function updateNumbering() {
        var count = 0;
        $('#kdna-bg-colour-list .kdna-bg-colour-row').each(function (i) {
            $(this).find('.kdna-bg-colour-number').text(i + 1);
            $(this).attr('data-index', i);
            count++;
        });
        $('.kdna-bg-colour-count').text(count + ' / ' + MAX_COLOURS + ' colours');

        if (count >= MAX_COLOURS) {
            $('#kdna-bg-add-colour').prop('disabled', true).text('Maximum reached');
        } else {
            $('#kdna-bg-add-colour').prop('disabled', false).text('+ Add Colour');
        }
    }

    /* ── DOM Ready ── */
    $(function () {
        var $list = $('#kdna-bg-colour-list');

        initPickers($list);

        /* Single colour pickers (e.g. Bands Background Colour) live outside
           the gradient list and refresh the preview on change. */
        $('.kdna-bg-single-colour').each(function () {
            if (!$(this).closest('.wp-picker-container').length) {
                $(this).wpColorPicker({
                    change: function () { refreshPreview(); },
                    clear: function () { refreshPreview(); }
                });
            }
        });

        $list.sortable({
            handle: '.kdna-bg-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            axis: 'y',
            tolerance: 'pointer',
            update: function () {
                updateNumbering();
                refreshPreview();
            }
        });

        /* Add colour */
        $('#kdna-bg-add-colour').on('click', function () {
            var count = $list.find('.kdna-bg-colour-row').length;
            if (count >= MAX_COLOURS) return;

            var $row = $(
                '<li class="kdna-bg-colour-row">' +
                    '<span class="kdna-bg-drag-handle dashicons dashicons-menu"></span>' +
                    '<span class="kdna-bg-colour-number">' + (count + 1) + '</span>' +
                    '<input type="text" class="kdna-bg-colour-picker" name="kdna_bg_colours[]" value="#333333" />' +
                    '<button type="button" class="button kdna-bg-remove-colour" title="Remove">&times;</button>' +
                '</li>'
            );

            $list.append($row);
            initPickers($row);
            updateNumbering();
            refreshPreview();
        });

        /* Remove colour */
        $list.on('click', '.kdna-bg-remove-colour', function () {
            if ($list.find('.kdna-bg-colour-row').length <= 2) {
                alert('You need at least 2 colours for a gradient.');
                return;
            }
            $(this).closest('.kdna-bg-colour-row').remove();
            updateNumbering();
            refreshPreview();
        });

        /* Sliders: update displayed value + refresh preview */
        $('input[type="range"]').on('input', function () {
            var valMap = {
                'kdna_bg_speed': '#kdna-bg-speed-val',
                'kdna_bg_amplitude': '#kdna-bg-amp-val',
                'kdna_bg_density': '#kdna-bg-density-val',
                'kdna_bg_refract_strength': '#kdna-bg-refract-strength-val',
                'kdna_bg_refract_scale': '#kdna-bg-refract-scale-val',
                'kdna_bg_refract_speed': '#kdna-bg-refract-speed-val',
                'kdna_bg_rib_count': '#kdna-bg-rib-count-val',
                'kdna_bg_rib_angle': '#kdna-bg-rib-angle-val',
                'kdna_bg_rib_sharp': '#kdna-bg-rib-sharp-val',
                'kdna_bg_rib_hi_width': '#kdna-bg-rib-hi-width-val',
                'kdna_bg_rib_hi_strength': '#kdna-bg-rib-hi-strength-val',
                'kdna_bg_rib_sh_width': '#kdna-bg-rib-sh-width-val',
                'kdna_bg_rib_sh_strength': '#kdna-bg-rib-sh-strength-val',
                'kdna_bg_flow_amount': '#kdna-bg-flow-amount-val',
                'kdna_bg_flow_angle': '#kdna-bg-flow-angle-val',
                'kdna_bg_definition': '#kdna-bg-definition-val',
                'kdna_bg_spread': '#kdna-bg-spread-val',
                'kdna_bg_stretch': '#kdna-bg-stretch-val',
                'kdna_bg_radiate': '#kdna-bg-radiate-val',
                'kdna_bg_ring_count': '#kdna-bg-ring-count-val',
                'kdna_bg_shape_count': '#kdna-bg-shape-count-val',
                'kdna_bg_color_blend': '#kdna-bg-color-blend-val',
                'kdna_bg_drift': '#kdna-bg-drift-val',
                'kdna_bg_band_min': '#kdna-bg-band-min-val',
                'kdna_bg_band_max': '#kdna-bg-band-max-val',
                'kdna_bg_band_vary': '#kdna-bg-band-vary-val',
                'kdna_bg_band_move': '#kdna-bg-band-move-val',
                'kdna_bg_band_fade': '#kdna-bg-band-fade-val',
                'kdna_bg_band_fade_var': '#kdna-bg-band-fade-var-val',
                'kdna_bg_sheen': '#kdna-bg-sheen-val',
                'kdna_bg_grain': '#kdna-bg-grain-val'
            };
            var target = valMap[$(this).attr('id')];
            if (target) $(target).text($(this).val());
            refreshPreview();
        });

        /* Seed + darken-top + dominant background */
        $('#kdna_bg_seed').on('input', refreshPreview);
        $('#kdna_bg_darken_top').on('change', refreshPreview);
        $('#kdna_bg_dominant_bg').on('change', refreshPreview);

        /* Glass type: toggle relevant controls + refresh */
        $('#kdna_bg_glass_type').on('change', function () {
            updateGlassVisibility();
            refreshPreview();
        });
        updateGlassVisibility();

        /* Shape style: toggle relevant controls + refresh */
        $('#kdna_bg_shape_style').on('change', function () {
            updateShapeVisibility();
            refreshPreview();
        });
        updateShapeVisibility();

        updateNumbering();

        /* Initial preview render from saved form values */
        refreshPreview();
    });
})(jQuery);

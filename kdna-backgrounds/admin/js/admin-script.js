(function ($) {
    'use strict';

    var MAX_COLOURS = 10;
    var currentGradient = null;
    var refreshTimer = null;

    /**
     * Read every form field and return a gradient config object.
     */
    function buildConfigFromDOM() {
        var colours = [];
        $('#kdna-bg-colour-list .kdna-bg-colour-picker').each(function () {
            var val = $(this).val();
            if (val) colours.push(val);
        });
        if (colours.length < 2) colours = ['#0a2463', '#1e6bff', '#3d8bff'];

        return {
            colours:   colours,
            speed:     parseFloat($('#kdna_bg_speed').val()) || 5,
            amplitude: parseInt($('#kdna_bg_amplitude').val(), 10) || 320,
            density:   parseFloat($('#kdna_bg_density').val()) || 6,
            seed:      parseInt($('#kdna_bg_seed').val(), 10) || 5,
            darkenTop: $('#kdna_bg_darken_top').is(':checked')
        };
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
                'kdna_bg_density': '#kdna-bg-density-val'
            };
            var target = valMap[$(this).attr('id')];
            if (target) $(target).text($(this).val());
            refreshPreview();
        });

        /* Seed + darken-top */
        $('#kdna_bg_seed').on('input', refreshPreview);
        $('#kdna_bg_darken_top').on('change', refreshPreview);

        updateNumbering();

        /* Initial preview render from saved form values */
        refreshPreview();
    });
})(jQuery);

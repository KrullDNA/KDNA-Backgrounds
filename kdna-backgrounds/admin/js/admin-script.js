(function ($) {
    'use strict';

    var MAX_COLOURS = 10;

    function initPickers($scope) {
        $scope.find('.kdna-bg-colour-picker').each(function () {
            if (!$(this).closest('.wp-picker-container').length) {
                $(this).wpColorPicker({
                    change: function () {
                        updateNumbering();
                    }
                });
            }
        });
    }

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
            }
        });

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
        });

        $list.on('click', '.kdna-bg-remove-colour', function () {
            if ($list.find('.kdna-bg-colour-row').length <= 2) {
                alert('You need at least 2 colours for a gradient.');
                return;
            }
            $(this).closest('.kdna-bg-colour-row').remove();
            updateNumbering();
        });

        $('input[type="range"]').on('input', function () {
            var valMap = {
                'kdna_bg_speed': '#kdna-bg-speed-val',
                'kdna_bg_amplitude': '#kdna-bg-amp-val',
                'kdna_bg_density': '#kdna-bg-density-val'
            };
            var target = valMap[$(this).attr('id')];
            if (target) $(target).text($(this).val());
        });

        updateNumbering();
    });
})(jQuery);

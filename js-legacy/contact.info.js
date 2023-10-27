//
// This script is loaded into contact profile editor tab
// to enable autocomplete for Company field.
//
(function() { "use strict";

    // Init autocomplete when user interacts with company field
    $(document).on('keydown keypress', 'div.field.company input.val', function() {
        var $visibleField = $(this);
        if ($visibleField.data('ui-autocomplete')) {
            return;
        }
        $visibleField.autocomplete({
            appendTo: $visibleField.parent(),
            source: "?module=autocomplete&type=company",
            minLength: 2,
            focus: function() {
                return false;
            },
            select: function(event, ui) {
                var text = $("<div />").text(ui.item.name).text();
                $visibleField.val(text);
                return false;
            }
        }).data("ui-autocomplete")._renderItem = function( ul, item ) {
            return $("<li />").addClass("ui-menu-item-html").append("<div>"+ item.value + "</div>").appendTo( ul );
        };
    });

}());
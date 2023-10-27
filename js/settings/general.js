var CRMSettingsGeneral = (function ($) {

  CRMSettingsGeneral = function (options) {
    var that = this;

    // DOM
    that.$wrapper = options["$wrapper"];
    that.$form = that.$wrapper.find("form");

    // VARS

    // INIT
    that.initClass();
  };

  CRMSettingsGeneral.prototype.initClass = function () {
    var that = this;
    //
    that.initFooterToggle();
    //
    that.initSubmit();
  };

  CRMSettingsGeneral.prototype.initSubmit = function () {
    var that = this,
      $form = that.$form,
      $button = $form.find(".js-submit-button:first");

    $form.submit(function (e) {
      e.preventDefault();

      let $button_text = $button.text(),
        $loader_icon = ' <i class="fas fa-spinner fa-spin"></i>',
        $success_icon = ' <i class="fas fa-check-circle"></i>';
      $button.empty();
      $button.html($button_text + $loader_icon);

      $.post($form.attr('action'), $form.serialize(), function (response) {
        if (response.status === "ok") {
          $button.empty().html($button_text + $success_icon)
          $.crm.content.reload();
        }
      })
    });
  };

  CRMSettingsGeneral.prototype.initFooterToggle = function () {
    var that = this,
      active_class = "is-changed",
      $footer = that.$wrapper.find(".js-footer-actions"),
      $button = $footer.find(".js-submit-button");

    that.$wrapper.on("change keydown", "input, textarea, select", function () {
      toggle(true);
    });

    function toggle(changed) {
      if (changed) {
        $button.addClass("yellow");
        $footer.addClass(active_class);
      } else {
        $button.removeClass("yellow");
        $footer.removeClass(active_class);
      }
    }
  };

  return CRMSettingsGeneral;

})(jQuery);

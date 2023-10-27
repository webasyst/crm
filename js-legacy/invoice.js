var CRMInvoiceEdit = ( function($) {

    CRMInvoiceEdit = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");
        that.$table = that.$wrapper.find(".c-product-table");
        that.$tableBody = that.$table.find("tbody");
        that.$emptyTax = that.$table.find(".js-empty-tax");
        that.$emptyTaxLink = that.$table.find(".js-empty-tax-link");
        that.$tax = that.$table.find(".js-tax-toggle");
        that.$taxPercent = that.$table.find(".js-tax-percent");
        that.$taxType = that.$table.find(".js-tax-type");
        that.$taxName = that.$table.find(".js-tax-name");
        that.$taxHeader = that.$table.find(".js-tax-header");
        that.$currencyField = that.$wrapper.find(".js-currency-field");
        that.$companyField = that.$wrapper.find(".js-company-select");

        // VARS
        that.locale = options["locale"];
        that.locales = options["locales"];
        that.companies = ( options["companies"] || {});
        that.invoice_id = options["invoice_id"];
        that.row_template_html = options["row_template_html"];
        that.confirm_dialog_template = options["confirm_dialog_template"];
        that.shop_supported = options["shop_supported"];
        that.tax_set_class = "is-changed";

        that.currencies = options["currencies"];
        that.supported_currencies = options["supported_currencies"];

        // for edit first init
        that.company_id = options["company_id"];
        that.tax = ( options["tax"] && options["tax"].name ? options["tax"] : false );

        // DYNAMIC VARS
        that.active_tax_array = [];
        that.tax_percent = ( $( that.$tax.find("option")[that.$tax[0].selectedIndex] ).data("percent") || "0" );
        that.tax_type = $( that.$tax.find("option")[that.$tax[0].selectedIndex] ).data("type");

        // INIT
        that.initClass();
    };

    CRMInvoiceEdit.prototype.initClass = function() {
        var that = this;

        // deal already attached, no need to init selected
        if (that.$wrapper.find('input[name="invoice[deal_id]"]').val() == 0) {
            that.initSelectDeal();
        }

        that.initDatePickers();
        //
        that.initTableEvents();
        //
        that.initWYSIWYG();
        //
        that.initSubmit();
        //
        that.initChangeContact();
        //
        that.initChangeDeal();
        //
        that.initChangeCurrency();
        //
        if (that.shop_supported) {
            that.initProductName();
        }
        //
        that.initChangeTax();
        // must be init after "tax"
        that.initChangeCompany();
        //
        that.initToggleButton();
    };

    CRMInvoiceEdit.prototype.initTableEvents = function() {
        var that = this;

        // Add row
        that.$table.on("focus", ".js-name-field", function() {
            var $tr = $(this).closest("tr"),
                count = that.$tableBody.find("tr").length,
                tr_index = $tr.index();

            if (tr_index + 1 >= count) {
                that.addTableRow( count + 1 );
            }
        });

        that.$table.on("keypress", ".js-name-field", function(event) {
            var code = event.keyCode;
            if (code === 13) {
                event.preventDefault();
            }
        });

        that.$table.on("click", ".js-remove-row", function(event) {
            event.preventDefault();
            var count = that.$tableBody.find("tr").length;
            if (count > 1) {
                that.removeTableRow( $(this).closest("tr") );
            }
        });

        that.$table.on("change", ".js-amount-field", function() {
            that.changeTableRow( $(this) );
        });

        that.$table.on("change", ".js-price-field", function() {
            var $tr = $(this).closest("tr"),
                $amount = $tr.find(".js-amount-field"),
                amount = $amount.val();

            if (!amount) { $amount.val("1"); }

            that.changeTableRow( $(this) );
        });

        that.$table.on("click", ".js-set-product-tax", function() {
            var $element = $(this),
                $taxDropdown = $element.closest(".js-tax-dropdown"),
                $percentField = $taxDropdown.find(".js-product-tax-percent"),
                $typeField = $taxDropdown.find(".js-product-tax-type");

            var tax_index = parseInt($element.data("tax-index")),
                tax = that.active_tax_array[tax_index];

            if (tax) {

                if (parseInt(that.tax_percent) === parseInt(tax.tax_percent) && that.tax_type === tax.tax_type) {
                    $taxDropdown.removeClass(that.tax_set_class);
                } else {
                    $taxDropdown.addClass(that.tax_set_class);
                }

                var type = that.tax_type;
                if (tax.tax_type === "NONE") {
                    type = "NONE";
                }

                $typeField.val(type).trigger("change");

                var tax_percent = tax.tax_percent + "%";
                if (tax.tax_type === "NONE") {
                    var company = that.companies[ that.$companyField.val() ];
                    tax_percent = that.locales["no_tax"].replace("%name", company.tax_name);
                }

                $percentField.val(tax_percent).trigger("change");

                that.refreshTable();
            }
        });

        $(document).on("click", watcher);

        function watcher(event) {
            var is_exist = $.contains(document, that.$table[0]);
            if (is_exist) {
                // VARS
                var active_class = "is-active",
                    is_active = true;

                // DOM
                var $taxDropdown = that.$table.find(".js-tax-dropdown." + active_class),
                    $target = $(event.target),
                    $dropdown = $target.closest(".js-tax-dropdown");

                if ($dropdown.length) {
                    is_active = $dropdown.hasClass(active_class);
                }

                $taxDropdown.removeClass(active_class);

                if (!is_active && that.tax_type !== "NONE") {
                    $dropdown.addClass(active_class);
                }
            } else {
                $(document).off("click", watcher);
            }
        }
    };

    CRMInvoiceEdit.prototype.addTableRow = function(count) {
        var that = this,
            template = that.row_template_html,
            $row = $(template);

        $row.find(".js-product-number").text( count );

        that.renderProductTaxOptions($row);
        //
        that.$tableBody.append($row);

        that.renderProductTaxFields();

        // trigger for use events on name. only when shop supported
        if (that.shop_supported) {
            that.$wrapper.trigger("addProduct", $row);
        }
    };

    CRMInvoiceEdit.prototype.removeTableRow = function($tr) {
        var that = this;

        // remove
        $tr.remove();
        // remap indexes
        that.$tableBody.find("tr").each( function(index) {
            $(this).find(".js-product-number").text(index + 1);
        });

        that.refreshTable();
    };

    CRMInvoiceEdit.prototype.changeTableRow = function($field) {
        var that = this;
        //
        formatField($field, that.locale);
        //
        that.refreshTable();
    };

    CRMInvoiceEdit.prototype.refreshTable = function() {
        var that = this,
            products = getProducts();

        var tax_include = (that.tax_type && that.tax_type === "INCLUDE");

        // subtotal
        var subtotal = 0,
            tax = 0;

        $.each(products, function(index, item) {
            subtotal += item.total;

            var _tax_percent = (item.percent/100),
                _tax;

            if (tax_include) {
                _tax = item.total * (_tax_percent/(1 + _tax_percent));
            } else {
                _tax = item.total * _tax_percent
            }

            tax += _tax;
        });

        // set subtotal
        that.$table.find(".js-subtotal").text( getFormattedPrice(subtotal, that.locale) );

        // tax
        var $tax = that.$table.find(".js-tax");
        $tax.text( getFormattedPrice(tax, that.locale) );

        // total
        var $total = that.$table.find(".js-total"),
            total = subtotal + (tax_include ? 0 : tax);
        $total.text( getFormattedPrice(total, that.locale) );

        function getProducts() {
            var $products = that.$tableBody.find("tr"),
                products = [];

            $products.each( function() {
                var $product = $(this),
                    $amount = $product.find(".js-amount-field"),
                    $price = $product.find(".js-price-field"),
                    $total = $product.find(".js-product-total"),
                    $percent = $product.find(".js-product-tax-percent");

                var amount = getFieldData( $amount.val() );
                var price = getFieldData( $price.val() ),
                    total = 0;

                if (amount > 0 && price > 0) {
                    total = (price * amount);
                }

                $total.text( getFormattedPrice(total, that.locale) );

                var percent = parseInt( $percent.val() );
                if (!percent || !(percent > 0 || percent === 0)) {
                    percent = 0;
                }

                products.push({
                    $tr: $product,
                    percent: percent,
                    amount: amount,
                    price: price,
                    total: total
                });
            });

            return products;
        }

    };

    CRMInvoiceEdit.prototype.renderProductTaxOptions = function($product) {
        var that = this,
            $taxDropdown = false,
            percent_name;

        // render tax in new row
        if ($product && $product.length) {
            $taxDropdown = $product.find(".js-tax-dropdown");

        } else {
            $taxDropdown = that.$tableBody.find(".js-tax-dropdown");
        }

        var $list = $taxDropdown.find(".js-hidden-list").html("");

        var company = that.companies[ that.$companyField.val() ],
            percents = [];

        $.each(that.active_tax_array, function(index, item) {
            if (item.tax_type === "NONE") {
                percent_name = that.locales["no_tax"].replace("%name", company.tax_name);
            } else {
                percent_name = item.tax_percent + "%";
            }

            var render_item = (percents.indexOf(percent_name) < 0);
            if (render_item) {
                percents.push(percent_name);

                var li = "<li class=\"ui-menu-item-html ui-menu-item\"><div class=\"ui-menu-item-wrapper\"><div class=\"js-set-product-tax\" data-tax-index=\"" + index + "\">" + percent_name + "</div></div></li>";
                $list.append(li);
            }
        });
    };

    CRMInvoiceEdit.prototype.renderProductTaxFields = function(force) {
        var that = this;

        var $taxDropdowns = that.$tableBody.find(".js-tax-dropdown");

        if (force) {
            $taxDropdowns.removeClass(that.tax_set_class);
        }

        $taxDropdowns.each( function() {
            var $taxDropdown = $(this),
                $percentField = $taxDropdown.find(".js-product-tax-percent"),
                $typeField = $taxDropdown.find(".js-product-tax-type");

            if ( !$taxDropdown.hasClass(that.tax_set_class) || that.tax_type === "NONE" ) {
                $typeField.val(that.tax_type).trigger("change");

                var tax_percent = that.tax_percent + "%";
                if (that.tax_type === "NONE") {
                    tax_percent = "—";
                    $taxDropdown.removeClass(that.tax_set_class);
                }

                $percentField.val(tax_percent).trigger("change");
            }
        });
    };

    CRMInvoiceEdit.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(false, formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index, item) {
                if (item.name !== "invoice[contact]") {
                    result.data.push(item);
                }

                if (item.name === "invoice[contact_id]") {
                    if (!(item.value > 0)) {
                        result.errors.push({
                            "name": "invoice[contact_id]",
                            "value": that.locales["empty_contact"]
                        });
                    }
                }
            });

            setItemsData(result.data);

            return result;

            function setItemsData(data) {
                var $items = that.$tableBody.find("tr");

                $items.each( function(index) {
                    var $item = $(this),
                        $product = $item.find(".js-product-field"),
                        $amount = $item.find(".js-amount-field"),
                        $price = $item.find(".js-price-field"),
                        $taxPercent = $item.find(".js-product-tax-percent"),
                        $taxType = $item.find(".js-product-tax-type"),
                        $name = $item.find(".js-name-field");

                    var amount = getFieldData( $amount.val() ),
                        price = getFieldData( $price.val() ),
                        tax_percent = parseInt( $taxPercent.val() ),
                        tax_type = $taxType.val(),
                        product_id = $product.val(),
                        name = $name.val();

                    if (amount || price || name) {
                        data.push({
                            "name": "items[" + index + "][name]",
                            "value": name
                        });

                        data.push({
                            "name": "items[" + index + "][price]",
                            "value": price
                        });

                        data.push({
                            "name": "items[" + index + "][quantity]",
                            "value": amount
                        });

                        data.push({
                            "name": "items[" + index + "][tax_percent]",
                            "value": ( tax_percent > 0 || tax_percent === 0 ? tax_percent : "" )
                        });

                        data.push({
                            "name": "items[" + index + "][tax_type]",
                            "value": tax_type
                        });
                    }

                    if (product_id) {
                        data.push({
                            "name": "items[" + index + "][product_id]",
                            "value": product_id
                        });
                    }
                });
            }
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                var keys = Object.keys(ajax_errors);
                $.each(keys, function(index, name) {
                    errors.push({
                        name: name,
                        value: ajax_errors[name]
                    })
                });
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text),
                        field_o = $field.offset(),
                        wrapper_o = that.$wrapper.offset(),
                        top = field_o.top - wrapper_o.top + $field.outerHeight(),
                        left = field_o.left - wrapper_o.left;

                    $text.css({
                        left: left + "px",
                        top: top + "px"
                    });

                    that.$wrapper.append($text);

                    $field
                        .addClass(error_class)
                        .one("focus click change", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });
                }
            });
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=invoice&action=save";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        if (response.data.id) {
                            $(document).trigger("unsavedChanges", false);
                            var content_uri = $.crm.app_url + "invoice/" + response.data.id + "/";
                            $.crm.content.load(content_uri);
                        }
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMInvoiceEdit.prototype.initDatePickers = function() {
        var that = this;

        // DOM
        var $invoiceDatepicker = that.$wrapper.find(".js-invoice-datepicker"),
            $dueDatepicker = that.$wrapper.find(".js-invoice-due-datepicker"),
            $dayDueField = that.$wrapper.find(".js-due-days-field"),
            $reset = that.$wrapper.find(".js-clear-due-date");

        // Init

        initStartDatePicker();

        initDueDatePicker();

        // Events

        that.$wrapper.on("click", ".js-focus-on-field", function() {
            $(this).parent().find("input:text").trigger("focus");
        });

        $dayDueField.on("change", function() {
            var value = $dayDueField.val(),
                days_count = 0;

            if (value && value.length && parseInt(value) > 0) {
                days_count = parseInt(value);
                $dayDueField.val(days_count);
            }

            if (days_count > 0) {
                $reset.show();
            } else {
                $reset.hide();
            }

            $dayDueField.val(days_count);

            setDueDate(days_count);
        });

        $invoiceDatepicker.on("change", function() {
            var value = $dayDueField.val();
            if (value && value.length && parseInt(value) > 0) {
                $dayDueField.trigger("change");
            }
        });

        $dueDatepicker.on("change", function() {
            $dayDueField.val("");
        });

        $reset.on("click", function() {
            $dueDatepicker.val("").trigger("change");
        });

        // Functions

        function setDueDate(days_count) {
            var date = $invoiceDatepicker.datepicker("getDate"),
                new_date = new Date(date.getTime() + days_count*24*60*60*1000);

            $dueDatepicker.datepicker("setDate", new_date);
        }

        function initStartDatePicker() {
            var $input = $invoiceDatepicker,
                $altField = $input.parent().find("input[type='hidden']");

            $input.datepicker({
                altField: $altField,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true
            });

            // set default date for new invoice
            if (!that.invoice_id) {
                $input.datepicker("setDate", "+0d");
            }
        }

        function initDueDatePicker() {
            var $input = $dueDatepicker,
                $altField = $input.parent().find("input[type='hidden']");

            $input.datepicker({
                altField: $altField,
                altFormat: "yy-mm-dd",
                changeMonth: true,
                changeYear: true
            });

            $input.on("change", function() {
                var value = $(this).val();
                if (!$.trim(value).length) {
                    $altField.val("");
                    $reset.hide();
                } else {
                    $reset.show();
                }
            });
        }

    };

    CRMInvoiceEdit.prototype.initChangeContact = function() {
        var that = this,
            $toggle = that.$wrapper.find(".js-contact-toggle"),
            $name = $toggle.find(".js-name"),
            $field = $toggle.find(".js-contact-id"),
            is_set = false;

        $toggle.on("click", ".js-change-contact", showDialog);

        function showDialog(event) {
            event.preventDefault();

            var $link = $(this),
                href = $.crm.app_url + "?module=invoice&action=contactAdd";

            $.get(href, function (html) {
                new CRMDialog({
                    html: html,
                    options: {
                        onAdd: function(id, name) {
                            $field.val(id).trigger("change");
                            $name.text(name);

                            if (!is_set) {
                                var $label = $link.find(".js-label"),
                                    text = $label.data("change-text");

                                $label.text(text);
                                is_set = true;
                            }
                        }
                    }
                });
            });
        }
    };

    CRMInvoiceEdit.prototype.loadDealListByContact = function (callback) {
        let contact_id = this.$wrapper.find(".js-contact-toggle .js-contact-id").val();
        let href = $.crm.app_url +'?module=deal&action=byContact&id='+ contact_id;

        callback = callback || function () {};
        if (contact_id <= 0) {
            callback({});
            return;

        }
        $.get(href, "json").always(function (response) {
            if (response && response.status === "ok") {
                callback(response.data || {});
            } else {
                callback({});
            }
        });
    };

    CRMInvoiceEdit.prototype.initSelectDeal = function (options) {
        options = options || {};
        let that = this;
        let $wrapper = options.$wrapper || that.$wrapper;
        let $deal_form = $wrapper.find('.js-deal-selector-control-wrapper');
        let $deal_name = $deal_form.find('.js-deal-name');
        let $deal_name_input = $deal_form.find('.js-deal-name-input');
        let $deal_save = $deal_form.find('.js-save-deal');
        let $deals_dropdown = $deal_form.find('.js-deals-dropdown');
        let $deal_create_new_single_link = $deal_form.find('.js-create-new-deal-link');
        let $deal_remove = $deal_form.find('.js-remove-deal');
        let $deal_empty = $deals_dropdown.find('.js-empty-deal');
        let $visible_link = $deal_form.find('.js-select-deal .js-visible-link .js-text');
        let $select_funnel = $deal_form.find('.js-select-funnel-wrapper');
        let $select_stage = $deal_form.find('.js-select-stage-wrapper');
        let $deals_list = $deal_form.find('.js-deals-list');
        let $deal_id = $deal_form.find('.js-deal-id');

        // render helper
        var renderContactDeals = function (data) {
            data = data || {};
            let deals = data.deals || {};
            let funnels = data.funnels || {};

            // rendering contact deals
             deals_count = 0;
            $.each(deals, function (i, deal) {
                $deals_list.prepend(renderDeals(deal, funnels[deal.funnel_id]));
                deals_count++;
            });

            $deal_form.show();
            $deals_dropdown.data('deals_count', deals_count);

            if (deals_count > 0) {
                $deals_dropdown.removeClass('hidden');
                $deal_create_new_single_link.addClass('hidden');
            } else {
                $deal_create_new_single_link.removeClass('hidden');
                $deals_dropdown.addClass('hidden');
            }
            $.crm.renderSVG($wrapper);
        };

        // Default deal_id - none
        $deal_id.val('none');

        if (typeof options.data === 'undefined') {
            // Load deals by contact
            that.loadDealListByContact(renderContactDeals);
        } else {
            // render predefined list of deals
            renderContactDeals(options.data);
        }

        // New deal
        $deal_form.on('click', '.js-create-new-deal', function () {
            $deal_id.val('0');
            $deals_dropdown.addClass('hidden');
            $deal_create_new_single_link.addClass('hidden');
            $deal_name.removeClass('c-deal-name-hidden');
            $deal_save.attr('title', that.locales['deal_create']).removeClass('hidden');
            $select_funnel.removeClass('hidden');
            $deal_empty.removeClass('c-empty-deal-hidden');
            $deal_name_input.focus();
        });

        // Select old deal
        $deal_form.on('click', '.js-deal-item', function () {
            let new_deal = $(this).find('.js-text').html();
            $visible_link.html(new_deal);
            $deal_id.val($(this).data('deal-id'));
            $deal_save.attr('title', that.locales['deal_add']).removeClass('hidden');
            $select_funnel.addClass('hidden');
            $deal_empty.removeClass('c-empty-deal-hidden');
            $deals_list.find('li').removeClass('selected');
            $(this).parent().addClass('selected');
        });

        function renderDeals(deal, funnel) {
            if (!deal || deal.id <= 0) {
                return '';
            }

            let color = '';
            let deal_id = deal.id;
            let deal_name = deal.name || '';
            let funnel_deleted_html = '';    // if case is funnel is deleted (empty)

            if (funnel && funnel.stages && funnel.stages[deal.stage_id]) {
                color = funnel.stages[deal.stage_id].color || '';
            }

            if ($.isEmptyObject(funnel)) {
                funnel_deleted_html = '<span class="hint">' + that.locales['funnel_deleted'] + '</span>';
            }

            return '<li><a href="javascript:void(0);" class="js-deal-item" data-deal-id="'+ deal_id +'">'+
                '<span class="js-text"><i class="icon16 funnel-state svg-icon" data-color="'+ color +'"></i>'+
                '<b><i>'+ deal_name +'</i></b>'+
                '</span>'+ funnel_deleted_html +'</a>'+
                '</li>';
        }

        function emptyDeal() {
            $visible_link.html('<b><i>'+ that.locales['deal_empty'] +'</i></b>');
            $deal_id.val('none');
            $deal_name_input.val('');
            $deal_name.addClass('c-deal-name-hidden');
            $deal_save.addClass('hidden').removeAttr('title');
            $deal_empty.addClass('c-empty-deal-hidden');
            $select_funnel.addClass('hidden');
            $deals_list.find('li').removeClass('selected');

            if ($deals_dropdown.data('deals_count') > 0) {
                $deals_dropdown.removeClass('hidden');
                $deal_create_new_single_link.addClass('hidden');
            } else {
                $deals_dropdown.addClass('hidden');
                $deal_create_new_single_link.removeClass('hidden');
            }
        }

        function saveDeal() {
            let $created_deal = $wrapper.find('.js-created-deal');
            let $new_deal_stage_icon = $select_stage.find('.js-visible-link .js-text .funnel-state').clone();
            let new_deal_name = $.trim($deal_name_input.val());
            let data = $deal_form.serializeObject();

            data['invoice_id'] = that.invoice_id;
            data['deal[id]'] = $deal_id.val();

            // Validate deal data
            if ($deal_id.val() === 'none') {
                $deal_form.addClass('shake animated');
                setTimeout(function () {
                    $deal_form.removeClass('shake animated');
                },500);
                return false;
            }

            if ($deal_id.val() <= 0 && !new_deal_name) {
                $deal_name.addClass('shake animated');
                setTimeout(function () {
                    $deal_name.removeClass('shake animated');
                    $deal_name_input.focus();
                },500);
                return false;
            }

            $deal_form.addClass('deal-form-hidden');
            $created_deal.removeClass('hidden');

            // Set deal
            if ($deal_id.val() <= 0) {
                $created_deal.html($new_deal_stage_icon);
                $created_deal.append($.crm.escape(new_deal_name));
            } else {
                let $old_deal = $deals_dropdown.find('.js-visible-link .js-text');
                let $old_deal_stage_icon = $old_deal.find('.funnel-state').clone();
                let old_deal_name = $old_deal.find('b i').text();

                $created_deal.html($old_deal_stage_icon);
                $created_deal.append($.crm.escape(old_deal_name));
            }

            // Send data
            var href = $.crm.app_url +"?module=invoice&action=associateDealSave";
            $.post(href, data, function(res) {
                if (res.status === "ok") {
                    $.crm.content.reload();
                } else {
                    $created_deal.html('');
                    $created_deal.addClass('hidden');
                    emptyDeal();
                    $deal_form.removeClass('deal-form-hidden');
                }
            });
        }

        // Hide items in .menu-h .dropdown, by clicking (select) an item
        $deals_list.on('click', function () {
            $deals_list.hide();
            setTimeout( function () {
                $deals_list.removeAttr("style");
            }, 200);
        });

        // Remove deal
        $deal_empty.on('click', function () {
            emptyDeal();
        });
        $deal_remove.on('click', function () {
            emptyDeal();
        });

        // Save deal on click button (this button could not exist)
        $deal_save.on('click', function (e) {
            e.preventDefault();
            saveDeal();
        });

        $deal_form.on('submit', function (e) {
            e.preventDefault();
            saveDeal();
        });

        // Load new funnel stages
        $deal_form.on('change', '.js-select-deal-funnel', function () {
            $deal_form.find('.js-select-stage-wrapper').load('?module=deal&action=stagesByFunnel&id='+ $(this).val());
        });

        return {
            submit: function () {
                $deal_form.trigger('submit');
            }
        };
    };

    CRMInvoiceEdit.prototype.initChangeDeal = function () {
        let that = this;
        let $toggle = that.$wrapper.find(".js-deal-toggle");
        let deal_id = that.$wrapper.find(".js-deal-toggle [name='invoice[deal_id]']").val();
        let is_send = false;

        function showDialog(event) {
            event.preventDefault();

            if (is_send) {
                return;
            }
            that.loadDealListByContact(function (data) {
                is_send = true;
                let dialog_url = $.crm.app_url +'?module=invoiceDeal&action=attachDialog';
                $.get(dialog_url, { invoice_id: that.invoice_id, deal_id: deal_id }, function (html) {
                    let $dialog = $(html);
                    is_send = false;
                    new CRMDialog({
                        html: $dialog,
                        onOpen: function () {
                            let $deal_block = $dialog.find('.js-invoice-deal');
                            let deal_selector = that.initSelectDeal({
                                $wrapper: $deal_block,
                                data: data
                            });
                            $dialog.find('.js-save-button').on('click', function () {
                                deal_selector.submit();
                            });
                        }
                    });
                });
            });
        }

        $toggle.on("click", ".js-change-deal", showDialog);

        let detach_deal_xhr = null;
        $toggle.on('click', '.js-detach-deal', function (e) {
            e.preventDefault();
            let name = $toggle.find('.js-deal-name').text();
            $.crm.confirm.show({
                title: that.locales["deal_detach_title"],
                text: that.locales["deal_detach_text"].replace(/%s/, name),
                button: that.locales["deal_detach_confirm_button"],
                onConfirm: function() {
                    detach_deal_xhr && detach_deal_xhr.abort();
                    that.loadDealListByContact(function (data) {
                        let url = $.crm.app_url +'?module=invoiceDeal&action=detach';
                        detach_deal_xhr = $.post(url, { invoice_id: that.invoice_id })
                            .done(function (r) {
                                if (r && r.status === 'ok') {
                                    var html = r.data && r.data.html;
                                    $toggle.html(html);
                                    that.initSelectDeal({
                                        data: data
                                    });
                                }
                            })
                            .always(function () {
                                detach_deal_xhr = null;
                            });
                    });
                }
            });
        });
    };

    CRMInvoiceEdit.prototype.initChangeCompany = function() {
        var that = this,
            $companyField = that.$companyField,
            $taxSelect = that.$tax;

        $companyField.on("change", renderTax);

        $companyField.trigger("change", true);

        function renderTax(event, is_first_load) {
            var company_id = $(this).val(),
                company = that.companies[company_id];

            that.active_tax_array = [];

            if (company) {
                var tax_name = ( company.tax_name || that.locales["tax"]),
                    tax_array = JSON.parse(company["tax_options"]);

                setTax(tax_array, tax_name, is_first_load);
            }
        }

        function setTax(tax_array, tax_name, is_first_load) {
            if (tax_array && tax_array.length) {
                that.active_tax_array = tax_array;
            }

            $taxSelect.find("option").remove();

            // set name in table header
            that.$taxHeader.text(  ( tax_name.length ? tax_name : "—") );

            //
            if (tax_array && tax_name) {
                that.$emptyTax.hide();
                that.$tax.show();
                $.each(tax_array, function(index, item) {
                    var $item = $("<option value=\"" + index + "\"></option>"),
                        text;

                    if (item.tax_type === "INCLUDE") {
                        $item.data("type", "INCLUDE");
                        text = that.locales["include_tax"];

                    } else if (item.tax_type === "APPEND") {
                        $item.data("type", "APPEND");
                        text = that.locales["append_tax"];
                    } else if (item.tax_type === "NONE") {
                        $item.data("type", "NONE");
                        item.tax_percent = 0;
                        text = that.locales["no_tax"];
                    }

                    if (item.tax_percent > 0) {
                        $item.data("percent", item.tax_percent);
                        text = text.replace("%value", item.tax_percent + "%");
                    } else {
                        $item.data("percent", 0);
                        text = text.replace("%value", "");
                    }

                    text = text.replace("%name", tax_name);

                    if (text) {

                        // set selected
                        if (that.tax) {
                            var is_this_name = (tax_name === that.tax.name),
                                is_this_type = (item.tax_type === that.tax.type),
                                is_this_percent = ( (+item.tax_percent).toFixed(2) === (+that.tax.percent).toFixed(2) );

                            if (is_this_name && is_this_type && is_this_percent) {
                                $item.attr("selected", true);
                            }
                        }

                        $item.text(text);
                        $item.data("name", tax_name);
                        $taxSelect.append($item);
                    }
                });
            } else {
                that.$tax.hide();
                that.$emptyTax.show();
                var href = that.$emptyTaxLink.data('href');
                that.$emptyTaxLink.attr('href', href + that.$companyField.val().toString() + '/');
            }

            $taxSelect.trigger("change", !is_first_load);
        }
    };

    CRMInvoiceEdit.prototype.initChangeTax = function() {
        var that = this;

        that.$tax.on("change", onChangeTax);

        function onChangeTax(event, force) {
            var $options = $(this).find("option");

            that.tax_percent = 0;
            that.tax_type = "NONE";
            that.tax_name = "";

            if ($options.length) {
                var option = $options[this.selectedIndex];
                // set data
                that.tax_percent = ( $(option).data("percent") || 0 );
                that.tax_type = $(option).data("type");
                that.tax_name = $(option).data("name");
            }

            // for save
            that.$taxPercent.val(that.tax_percent);
            that.$taxType.val(that.tax_type);
            that.$taxName.val(that.tax_name);

            that.renderProductTaxOptions();

            that.renderProductTaxFields(force);

            // render
            that.refreshTable();
        }
    };

    CRMInvoiceEdit.prototype.initWYSIWYG = function() {
        var that = this;

        var $textarea = that.$wrapper.find(".js-wysiwyg");

        $.crm.initWYSIWYG($textarea, {
            keydownCallback: function (e) {
                //if (e.keyCode == 13 && e.ctrlKey) {
                    //return addComment(); // Ctrl+Enter disabled
                //}
            }
        });
    };

    CRMInvoiceEdit.prototype.initProductName = function() {
        var that = this;

        var $wrappers = that.$wrapper.find(".js-name-autocomplete");
        if ($wrappers.length) {
            $wrappers.each( function() { init( $(this) ); });
            that.refreshTable();
        }

        that.$wrapper.on("addProduct", function(event, tr) {
            var $wrapper = $(tr).find(".js-name-autocomplete");
            if ($wrapper.length) { init($wrapper); }
        });

        function init($wrapper) {
            // DOM
            var $nameField = $wrapper.find(".js-name-field"),
                $list = $wrapper.find(".js-hidden-list"),
                $productField = $wrapper.find(".js-product-field");

            // VARS
            var active_class = "is-active",
                selected_class = "is-highlighted";

            // DYNAMIC VARS
            var is_active = false,
                timer = 0,
                xhr = false;

            // EVENTS

            $nameField.on("keyup", function(event) {
                var code = event.keyCode,
                    stopCodes = [13, 27, 37,38,39, 40];

                if (stopCodes.indexOf(code) >= 0) {

                    switch(code) {
                        case 13:
                            setItem();
                            break;
                        case 27:
                            hide();
                            $nameField.trigger("blur");
                            break;
                        case 38:
                            highlightItem(false);
                            break;
                        case 40:
                            highlightItem(true);
                            break;
                        default:
                            break;
                    }

                } else {
                    var value = $(this).val();
                    if (value.length > 1) {

                        if (!is_active) {
                            that.$wrapper.trigger("searchProduct");
                            $wrapper.addClass(active_class);
                            is_active = true;
                        }

                        clearTimeout(timer);
                        timer = setTimeout(function () {
                            searchProducts(value);
                        }, 500);

                    } else {
                        that.$wrapper.trigger("searchProduct");
                    }
                }
            });

            $nameField.on("change", function() {
                $productField.val("").trigger("change");
            });

            that.$wrapper.on("searchProduct", hide);

            $list.on("click", ".js-set-product", function() {
                var product = $(this).data("product");
                if (product) { searchSKU(product); }
            });

            $list.on("click", ".js-set-sku", function() {
                var $item = $(this),
                    product = $item.data("product"),
                    sku = ( $item.data("sku") || false);

                if (product) { setProduct(product, sku); }

                hide();

                $nameField.trigger("blur");
            });

            // FUNCTIONS

            function searchProducts(search_text) {
                if (xhr) { xhr.abort(); }

                var href = $.crm.backend_url + "shop/?action=autocomplete&with_counts=1",
                    data = {
                        term: search_text
                    };

                renderItem("loading");

                xhr = $.get(href, data, function(response) {
                    if (response.length) {

                        $list.html("");

                        $.each(response, function(index, item) {
                            var $item = $("<div />");

                            $item
                                .html(item.label)
                                .data("product", item)
                                .addClass("js-set-product");

                            renderItem($item);
                        });

                    } else {
                        that.$wrapper.trigger("searchProduct");
                    }
                }, "json").always( function() {
                    xhr = false;
                });
            }

            function searchSKU(product) {
                if (xhr) { xhr.abort(); }

                renderItem("loading");

                var currency = that.$currencyField.val();
                var href = $.crm.backend_url + "shop/?module=orders&action=getProduct",
                    data = {
                        product_id: product.id,
                        currency: currency
                    };

                xhr = $.get(href, data, function(response) {
                    if (response.status === "ok") {

                        $list.html("");

                        $.each(response.data.sku_ids, function(index, sku_id) {
                            var $item = $("<div />"),
                                sku = response.data.product["skus"][sku_id];

                            $item
                                .text(response.data.product.name + ( sku.name.length ? " (" + sku.name + ")" : "" ) )
                                .data("product", response.data.product)
                                .data("sku", sku)
                                .addClass("js-set-sku");

                            renderItem($item);

                            if (response.data.sku_ids.length === 1) {
                                $item.trigger("click");
                            }
                        });

                    } else {
                        that.$wrapper.trigger("searchProduct");
                    }
                }, "json").always( function() {
                    xhr = false;
                });
            }

            function renderItem($item) {
                if ($item === "loading") {
                    $list.html("").append("<li class=\"ui-menu-item-html ui-menu-item\"><div class=\"ui-menu-item-wrapper\"><i class=\"icon16 loading\"></i></div></li>");

                } else {
                    var $li = $("<li />").addClass("ui-menu-item-html ui-menu-item");
                    $("<div />").addClass("ui-menu-item-wrapper").append($item).appendTo($li);
                    $li.appendTo($list);
                }
            }

            function setProduct(product, sku) {
                var name = product.name + ( sku.name.length ? " (" + sku.name + ")" : ""),
                    product_id = "shop:" + product.id + (sku ? ":" + sku.id : ""),
                    price = sku.price;

                // set name
                $nameField.val(name).trigger("change");

                // set price
                $wrapper.closest("tr").find(".js-price-field").val(price).trigger("change");

                // set id
                $productField.val(product_id).trigger("change");
            }

            function hide() {
                $wrapper.removeClass(active_class);
                $list.html("");
                is_active = false;
            }

            // keyboard events for autocomplete

            function highlightItem(to_bottom) {
                var $items = $list.find("li"),
                    $activeItem = $items.filter("." + selected_class),
                    $newItem = false;

                if (!$items.length) { return false; }

                if ($activeItem.length && $items.length > 1) {
                    $activeItem.removeClass(selected_class);

                    if (to_bottom) {
                        $newItem = $activeItem.next();
                        if (!$newItem.length) {
                            $newItem = $items.first();
                        }
                    } else {
                        $newItem = $activeItem.prev();
                        if (!$newItem.length) {
                            $newItem = $items.last();
                        }
                    }

                } else {
                    $newItem = $items.first();
                }

                $newItem.addClass(selected_class);
            }

            function setItem() {
                var $activeItem = $list.find("li." + selected_class);
                if ($activeItem.length) {
                    $activeItem.find(".ui-menu-item-wrapper > *").first().trigger("click");
                }
            }
        }
    };

    CRMInvoiceEdit.prototype.initChangeCurrency = function() {
        var that = this,
            currency = that.$currencyField.val();

        that.$currencyField.on("change", showConfirm);

        function showConfirm() {
            var new_currency = that.$currencyField.val(),
                show_confirm = false;

            var $priceFields = that.$tableBody.find(".js-price-field");
            $priceFields.each( function() {
                if ($(this).val().length) {
                    show_confirm = true;
                    return false;
                }
            });

            if (show_confirm) {
                that.$currencyField.val(currency);

                new CRMDialog({
                    html: that.confirm_dialog_template,
                    options: {
                        changeCurrencyWithPrice: function() {
                            that.$currencyField.val(new_currency);
                            updatePrices(new_currency, currency);
                            currency = new_currency;
                        },
                        changeCurrency: function() {
                            that.$currencyField.val(new_currency);
                            currency = new_currency;
                        }
                    }
                });

            } else {
                currency = new_currency;
            }
        }

        function updatePrices(new_currency, old_currency) {
            var $priceFields = that.$tableBody.find(".js-price-field");

            $priceFields.each( function() {
                var $field = $(this),
                    string = $field.val(),
                    price = 0;

                if (string && string.length) {
                    string = string.replace(/,/g,".").replace(/\s/g,"");
                    if (parseFloat(string) > 0) {
                        price = parseFloat(string);
                    }

                    var new_price = getPrice(price, new_currency, old_currency);
                    $field.val(new_price).trigger("change");
                }
            });
        }

        function getPrice(price, new_currency, old_currency) {
            var old_currency_rate = that.currencies[old_currency].rate,
                new_currency_rate = that.currencies[new_currency].rate;

            return (price * old_currency_rate / new_currency_rate);
        }
    };

    CRMInvoiceEdit.prototype.initToggleButton = function() {
        var that = this,
            active_class = "is-changed",
            $footer = that.$wrapper.find(".js-footer-actions"),
            $button = $footer.find(".js-submit-button");

        that.$wrapper.on("change keydown", "input, textarea, select", function() {
            toggle(true);
        });

        function toggle(changed) {
            if (changed) {
                $button.removeClass("green").addClass("yellow");
                $footer.addClass(active_class);

                $(document).trigger("unsavedChanges", true);
            } else {
                $button.removeClass("yellow").addClass("green");
                $footer.removeClass(active_class);
            }
        }
    };

    return CRMInvoiceEdit;

    function formatField($field, locale) {
        var is_price = ($field.data("type") === "price"),
            string = $field.val(),
            result = 0;

        if (string && string.length) {
            string = string.replace(/,/g,".").replace(/\s/g,"");
            if (parseFloat(string) > 0) {
                result = parseFloat(string);
            }
        }

        $field.val( is_price ? getFormattedPrice(result, locale) : result );
    }

    function getFormattedPrice(price, locale) {
        var result = price.toFixed(2);

        if (locale === "ru") {
            result = result.replace(".",",");
        }

        return result;
    }

    function getFieldData(string) {
        var result = 0;

        if (string && string.length) {
            string = string.replace(/,/g,".").replace(/\s/g,"");
            if (parseFloat(string) != string) {
                result = false;
            } else {
                result = parseFloat(string);
            }
        }

        return result;
    }

})(jQuery);

var CRMInvoiceContactAdd = ( function($) {

    CRMInvoiceContactAdd = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.dialog = that.$wrapper.data("dialog");
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMInvoiceContactAdd.prototype.initClass = function() {
        var that = this,
            is_locked = false;

        that.$form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var $id = that.$wrapper.find(".js-contact-id-field"),
                contact_id = $id.val(),
                contact_name;

            if (contact_id) {
                contact_name = that.$wrapper.find(".js-contact-autocomplete").val();
                setContact(contact_id, contact_name);
            } else {
                createContact();
            }
        }

        function setContact(id, name) {
            that.dialog.options.onAdd(id, name);
            that.dialog.close();
        }

        function createContact() {
            if (!is_locked) {
                is_locked = true;

                var formData = getData();

                if (formData.errors.length) {
                    showErrors(false,formData.errors);
                    is_locked = false;
                } else {
                    save(formData.data);
                }
            }
        }

        function getData() {
            var formData = that.$form.serializeArray(),
                result = {
                    data: [],
                    errors: []
                };

            var id_is_set = false,
                name_is_set = false;

            $.each(formData, function(index, item) {
                var name = item.name,
                    value = item.value;

                if (name === "deal[contact_id]" && value) {
                    id_is_set = true;
                }

                if (name === "contact[firstname]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[lastname]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[middlename]" && value) {
                    name_is_set = true;
                }

                if (name === "contact[name]" && value) {
                    name_is_set = true;
                }

                result.data.push(item);
            });

            if (!id_is_set && !name_is_set) {
                result.errors.push({
                    name: "contact[name]",
                    value: that.locales["empty_name"]
                });
                result.errors.push({
                    name: "contact[firstname]",
                    value: that.locales["empty_name"]
                });
            }

            return result;
        }

        function save(data) {
            var href = "?module=invoice&action=contactAddSave";

            $.post(href, data, function(response) {
                if (response.status === "ok") {
                    setContact(response.data.id, response.data.name);
                } else {
                    showErrors(response.errors);
                }
            }, "json").always( function() {
                is_locked = false;
            });
        }

        function showErrors(ajax_errors, errors) {
            var error_class = "error";

            errors = (errors ? errors : []);

            if (ajax_errors) {
                var keys = Object.keys(ajax_errors);
                $.each(keys, function(index, name) {
                    errors.push({
                        name: name,
                        value: ajax_errors[name]
                    })
                });
            }

            $.each(errors, function(index, item) {
                var name = item.name,
                    text = item.value;

                if (name === "name") {
                    name = "contact[firstname]";
                }

                var $field = that.$form.find("[name=\"" + name + "\"]");

                if ( name === "contact[firstname]" && !$field.is(":visible") ) {
                    $field = that.$form.find(".js-contact-autocomplete");
                }

                if ($field.length && !$field.hasClass(error_class)) {

                    var $text = $("<span />").addClass("errormsg").text(text);
                    $text.insertAfter($field);

                    $field
                        .addClass(error_class)
                        .one("focus click", function() {
                            $field.removeClass(error_class);
                            $text.remove();
                        });

                    $(document).on("toggleChanged", function() {
                        $field.removeClass(error_class);
                        $text.remove();
                    });
                }
            });
        }
    };

    return CRMInvoiceContactAdd;

})(jQuery);

/**
 * init in invoice/InvoiceId.html
 * */
var CRMInvoicePage = ( function($) {

    CRMInvoicePage = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];

        // VARS
        that.invoice_id = options["invoice_id"];
        that.locales = options["locales"];

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMInvoicePage.prototype.initClass = function() {
        var that = this;
        //
        that.initChangeState();
        //
        that.initDelete();
        //
        that.initRefund();
        //
        that.initRestore();
        // set active in sidebar
        setTimeout( function() {
            $(document).trigger("viewInvoice", that.invoice_id);
        }, 100);

        that.$wrapper.data("page", that);
    };

    CRMInvoicePage.prototype.initChangeState = function() {
        var that = this,
            $wrapper = that.$wrapper,
            invoice_id = that.invoice_id,
            is_locked = false;

        $wrapper.on("click", ".js-change-state", function(event) {
            event.preventDefault();
            var action = $(this).data("action");
            if (action) { submit(action); }
        });

        function submit(action) {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=invoice&action=handleTransaction",
                    data = {
                        invoice_id: invoice_id,
                        action: action
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        updateInvoiceAtSidebar(that.invoice_id, (action === "delete" ? "" : response.data.html));
                        var redirect_uri = $.crm.app_url + "invoice/" + that.invoice_id + "/";
                        that.load(redirect_uri);

                    } else {
                        alert(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMInvoicePage.prototype.initDelete = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-delete-invoice", function(event) {
            event.preventDefault();

            $.crm.confirm.show({
                title: that.locales["delete_confirm_title"],
                text: that.locales["delete_confirm_text"],
                button: that.locales["delete_confirm_button"],
                onConfirm: deleteInvoice
            });
        });

        function deleteInvoice() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=invoice&action=handleTransaction",
                    data = {
                        invoice_id: that.invoice_id,
                        action: "delete"
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        var content_uri = $.crm.app_url + "invoice/";
                        $.crm.content.load(content_uri);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMInvoicePage.prototype.initRefund = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-change-refund", function(event) {
            event.preventDefault();
            showDialog();
        });

        function showDialog() {
            if (!is_locked) {
                is_locked = true;

                var href = $.crm.app_url + "?module=invoice&action=refundDialog",
                    data = {
                        invoice_id: that.invoice_id
                    };

                $.post(href, data, function(html) {
                    new CRMDialog({
                        html: html,
                        options: {
                            onRefund: function() {
                                that.reload();
                            }
                        }
                    });
                }).always( function() {
                    is_locked = false;
                });
            }
        }
    };

    CRMInvoicePage.prototype.initRestore = function() {
        var that = this,
            is_locked = false;

        that.$wrapper.on("click", ".js-restore-invoice", function(event) {
            event.preventDefault();
            restore();
        });

        function restore() {
            if (!is_locked) {
                is_locked = true;

                var href = "?module=invoiceRestore",
                    data = {
                        id: that.invoice_id
                    };

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        updateInvoiceAtSidebar(that.invoice_id, response.data.html);
                        that.reload();
                    } else {
                        alert(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }

    };

    CRMInvoicePage.prototype.load = function(content_uri) {
        var that = this;

        var $link = $("<a />").addClass("js-disable-router").attr("href", content_uri).hide();
        that.$wrapper.append($link);
        $link.trigger("click");
    };

    CRMInvoicePage.prototype.reload = function() {
        var that = this;
        that.load(location.href);
    };

    return CRMInvoicePage;

    /**
     * @param {Number|String} invoice_id
     * @param {String} html
     * */
    function updateInvoiceAtSidebar(invoice_id, html) {
        if (!invoice_id) {
            return false;
        }

        var $selectedInvoice = $(".js-invoices-list .c-invoice[data-id=\"" + invoice_id + "\"]");
        if ($selectedInvoice.length) {
            if (html) {
                $selectedInvoice.replaceWith(html);
            } else {
                $selectedInvoice.remove();
            }
        }
    }

})(jQuery);

/**
 * init in invoice/InvoiceRefundDialog.html
 * */
var CRMInvoiceRefundDialog = ( function($) {

    CRMInvoiceRefundDialog = function(options) {
        var that = this;

        // DOM
        that.$wrapper = options["$wrapper"];
        that.$form = that.$wrapper.find("form");

        // VARS
        that.dialog = that.$wrapper.data("dialog");

        // DYNAMIC VARS

        // INIT
        that.initClass();
    };

    CRMInvoiceRefundDialog.prototype.initClass = function() {
        var that = this;

        that.initSubmit();
    };

    CRMInvoiceRefundDialog.prototype.initSubmit = function() {
        var that = this,
            $form = that.$form,
            is_locked = false;

        $form.on("submit", onSubmit);

        function onSubmit(event) {
            event.preventDefault();

            var formData = getData();

            if (formData.errors.length) {
                showErrors(formData.errors);
            } else {
                request(formData.data);
            }
        }

        function getData() {
            var result = {
                    data: [],
                    errors: []
                },
                data = $form.serializeArray();

            $.each(data, function(index, item) {
                result.data.push(item);
            });

            return result;
        }

        function showErrors(errors) {
            console.log(errors);
        }

        function request(data) {
            if (!is_locked) {
                is_locked = true;
                var href = $.crm.app_url + "?module=invoice&action=handleTransaction";

                $.post(href, data, function(response) {
                    if (response.status === "ok") {
                        that.dialog.options.onRefund();
                        that.dialog.close();
                    } else {
                        showErrors(response.errors);
                    }
                }, "json").always( function() {
                    is_locked = false;
                });
            }
        }
    };

    return CRMInvoiceRefundDialog;

})(jQuery);
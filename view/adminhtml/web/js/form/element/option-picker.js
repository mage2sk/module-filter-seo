/**
 * Panth Filter SEO — dynamic option picker for the Filter URL Rewrite form.
 *
 * Fetches the selected attribute's options from the admin AJAX endpoint and
 * populates this select's option list. Also auto-fills the sibling
 * `option_label` and `rewrite_slug` fields when the user picks a value.
 */
define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Select, $, urlBuilder, registry) {
    'use strict';

    return Select.extend({
        defaults: {
            cachedOptions: {},
            optionsEndpoint: 'panth_filterseo/filterrewrite/options',
            listens: {
                '${ $.provider }:data.attribute_code': 'onAttributeChange',
                value: 'onValueChange'
            },
            imports: {
                initialAttributeCode: '${ $.provider }:data.attribute_code'
            },
            modules: {
                optionLabelField: '${ $.parentName }.option_label',
                rewriteSlugField: '${ $.parentName }.rewrite_slug'
            }
        },

        /**
         * On init, if the form already has an attribute_code (edit mode),
         * fetch options for it.
         */
        initialize: function () {
            this._super();
            if (this.initialAttributeCode) {
                this.loadOptionsFor(this.initialAttributeCode, false);
            }
            return this;
        },

        /**
         * @param {String} code — new attribute_code selection
         */
        onAttributeChange: function (code) {
            this.value('');
            this.loadOptionsFor(code, true);
        },

        /**
         * Fetch options for `code` from cache or AJAX and update the select.
         * @param {String} code
         * @param {Boolean} clearSiblings
         */
        loadOptionsFor: function (code, clearSiblings) {
            var self = this;

            if (!code) {
                self.setOptions([]);
                return;
            }

            if (self.cachedOptions[code]) {
                self.applyOptions(self.cachedOptions[code]);
                return;
            }

            $.ajax({
                url: urlBuilder.build(self.optionsEndpoint),
                method: 'GET',
                data: { attribute_code: code, form_key: window.FORM_KEY },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                var options = (response && Array.isArray(response.options)) ? response.options : [];
                self.cachedOptions[code] = options;
                self.applyOptions(options);
                if (clearSiblings) {
                    self.clearSiblingFields();
                }
            }).fail(function () {
                self.setOptions([]);
            });
        },

        /**
         * @param {Array} options — server response items: {value, label, slug}
         */
        applyOptions: function (options) {
            var cache = {};
            var normalized = options.map(function (o) {
                cache[String(o.value)] = o;
                return { value: String(o.value), label: o.label + ' (' + o.value + ')' };
            });
            this._optionMeta = cache;
            this.setOptions(normalized);
        },

        /**
         * When user picks an option, auto-fill sibling fields.
         * @param {String} value
         */
        onValueChange: function (value) {
            if (!value || !this._optionMeta || !this._optionMeta[value]) {
                return;
            }

            var meta = this._optionMeta[value];

            this.optionLabelField(function (field) {
                if (field && !field.value()) {
                    field.value(meta.label);
                }
            });

            this.rewriteSlugField(function (field) {
                if (field && !field.value()) {
                    field.value(meta.slug);
                }
            });
        },

        /**
         * Clear option_label and rewrite_slug when attribute changes.
         */
        clearSiblingFields: function () {
            this.optionLabelField(function (field) { if (field) { field.value(''); }});
            this.rewriteSlugField(function (field) { if (field) { field.value(''); }});
        }
    });
});

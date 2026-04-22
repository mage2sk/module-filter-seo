/**
 * Panth Filter SEO — dynamic option picker for the Filter URL Rewrite /
 * Filter Page Meta admin forms.
 *
 * Watches the sibling `attribute_code` select. When its value changes,
 * fetches the attribute's options from the admin AJAX endpoint and
 * rebuilds this select's option list. Auto-fills sibling reference
 * fields (`option_label`, `rewrite_slug`) when present.
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
            optionsEndpoint: 'panth_filterseo/filterrewrite/options',
            cachedOptions: {},
            optionMeta: {},
            attributeCode: '',
            imports: {
                attributeCode: '${ $.parentName }.attribute_code:value'
            },
            listens: {
                'value': 'onValueChange'
            },
            modules: {
                labelField: '${ $.parentName }.option_label',
                slugField: '${ $.parentName }.rewrite_slug'
            }
        },

        /**
         * Setter invoked by the `imports` link whenever the sibling
         * attribute_code field's value changes.
         *
         * @param {String} code
         */
        setAttributeCode: function (code) {
            this.attributeCode = code || '';
            this.loadOptions(this.attributeCode);
        },

        /**
         * Fetch the attribute's options (cached) and apply them to this select.
         *
         * @param {String} code
         */
        loadOptions: function (code) {
            var self = this;

            if (!code) {
                this.setOptions([]);
                return;
            }

            if (this.cachedOptions[code]) {
                this.applyOptions(this.cachedOptions[code]);
                return;
            }

            $.ajax({
                url: urlBuilder.build(this.optionsEndpoint),
                method: 'GET',
                data: { attribute_code: code, form_key: window.FORM_KEY },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                var options = (response && Array.isArray(response.options)) ? response.options : [];
                self.cachedOptions[code] = options;
                self.applyOptions(options);
            }).fail(function () {
                self.setOptions([]);
            });
        },

        /**
         * @param {Array<Object>} options items: {value, label, slug}
         */
        applyOptions: function (options) {
            var meta = {};
            var normalized = options.map(function (o) {
                var v = String(o.value);
                meta[v] = o;
                return { value: v, label: o.label + ' (' + v + ')' };
            });

            this.optionMeta = meta;
            this.setOptions(normalized);
        },

        /**
         * When the user picks an option, auto-fill option_label + rewrite_slug
         * if they are empty.
         *
         * @param {String} value
         */
        onValueChange: function (value) {
            var meta;
            if (!value || !(meta = this.optionMeta[value])) {
                return;
            }

            this.labelField(function (field) {
                if (field && !field.value()) {
                    field.value(meta.label);
                }
            });

            this.slugField(function (field) {
                if (field && !field.value()) {
                    field.value(meta.slug);
                }
            });
        }
    });
});

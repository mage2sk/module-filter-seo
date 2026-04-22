/**
 * Panth Filter SEO — dynamic option picker for the Filter URL Rewrite form.
 *
 * Watches the sibling `attribute_code` field and fetches that attribute's
 * options (value + label + slug) from the admin AJAX endpoint. Auto-fills
 * `option_label` and `rewrite_slug` when the user picks an option.
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
            optionMeta: {}
        },

        /**
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.bindToAttributeField();
            this.bindSelfValueChange();
            return this;
        },

        /**
         * Subscribe to the sibling attribute_code field — load on change, and
         * trigger an initial load if a value is already present (edit mode).
         */
        bindToAttributeField: function () {
            var self = this;

            registry.get(this.parentName + '.attribute_code', function (field) {
                if (!field) {
                    return;
                }

                var current = field.value();
                if (current) {
                    self.loadOptionsFor(current, false);
                }

                field.value.subscribe(function (newCode) {
                    self.loadOptionsFor(newCode, true);
                });
            });
        },

        /**
         * When this select's value changes, auto-fill sibling reference fields
         * if they are empty.
         */
        bindSelfValueChange: function () {
            var self = this;
            this.value.subscribe(function (newVal) {
                self.autoFillSiblings(newVal);
            });
        },

        /**
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
                self.applyOptions(self.cachedOptions[code], clearSiblings);
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
                self.applyOptions(options, clearSiblings);
            }).fail(function () {
                self.setOptions([]);
            });
        },

        /**
         * Apply options received from server to this select.
         *
         * @param {Array} options
         * @param {Boolean} clearSiblings
         */
        applyOptions: function (options, clearSiblings) {
            var meta = {};
            var normalized = options.map(function (o) {
                var value = String(o.value);
                meta[value] = o;
                return { value: value, label: o.label + ' (' + value + ')' };
            });

            this.optionMeta = meta;
            this.setOptions(normalized);

            if (clearSiblings) {
                this.clearSiblings();
                this.value('');
            }
        },

        /**
         * @param {String} value
         */
        autoFillSiblings: function (value) {
            if (!value || !this.optionMeta[value]) {
                return;
            }

            var meta = this.optionMeta[value];
            this.setSiblingValue('option_label', meta.label);
            this.setSiblingValue('rewrite_slug', meta.slug);
        },

        /**
         * Clear the reference fields when the attribute changes.
         */
        clearSiblings: function () {
            this.setSiblingValue('option_label', '', true);
            this.setSiblingValue('rewrite_slug', '', true);
        },

        /**
         * Set a sibling field's value. Only overwrites non-empty values when force=true.
         *
         * @param {String} name
         * @param {String} newValue
         * @param {Boolean} force
         */
        setSiblingValue: function (name, newValue, force) {
            var self = this;
            registry.get(this.parentName + '.' + name, function (field) {
                if (!field) {
                    return;
                }
                if (force || !field.value()) {
                    field.value(newValue);
                }
            });
        }
    });
});

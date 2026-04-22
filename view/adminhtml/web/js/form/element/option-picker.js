/**
 * Panth Filter SEO — dynamic option picker for Filter URL Rewrite /
 * Filter Page Meta admin forms.
 *
 * Watches the sibling `attribute_code` field. When its value changes,
 * fetches the attribute's options via AJAX and rebuilds this select's
 * option list. Auto-fills sibling `option_label` and `rewrite_slug`
 * fields when they exist and are empty.
 */
define([
    'Magento_Ui/js/form/element/select',
    'jquery',
    'mage/url',
    'uiRegistry'
], function (Select, $, urlBuilder, registry) {
    'use strict';

    var LOG_PREFIX = '[Panth_FilterSeo option-picker]';

    return Select.extend({
        defaults: {
            optionsEndpoint: 'panth_filterseo/filterrewrite/options',
            cachedOptions: {},
            optionMeta: {},
            // Placeholder shown before an attribute is picked.
            caption: ' '
        },

        /**
         * Subscribe to the sibling attribute_code field and load options for
         * its current value. Uses registry.async so subscription is deferred
         * until the sibling field is registered.
         *
         * @returns {Object}
         */
        initialize: function () {
            this._super();
            this.bindToAttribute();
            this.bindSelfValue();
            return this;
        },

        /**
         * Subscribe to the sibling attribute_code field.
         */
        bindToAttribute: function () {
            var self = this;
            var path = this.parentName + '.attribute_code';

            registry.async(path)(function (field) {
                if (!field) {
                    console.warn(LOG_PREFIX, 'attribute_code field not found at', path);
                    return;
                }
                var initial = field.value();
                console.log(LOG_PREFIX, 'bound to', path, 'initial value:', initial);

                if (initial) {
                    self.loadOptions(initial);
                }

                field.value.subscribe(function (newCode) {
                    console.log(LOG_PREFIX, 'attribute_code changed →', newCode);
                    self.loadOptions(newCode);
                });
            });
        },

        /**
         * Auto-fill sibling reference fields when the user picks an option,
         * and keep the <select> DOM in sync when the value hydrates after
         * our options list does (or vice-versa).
         */
        bindSelfValue: function () {
            var self = this;
            this.value.subscribe(function (newVal) {
                console.log(LOG_PREFIX, 'self.value changed →', JSON.stringify(newVal));

                // Avoid recursion from forceSelect's toggle.
                if (self._isForcing) {
                    return;
                }

                self.autoFillSiblings(newVal);

                if (newVal && self.optionMeta && self.optionMeta[String(newVal)]) {
                    console.log(LOG_PREFIX, 'value in optionMeta → force select');
                    self.forceSelect(String(newVal));
                }
            });
        },

        /**
         * Force the <select> to visually reflect `value` by briefly clearing
         * and re-writing the observable. Works around ko's optgroup binding
         * not re-syncing when options and value update out of order.
         *
         * @param {String} value
         */
        forceSelect: function (value) {
            var self = this;
            setTimeout(function () {
                if (String(self.value()) !== String(value)) {
                    return;
                }
                self._isForcing = true;
                self.value('');
                self.value(value);
                self._isForcing = false;
                console.log(LOG_PREFIX, 'forceSelect committed:', value);
            }, 0);
        },

        /**
         * Poll briefly after options are loaded in case the form data
         * provider hydrates the value observable later than the AJAX that
         * fetched the options. If the ko value.subscribe above catches the
         * update first this loop exits on the first tick.
         */
        pollForValueAfterOptions: function () {
            var self = this;
            var attempts = 0;
            var maxAttempts = 20;
            var tick = function () {
                attempts++;
                var v = self.value();
                if (v && self.optionMeta && self.optionMeta[String(v)]) {
                    console.log(LOG_PREFIX, 'poll tick', attempts, '— value now', v, ', forcing select');
                    self.forceSelect(String(v));
                    return;
                }
                if (attempts < maxAttempts) {
                    setTimeout(tick, 100);
                }
            };
            setTimeout(tick, 50);
        },

        /**
         * Fetch (or reuse cached) options for the given attribute and apply.
         *
         * @param {String} code
         */
        loadOptions: function (code) {
            var self = this;

            if (!code) {
                self.applyOptions([]);
                return;
            }

            if (self.cachedOptions[code]) {
                self.applyOptions(self.cachedOptions[code]);
                return;
            }

            // When `optionsEndpoint` is provided as a pre-built URL via the
            // ui_component XML (xsi:type="url"), use it as-is. Otherwise
            // fall back to mage/url which will not include the admin secret
            // key and will be rejected by backend auth.
            var url = self.optionsEndpoint && self.optionsEndpoint.indexOf('://') !== -1
                ? self.optionsEndpoint
                : urlBuilder.build(self.optionsEndpoint);

            console.log(LOG_PREFIX, 'AJAX', url, 'attribute_code=' + code);

            $.ajax({
                url: url,
                method: 'GET',
                data: { attribute_code: code, form_key: window.FORM_KEY },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                var opts = (response && Array.isArray(response.options)) ? response.options : [];
                console.log(LOG_PREFIX, 'received', opts.length, 'options');
                self.cachedOptions[code] = opts;
                self.applyOptions(opts);
            }).fail(function (jqxhr, textStatus, errorThrown) {
                console.error(LOG_PREFIX, 'AJAX failed:', textStatus, errorThrown);
                self.applyOptions([]);
            });
        },

        /**
         * Normalize + push options into this select's observable.
         * Preserves the currently-selected value when the same value exists
         * in the new options (so edit mode keeps the pre-selected option).
         *
         * @param {Array<Object>} rawOptions server items: {value, label, slug}
         */
        applyOptions: function (rawOptions) {
            var previousValue = this.value();

            var meta = {};
            var normalized = rawOptions.map(function (o) {
                var v = String(o.value);
                meta[v] = o;
                return { value: v, label: o.label + ' (' + v + ')' };
            });

            this.optionMeta = meta;

            if (typeof this.setOptions === 'function') {
                this.setOptions(normalized);
            }
            // Belt + braces: also write the observable directly in case
            // setOptions implementation did not trigger a ko re-render.
            if (typeof this.options === 'function') {
                this.options(normalized);
            }

            // If the previous value is still a valid option, re-write it so ko
            // notifies the <select> binding and keeps the selection visible.
            if (previousValue && meta[String(previousValue)]) {
                this.forceSelect(String(previousValue));
            } else {
                // Value may hydrate after options. Poll briefly.
                this.pollForValueAfterOptions();
            }

            console.log(LOG_PREFIX, 'options observable now has', normalized.length, 'entries, preserved value:', previousValue);
        },

        /**
         * Fill option_label + rewrite_slug when the user picks an option.
         *
         * @param {String} value
         */
        autoFillSiblings: function (value) {
            var meta = value && this.optionMeta[value];
            if (!meta) {
                return;
            }

            var self = this;
            var parent = this.parentName;

            registry.async(parent + '.option_label')(function (field) {
                if (field && !field.value()) {
                    field.value(meta.label);
                }
            });

            registry.async(parent + '.rewrite_slug')(function (field) {
                if (field && !field.value()) {
                    field.value(meta.slug);
                }
            });
        }
    });
});

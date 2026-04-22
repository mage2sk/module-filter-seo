/**
 * Panth Filter SEO — dynamic option picker for the Filter URL Rewrite and
 * Filter Page Meta admin forms.
 *
 * Extends Magento_Ui/js/form/element/select. Watches the sibling
 * `attribute_code` field and fetches that attribute's options via AJAX,
 * then rebuilds this select's option list. Auto-fills `option_label` and
 * `rewrite_slug` siblings when present. Preserves the form-hydrated value
 * across the async options load so edit forms render with the previously
 * saved option pre-selected.
 *
 * @api
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
            optionsEndpoint: '',
            cachedOptions: {},
            optionMeta: {}
        },

        /**
         * Wire up listeners for the sibling attribute_code field and this
         * component's own value observable. Called once per component.
         *
         * @returns {Object} this
         */
        initialize: function () {
            this._super();
            this.bindToAttribute();
            this.bindSelfValue();

            return this;
        },

        /**
         * The parent Magento_Ui/js/form/element/select#validateInitialValue
         * clears the value observable when the initial value is absent from
         * the options list. Our options are populated asynchronously after
         * the sibling attribute_code field is resolved, so at init time the
         * options list is empty and the parent implementation would always
         * wipe a valid form-hydrated value (e.g. an edit-mode option_id).
         *
         * Override to a no-op; the validation that would have happened at
         * init time is deferred to {@link #validateAgainstOptions} which is
         * invoked by {@link #applyOptions} after the AJAX response is
         * processed.
         *
         * @returns {Object} this
         */
        validateInitialValue: function () {
            return this;
        },

        /**
         * Subscribe to the sibling attribute_code field. Loads the initial
         * option list and re-loads on every subsequent attribute change.
         *
         * @returns {void}
         */
        bindToAttribute: function () {
            var self = this;

            registry.async(this.parentName + '.attribute_code')(function (field) {
                if (!field) {
                    return;
                }

                if (field.value()) {
                    self.loadOptions(field.value());
                }

                field.value.subscribe(function (code) {
                    self.loadOptions(code);
                });
            });
        },

        /**
         * Subscribe to this component's value observable so sibling
         * reference fields (option_label, rewrite_slug) auto-fill when the
         * user picks an option, and so the <select> DOM reconciles when
         * the value hydrates after the options load.
         *
         * @returns {void}
         */
        bindSelfValue: function () {
            var self = this;

            this.value.subscribe(function (value) {
                if (self._isForcing) {
                    return;
                }

                self.autoFillSiblings(value);

                if (value && self.optionMeta[String(value)]) {
                    self.forceSelect(String(value));
                }
            });
        },

        /**
         * Fetch the attribute's options from the admin AJAX endpoint
         * (cached per attribute_code) and apply them to this select.
         *
         * @param {String} code attribute_code
         * @returns {void}
         */
        loadOptions: function (code) {
            var self = this;

            if (!code) {
                this.applyOptions([]);
                return;
            }

            if (this.cachedOptions[code]) {
                this.applyOptions(this.cachedOptions[code]);
                return;
            }

            if (!this.optionsEndpoint) {
                return;
            }

            $.ajax({
                url: this.optionsEndpoint,
                method: 'GET',
                data: {
                    attribute_code: code,
                    form_key: window.FORM_KEY
                },
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                var options = (response && Array.isArray(response.options))
                    ? response.options
                    : [];

                self.cachedOptions[code] = options;
                self.applyOptions(options);
            });
        },

        /**
         * Normalize the server response into the `{value, label}` shape
         * Magento's select component expects, push into the options
         * observable, and reconcile the currently-selected value.
         *
         * @param {Array<Object>} rawOptions
         * @returns {void}
         */
        applyOptions: function (rawOptions) {
            var previousValue = this.value();
            var meta = {};
            var normalized = rawOptions.map(function (item) {
                var value = String(item.value);
                meta[value] = item;
                return {
                    value: value,
                    label: item.label + ' (' + value + ')'
                };
            });

            this.optionMeta = meta;
            this.setOptions(normalized);
            this.options(normalized);

            if (previousValue && meta[String(previousValue)]) {
                this.forceSelect(String(previousValue));
            } else {
                // Value may hydrate from the form DataProvider after the
                // AJAX that loaded options. Poll briefly so the selection
                // still appears once hydration completes.
                this.pollForHydratedValue();
            }

            this.validateAgainstOptions();
        },

        /**
         * Apply the validation the parent class would have run at init
         * time: clear the value observable when the current value is not
         * present in the current options list. Runs after the options are
         * populated so a form-hydrated value is not incorrectly wiped.
         *
         * @returns {void}
         */
        validateAgainstOptions: function () {
            var value = this.value();
            if (value && !this.optionMeta[String(value)]) {
                this.value('');
            }
        },

        /**
         * Poll this.value() every 100ms for up to 2s. Exits as soon as
         * the value observable contains a key present in optionMeta — the
         * usual case is the form DataProvider hydrating option_id after
         * the AJAX options response returned.
         *
         * @returns {void}
         */
        pollForHydratedValue: function () {
            var self = this;
            var attempts = 0;
            var maxAttempts = 20;

            var tick = function () {
                attempts++;

                var value = self.value();
                if (value && self.optionMeta[String(value)]) {
                    self.forceSelect(String(value));
                    return;
                }

                if (attempts < maxAttempts) {
                    setTimeout(tick, 100);
                }
            };

            setTimeout(tick, 50);
        },

        /**
         * Force the rendered <select> to reflect the given value. Knockout's
         * optgroup binding does not always reconcile the DOM when both the
         * options array and the value observable update in the same tick,
         * so this helper toggles value '' → value to fire a new mutation.
         *
         * Guarded by `_isForcing` to prevent the value subscription from
         * recursing back into forceSelect on the second write.
         *
         * @param {String} value
         * @returns {void}
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
            }, 0);
        },

        /**
         * When the user picks an option, mirror the selection into the
         * sibling reference fields (option_label, rewrite_slug).
         *
         * option_label is always synced since it is a read-only reference
         * to the chosen option. rewrite_slug is filled only when empty
         * so a user-customised slug (e.g. 'rouge' overriding 'red') is
         * preserved across re-opens and accidental re-selections.
         *
         * @param {String} value
         * @returns {void}
         */
        autoFillSiblings: function (value) {
            var meta = value && this.optionMeta[String(value)];
            if (!meta) {
                return;
            }

            var parent = this.parentName;

            registry.async(parent + '.option_label')(function (field) {
                if (field) {
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

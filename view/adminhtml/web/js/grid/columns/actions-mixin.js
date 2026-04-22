define([], function () {
    'use strict';

    return function (Actions) {
        return Actions.extend({
            isHandlerRequired: function (actionIndex, rowIndex) {
                var action = this.getAction(rowIndex, actionIndex);

                if (action && action.target === '_blank') {
                    return true;
                }

                return this._super(actionIndex, rowIndex);
            },

            defaultCallback: function (actionIndex, recordId, action) {
                if (action && action.target === '_blank') {
                    window.open(action.href, '_blank');
                    return;
                }

                return this._super(actionIndex, recordId, action);
            }
        });
    };
});

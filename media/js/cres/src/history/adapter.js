
export const initAdapter = (History) => {
    // Add the Adapter
    History.Adapter = {
        /**
		 * History.Adapter.handlers[uid][eventName] = Array
		 */
        handlers: {},

        /**
		 * History.Adapter._uid
		 * The current element unique identifier
		 */
        _uid: 1,

        /**
		 * History.Adapter.uid(element)
		 * @param {Element} element
		 * @return {String} uid
		 */
        uid: function (element) {
            // eslint-disable-next-line no-underscore-dangle
            return element._uid || (element._uid = History.Adapter._uid++);
        },

        /**
		 * History.Adapter.bind(el,event,callback)
		 * @param {Element} element
		 * @param {String} eventName - custom and standard events
		 * @param {Function} callback
		 * @return
		 */
        bind: function (element, eventName, callback) {
            // Prepare
            let uid = History.Adapter.uid(element);

            // Apply Listener
            History.Adapter.handlers[uid] = History.Adapter.handlers[uid] || {};
            History.Adapter.handlers[uid][eventName] = History.Adapter.handlers[uid][eventName] || [];
            History.Adapter.handlers[uid][eventName].push(callback);

            // Bind Global Listener
            element['on'+eventName] = (function (element, eventName) {
                return function (event) {
                    History.Adapter.trigger(element, eventName, event);
                };
            }(element, eventName));
        },

        /**
		 * History.Adapter.trigger(el,event)
		 * @param {Element} element
		 * @param {String} eventName - custom and standard events
		 * @param {Object} event - a object of event data
		 * @return
		 */
        trigger: function (element, eventName, event) {
            // Prepare
            event = event || {};
            let uid = History.Adapter.uid(element),
                i, n;

            // Apply Listener
            History.Adapter.handlers[uid] = History.Adapter.handlers[uid] || {};
            History.Adapter.handlers[uid][eventName] = History.Adapter.handlers[uid][eventName] || [];

            // Fire Listeners
            for (i=0, n=History.Adapter.handlers[uid][eventName].length; i<n; ++i) {
                History.Adapter.handlers[uid][eventName][i].apply(this, [event]);
            }
        },

        /**
		 * History.Adapter.extractEventData(key,event,extra)
		 * @param {String} key - key for the event data to extract
		 * @param {String} event - custom and standard events
		 * @return {mixed}
		 */
        extractEventData: function (key, event) {
            let result = (event && event[key]) || undefined;
            return result;
        },

        /**
		 * History.Adapter.onDomLoad(callback)
		 * @param {Function} callback
		 * @return
		 */
        onDomLoad: function (callback) {
            let timeout = window.setTimeout(function () {
                callback();
            }, 2000);
            window.onload = function () {
                clearTimeout(timeout);
                callback();
            };
        }
    };
};

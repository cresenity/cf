import { EventSourceConnection } from '../sse/EventSourceConnection';

/**
 *
 * @param {EventSourceConnection} connection
 */
export default function request(connection) {
    /**
     * @param {string} method
     * @param {string} route
     * @param {object} data
     * @returns {Promise<Response>}
     */
    function create(method, route, data) {
        let csrfToken = null;
        if (typeof document !== 'undefined') {
            const match = document.cookie.match(new RegExp('(^|;\\s*)(XSRF-TOKEN)=([^;]*)'));
            csrfToken = match ? decodeURIComponent(match[3]) : null;
        }

        const fetchRequest = (connectionId) => fetch(route, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Socket-Id': connectionId,
                'X-XSRF-TOKEN': csrfToken
            },
            body: JSON.stringify(data || {})
        }).then((response) => {
            return response.headers.get('content-type') === 'application/json' ? response.json() : response;
        });

        return typeof connection.getId() === 'undefined' ? new Promise((resolve) => {
            connection.afterConnect((connectionId) => {
                resolve(fetchRequest(connectionId));
            });
        }) : fetchRequest(connection.getId());
    }

    const factory = (method) => (route, data) => create(method, route, data);

    return {
        get: factory('GET'),
        post: factory('POST'),
        put: factory('PUT'),
        delete: factory('DELETE')
    };
}

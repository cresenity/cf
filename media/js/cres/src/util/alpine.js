
export function getComponentName(element) {
    return (
        element.getAttribute('x-title') ||
        element.getAttribute('x-id') ||
        element.id ||
        element.getAttribute('name') ||
        findLiveViewName(element) ||
        element.getAttribute('aria-label') ||
        extractFunctionName(element.getAttribute('x-data')) ||
        element.getAttribute('role') ||
        element.tagName.toLowerCase()
    );
}

function findLiveViewName(alpineEl) {
    const phxEl = alpineEl.closest('[data-phx-view]');
    if (phxEl) {
        // pretty sure we could do the following instead
        // return phxEl.dataset.phxView;
        if (!window.liveSocket.getViewByEl) {return;}
        const view = window.liveSocket.getViewByEl(phxEl);
        return view && view.name;
    }
}

function extractFunctionName(functionName) {
    if (functionName.startsWith('{')) {return;}
    return functionName
        .replace(/\(([^\)]+)\)/, '') // Handles myFunction(param)
        .replace('()', '');
}


/**
 * Semver version check
 *
 * @param {string} required
 * @param {string} actual
 * @returns {boolean}
 */
export function isRequiredVersion(required, actual) {
    if (required === actual) {return true;}
    const requiredArray = required.split('.').map((v) => parseInt(v, 10));
    const currentArray = actual.split('.').map((v) => parseInt(v, 10));
    for (let i = 0; i < requiredArray.length; i++) {
        if (currentArray[i] < requiredArray[i]) {
            return false;
        }
        if (currentArray[i] > requiredArray[i]) {
            return true;
        }
    }
    return true;
}

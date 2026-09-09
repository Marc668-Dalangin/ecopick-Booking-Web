(function () {
    const pollers = new Map();

    function normalizeOptions(options) {
        return {
            key: options.key || options.url || 'live-update',
            url: options.url,
            method: options.method || 'GET',
            interval: Number(options.interval || 5000),
            skipWhenEditing: options.skipWhenEditing !== false,
            headers: options.headers || {},
            body: options.body || null,
            onSuccess: typeof options.onSuccess === 'function' ? options.onSuccess : null,
            onError: typeof options.onError === 'function' ? options.onError : null
        };
    }

    function shouldSkipRefresh() {
        const active = document.activeElement;
        if (!active) return false;

        return ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName);
    }

    function parseJsonResponse(response) {
        return response.text().then((text) => {
            if (!text) {
                return {};
            }

            try {
                return JSON.parse(text);
            } catch (error) {
                return {};
            }
        });
    }

    function handleResponse(payload, response, options) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        if (payload.session_expired) {
            if (payload.redirect) {
                window.location.href = payload.redirect;
            }
            return;
        }

        if (payload.success === false && payload.message && options.onError) {
            options.onError(payload, response);
            return;
        }

        if (options.onSuccess) {
            options.onSuccess(payload, response);
        }
    }

    function runPoller(options) {
        if (!options.url) {
            return;
        }

        if (document.hidden || (options.skipWhenEditing && shouldSkipRefresh())) {
            return;
        }

        const requestOptions = {
            method: options.method,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            }
        };

        if (options.body && options.method && options.method.toUpperCase() !== 'GET') {
            requestOptions.body = JSON.stringify(options.body);
            requestOptions.headers['Content-Type'] = 'application/json; charset=UTF-8';
        }

        fetch(options.url, requestOptions)
            .then(async (response) => {
                const payload = await parseJsonResponse(response);
                if (!response.ok && payload.message) {
                    handleResponse(payload, response, options);
                    return;
                }

                handleResponse(payload, response, options);
            })
            .catch((error) => {
                if (options.onError) {
                    options.onError({ success: false, message: 'Unable to refresh data.', error: error.message || 'network_error' }, null);
                }
            });
    }

    function startPolling(options) {
        const normalized = normalizeOptions(options);
        const existing = pollers.get(normalized.key);

        if (existing && existing.timerId) {
            clearInterval(existing.timerId);
        }

        const instance = {
            ...normalized,
            timerId: null
        };

        const tick = () => runPoller(instance);
        tick();
        instance.timerId = window.setInterval(tick, normalized.interval);
        pollers.set(normalized.key, instance);

        return instance;
    }

    function stopPolling(key) {
        const instance = pollers.get(key);
        if (!instance || !instance.timerId) {
            return;
        }

        clearInterval(instance.timerId);
        pollers.delete(key);
    }

    function stopAllPolling() {
        pollers.forEach((instance) => {
            if (instance.timerId) {
                clearInterval(instance.timerId);
            }
        });
        pollers.clear();
    }

    function resumePolling() {
        pollers.forEach((instance) => {
            if (instance.timerId) {
                return;
            }
            instance.timerId = window.setInterval(() => runPoller(instance), instance.interval);
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            pollers.forEach((instance) => {
                if (instance.timerId) {
                    clearInterval(instance.timerId);
                    instance.timerId = null;
                }
            });
            return;
        }

        resumePolling();
        pollers.forEach((instance) => runPoller(instance));
    });

    window.EcoPickLiveUpdates = {
        startPolling,
        stopPolling,
        stopAllPolling,
        resumePolling,
        pollers
    };
})();

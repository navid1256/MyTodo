export async function sendFormRequest(urlOrFormData, formDataOrOptions = {}, maybeOptions = {}) {
    let url = '/tasks/create';
    let formData;
    let options = {};

    if (typeof urlOrFormData === 'string') {
        url = urlOrFormData;
        formData = formDataOrOptions;
        options = maybeOptions || {};
    } else {
        formData = urlOrFormData;
        options = formDataOrOptions || {};
        const action = formData.get('action');
        if (action === 'toggleTaskCompletion') {
            url = '/tasks/toggle';
        } else if (action === 'previewReminders') {
            url = '/reminders/preview';
        } else if (action === 'updateNotification') {
            url = '/notifications/update';
        } else if (action === 'cancelNotification') {
            url = '/notifications/cancel';
        } else if (action === 'changePassword') {
            url = '/auth/change-password';
        } else {
            url = '/tasks/create';
        }
    }

    const requestOptions = {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    };

    if (options.acceptJson) {
        requestOptions.headers.Accept = 'application/json';
    }

    if (options.signal) {
        requestOptions.signal = options.signal;
    }

    const response = await fetch(url, requestOptions);

    return {
        ok: response.ok,
        status: response.status,
        text: await response.text()
    };
}

export async function sendJsonFormRequest(urlOrFormData, formDataOrOptions = {}, maybeOptions = {}) {
    const options = typeof urlOrFormData === 'string' ? (maybeOptions || {}) : (formDataOrOptions || {});
    const fallbackMessage = options.errorMessage || 'The request failed.';
    const requestOptions = {
        ...options,
        acceptJson: true
    };
    const response = typeof urlOrFormData === 'string'
        ? await sendFormRequest(urlOrFormData, formDataOrOptions, requestOptions)
        : await sendFormRequest(urlOrFormData, requestOptions);
    let responseData;

    try {
        responseData = JSON.parse(response.text);
    } catch (error) {
        throw new Error(response.text || fallbackMessage);
    }

    if (!responseData || typeof responseData !== 'object') {
        throw new Error(fallbackMessage);
    }

    if (!response.ok || !responseData.success) {
        const requestError = new Error(responseData.message || fallbackMessage);

        if (typeof responseData.code === 'string') {
            requestError.code = responseData.code;
        }

        throw requestError;
    }

    return responseData;
}

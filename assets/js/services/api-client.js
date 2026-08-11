const AJAX_ENDPOINT = 'bootstrap/ajaxHandler.php';

export async function sendFormRequest(formData, options = {}) {
    const requestOptions = {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    };

    if (options.signal) {
        requestOptions.signal = options.signal;
    }

    const response = await fetch(AJAX_ENDPOINT, requestOptions);

    return {
        ok: response.ok,
        text: await response.text()
    };
}

export async function sendJsonFormRequest(formData, options = {}) {
    const fallbackMessage = options.errorMessage || 'The request failed.';
    const response = await sendFormRequest(formData, options);
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
        throw new Error(responseData.message || fallbackMessage);
    }

    return responseData;
}

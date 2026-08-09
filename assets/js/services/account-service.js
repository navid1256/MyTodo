export async function changePassword(formData) {
    formData.set('action', 'changePassword');

    const response = await fetch('bootstrap/ajaxHandler.php', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    });
    const responseText = await response.text();
    let responseData;

    try {
        responseData = JSON.parse(responseText);
    } catch (error) {
        throw new Error(responseText || 'The password could not be changed.');
    }

    if (!response.ok || !responseData.success) {
        throw new Error(responseData.message || 'The password could not be changed.');
    }

    return responseData;
}

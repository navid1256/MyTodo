export async function createTask(formData) {
  const response = await fetch('bootstrap/ajaxHandler.php', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData
  });

  const responseText = await response.text();

  if (!response.ok || responseText.trim() !== '1') {
    throw new Error(responseText || 'The task could not be saved.');
  }

  return responseText;
}

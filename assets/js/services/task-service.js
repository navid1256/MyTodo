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

export async function previewTaskReminders(data, signal) {
  const formData = new FormData();
  formData.set('action', 'previewReminders');
  formData.set('csrf_token', data.csrfToken);
  formData.set('due_at', data.dueAt);
  formData.set('has_time', data.hasTime);
  formData.set('reminders', JSON.stringify(data.reminders));

  const response = await fetch('bootstrap/ajaxHandler.php', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    },
    body: formData,
    signal: signal
  });

  const responseText = await response.text();
  let responseData;

  try {
    responseData = JSON.parse(responseText);
  } catch (error) {
    throw new Error(responseText || 'The reminder time could not be calculated.');
  }

  if (!response.ok || !responseData.success) {
    throw new Error(responseData.message || 'The reminder time could not be calculated.');
  }

  return responseData.reminders;
}

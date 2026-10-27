function responseError(response, data) {
  const error = new Error(data?.error || `HTTP ${response.status}`);
  error.status = response.status;
  error.code = data?.code;
  return error;
}

async function readResponse(response) {
  try {
    return await response.json();
  } catch {
    const error = new Error('Сервер вернул некорректный ответ. Обновите страницу и повторите запрос.');
    error.status = response.status >= 400 ? response.status : 502;
    throw error;
  }
}

export async function requestApi({ path, method = 'GET', body, getSession, setSession, fetchImpl = fetch }) {
  const serializedBody = body === undefined ? undefined : JSON.stringify(body);
  const send = csrf => fetchImpl(`/api${path}`, {
    method,
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
    ...(serializedBody === undefined ? {} : { body: serializedBody })
  });

  let response = await send(getSession()?.csrf);
  let data = await readResponse(response);

  if (response.status === 403 && data?.code === 'csrf_invalid' && getSession()?.user?.id) {
    const previousSession = getSession();
    let refreshed = null;
    try {
      const refreshResponse = await fetchImpl('/api/session', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      if (refreshResponse.ok) refreshed = await refreshResponse.json();
    } catch {
      // Keep the original CSRF rejection if the session endpoint is unavailable.
    }

    if (refreshed?.csrf && !refreshed.sessionExpired && refreshed.user?.id === previousSession.user.id) {
      setSession({ ...previousSession, ...refreshed });
      response = await send(refreshed.csrf);
      data = await readResponse(response);
    } else {
      const error = new Error('Сессия изменилась или истекла. Повторный вход нужен в той же вкладке; текущие несохранённые изменения оставлены в редакторе.');
      error.status = 401;
      error.code = 'session_refresh_required';
      throw error;
    }
  }

  if (!response.ok) throw responseError(response, data);
  return data;
}

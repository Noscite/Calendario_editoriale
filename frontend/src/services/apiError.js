/**
 * Estrae un messaggio d'errore leggibile da una Response fetch fallita.
 *
 * Il body di una risposta di errore non è garantito JSON: un 502/504 arriva
 * dalla pagina HTML di nginx, non da Laravel. Parsarlo con res.json() solleva
 * un'eccezione che maschera l'errore vero — su Safari con il criptico
 * "The string did not match the expected pattern.".
 */

const GATEWAY_TIMEOUT_MESSAGE =
  'Il server ci sta mettendo più del previsto. ' +
  "L'operazione potrebbe essere comunque andata a buon fine: ricarica la pagina prima di riprovare.";

export async function apiErrorMessage(response, fallback = 'Errore imprevisto') {
  if (response.status === 502 || response.status === 504) {
    return GATEWAY_TIMEOUT_MESSAGE;
  }

  let body = '';
  try {
    body = await response.text();
  } catch {
    return fallback;
  }

  let data;
  try {
    data = JSON.parse(body);
  } catch {
    return fallback;
  }

  return data?.error?.message || data?.detail || data?.message || fallback;
}

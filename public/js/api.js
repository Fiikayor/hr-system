/**
 * Shared API client for the HR System frontend.
 * Every page includes this before its own page-specific script.
 */

// Derived dynamically from wherever this page is served, so it keeps working
// no matter what port/folder name you're running XAMPP on.
const API_BASE_URL = `${window.location.origin}${window.location.pathname.replace(/\/[^/]*$/, '')}/api`;

const TOKEN_KEY = 'hr_token';
const USER_KEY = 'hr_user';

function getToken() {
  return localStorage.getItem(TOKEN_KEY);
}

function getUser() {
  const raw = localStorage.getItem(USER_KEY);
  return raw ? JSON.parse(raw) : null;
}

function setSession(token, user) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

function clearSession() {
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
}

/** Redirect to login if there's no token. Call this at the top of any protected page. */
function requireAuth() {
  if (!getToken()) {
    window.location.href = 'login.html';
  }
}

/**
 * Core request helper. Attaches the bearer token automatically unless auth: false.
 * Throws on API errors so callers can try/catch and read err.code / err.message.
 */
async function apiRequest(path, { method = 'GET', body = null, auth = true } = {}) {
  const headers = { 'Content-Type': 'application/json' };

  if (auth) {
    const token = getToken();
    if (token) headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  let json;
  try {
    json = await response.json();
  } catch {
    throw new Error('The server returned an unexpected response.');
  }

  if (!json.success) {
    const err = new Error(json.error?.message || 'Something went wrong.');
    err.code = json.error?.code;
    err.status = response.status;
    throw err;
  }

  return json.data;
}

/** For binary responses like PDFs — apiRequest() assumes JSON, this doesn't. */
async function fetchBlob(path) {
  const token = getToken();
  const headers = {};
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const response = await fetch(`${API_BASE_URL}${path}`, { headers });

  if (!response.ok) {
    throw new Error('Could not load the file.');
  }

  return response.blob();
}
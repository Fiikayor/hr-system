// --- Live clock in the ledger panel ---
function tickClock() {
  const now = new Date();
  const time = now.toLocaleTimeString('en-GB', { hour12: false });
  document.getElementById('liveClock').textContent = time;
}
tickClock();
setInterval(tickClock, 1000);

// --- If already logged in, skip the login page entirely ---
if (getToken()) {
  routeToDashboard(getUser());
}

function routeToDashboard(user) {
  const routes = {
    admin: 'admin-dashboard.html',
    manager: 'manager-dashboard.html',
    employee: 'employee-dashboard.html',
  };
  window.location.href = routes[user?.role] || 'employee-dashboard.html';
}

// --- Form handling ---
const form = document.getElementById('loginForm');
const errorBanner = document.getElementById('errorBanner');
const submitBtn = document.getElementById('submitBtn');
const submitLabel = document.getElementById('submitLabel');

function showError(message) {
  errorBanner.textContent = message;
  errorBanner.classList.remove('hidden');
}

function hideError() {
  errorBanner.classList.add('hidden');
}

function setLoading(isLoading) {
  submitBtn.disabled = isLoading;
  submitLabel.textContent = isLoading ? 'Signing in…' : 'Sign in';
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  hideError();

  const email = document.getElementById('email').value.trim();
  const password = document.getElementById('password').value;

  if (!email || !password) {
    showError('Enter both your email and password.');
    return;
  }

  setLoading(true);

  try {
    const data = await apiRequest('/auth/login', {
      method: 'POST',
      auth: false,
      body: { email, password },
    });

    setSession(data.token, data.user);
    routeToDashboard(data.user);
  } catch (err) {
    showError(err.message || 'Could not sign in. Check your details and try again.');
    setLoading(false);
  }
});
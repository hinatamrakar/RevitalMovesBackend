<?php

require_once 'includes/auth.php';

startSecureSession();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: loginpage.html');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Revital Moves — Dashboard</title>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: sans-serif;
      background: #f0f0f0;
      color: #111;
      min-height: 100vh;
      display: flex;
    }

    /* Sidebar */
    #sidebar {
      width: 220px;
      min-height: 100vh;
      background: #111;
      color: #fff;
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
    }

    .sidebar-brand {
      padding: 24px 20px 20px;
      font-size: 15px;
      font-weight: 600;
      letter-spacing: 0.02em;
      border-bottom: 1px solid #2a2a2a;
    }

    .sidebar-brand span { font-weight: 300; opacity: 0.5; }

    nav { padding: 16px 0; flex: 1; }

    nav a {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 20px;
      font-size: 14px;
      color: #aaa;
      text-decoration: none;
      cursor: pointer;
      transition: background 0.15s, color 0.15s;
      border-left: 3px solid transparent;
    }

    nav a:hover { background: #1c1c1c; color: #fff; }
    nav a.active { background: #1c1c1c; color: #fff; border-left-color: #fff; }
    nav a svg { flex-shrink: 0; }

    .sidebar-footer {
      padding: 16px 20px;
      border-top: 1px solid #2a2a2a;
      font-size: 13px;
      color: #666;
    }

    .sidebar-footer strong { display: block; color: #ccc; margin-bottom: 8px; font-weight: 500; }

    .btn-logout {
      background: none;
      border: 1px solid #333;
      color: #888;
      padding: 7px 14px;
      font-size: 13px;
      border-radius: 5px;
      cursor: pointer;
      width: 100%;
      transition: background 0.15s, color 0.15s;
    }

    .btn-logout:hover { background: #1c1c1c; color: #fff; border-color: #555; }

    /* Main */
    #main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    header {
      background: #fff;
      padding: 16px 28px;
      border-bottom: 1px solid #e5e5e5;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    header h1 { font-size: 17px; font-weight: 600; }
    header p  { font-size: 13px; color: #888; margin-top: 2px; }

    /* Content */
    #content { padding: 28px; overflow-y: auto; flex: 1; }

    .section { display: none; }
    .section.active { display: block; }

    /* Stat cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      padding: 18px 20px;
    }

    .stat-card .label { font-size: 12px; color: #888; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-card .value { font-size: 28px; font-weight: 600; }
    .stat-card .sub   { font-size: 12px; color: #aaa; margin-top: 4px; }

    /* Tables */
    .table-wrap {
      background: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 8px;
      overflow: hidden;
    }

    .table-header {
      padding: 16px 20px;
      border-bottom: 1px solid #e5e5e5;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .table-header h2 { font-size: 15px; font-weight: 600; }

    table { width: 100%; border-collapse: collapse; font-size: 14px; }

    th {
      text-align: left;
      padding: 10px 20px;
      font-size: 12px;
      color: #888;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      background: #fafafa;
      border-bottom: 1px solid #e5e5e5;
    }

    td { padding: 13px 20px; border-bottom: 1px solid #f0f0f0; color: #222; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafafa; }

    /* Status badges */
    .status {
      display: inline-block;
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      font-weight: 500;
    }

    .status.active      { background: #e8f5e9; color: #2e7d32; }
    .status.inactive    { background: #f5f5f5; color: #757575; }
    .status.new         { background: #e3f2fd; color: #1565c0; }
    .status.reviewed    { background: #fff8e1; color: #f57f17; }
    .status.shortlisted { background: #ede7f6; color: #4527a0; }
    .status.hired       { background: #e8f5e9; color: #2e7d32; }
    .status.rejected    { background: #fce4ec; color: #c62828; }

    /* Buttons */
    .btn { padding: 8px 16px; font-size: 13px; border-radius: 5px; cursor: pointer; font-weight: 500; transition: background 0.15s; }
    .btn-primary { background: #111; color: #fff; border: none; }
    .btn-primary:hover { background: #333; }

    .btn-sm {
      padding: 5px 11px;
      font-size: 12px;
      border-radius: 4px;
      cursor: pointer;
      border: 1px solid #ddd;
      background: #fff;
      color: #444;
    }
    .btn-sm:hover { background: #f5f5f5; }

    .btn-danger-sm {
      padding: 5px 11px;
      font-size: 12px;
      border-radius: 4px;
      cursor: pointer;
      border: 1px solid #f5c6cb;
      background: #fff5f5;
      color: #c62828;
    }
    .btn-danger-sm:hover { background: #fce4ec; }

    /* Filter bar */
    .filter-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }

    .filter-bar input,
    .filter-bar select {
      padding: 8px 12px;
      font-size: 13px;
      border: 1px solid #ddd;
      border-radius: 5px;
      outline: none;
    }

    .filter-bar input:focus,
    .filter-bar select:focus { border-color: #888; }

    /* Pagination */
    .pagination {
      display: flex;
      gap: 6px;
      padding: 14px 20px;
      border-top: 1px solid #f0f0f0;
      align-items: center;
      font-size: 13px;
      color: #888;
    }

    .page-btn {
      padding: 5px 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background: #fff;
      cursor: pointer;
      font-size: 13px;
    }

    .page-btn:hover { background: #f5f5f5; }
    .page-btn.active { background: #111; color: #fff; border-color: #111; }
    .page-btn:disabled { opacity: 0.4; cursor: default; }

    /* Empty / loading */
    .empty-state { text-align: center; padding: 48px 20px; color: #aaa; font-size: 14px; }
    .loading     { color: #aaa; font-size: 14px; padding: 20px; text-align: center; }

  </style>
</head>
<body>

<!-- Sidebar -->
<div id="sidebar">
  <div class="sidebar-brand">Revital <span>Moves</span></div>
  <nav>
    <a class="active" onclick="navigate('overview')">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
      Overview
    </a>
    <a onclick="navigate('jobs')">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      Jobs
    </a>
    <a onclick="navigate('applications')">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Applications
    </a>
    <a onclick="navigate('settings')">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 17v.01"/><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Settings
    </a>
  </nav>
  <div class="sidebar-footer">
    <strong><?= htmlspecialchars($_SESSION['email']) ?></strong>
    <button class="btn-logout" onclick="logout()">Sign out</button>
  </div>
</div>

<!-- Main -->
<div id="main">
  <header>
    <div>
      <h1 id="page-title">Overview</h1>
      <p id="page-sub">Sub Heading</p>
    </div>
  </header>

  <div id="content">

    <!-- Overview -->
    <div id="section-overview" class="section active">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="label">Total Jobs</div>
          <div class="value" id="stat-jobs">—</div>
          <div class="sub">All listings</div>
        </div>
        <div class="stat-card">
          <div class="label">Active Jobs</div>
          <div class="value" id="stat-active">—</div>
          <div class="sub">Currently live</div>
        </div>
        <div class="stat-card">
          <div class="label">Applications</div>
          <div class="value" id="stat-apps">—</div>
          <div class="sub">All time</div>
        </div>
        <div class="stat-card">
          <div class="label">Shortlisted</div>
          <div class="value" id="stat-shortlisted">—</div>
          <div class="sub">Pending action</div>
        </div>
      </div>

      <div class="table-wrap">
        <div class="table-header">
          <h2>Recent Jobs</h2>
          <button class="btn btn-primary" onclick="navigate('jobs')">View all</button>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Status</th>
              <th>Posted</th>
            </tr>
          </thead>
          <tbody id="recent-jobs-body">
            <tr><td colspan="3" class="loading">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Jobs -->
    <div id="section-jobs" class="section">
      <div class="filter-bar">
        <input type="text" id="jobs-search" placeholder="Search title…" oninput="filterJobs()" style="flex:1; min-width:180px;" />
        <select id="jobs-status" onchange="filterJobs()">
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
        <button class="btn btn-primary">+ Add Job</button>
      </div>
      <div class="table-wrap">
        <div class="table-header">
          <h2>All Jobs</h2>
          <span id="jobs-count" style="font-size:13px;color:#888;"></span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Job Type</th>
              <th style="width: 250px;">Description</th>
              <th>Status</th>
              <th>Posted</th>
              <th>Responsibilities</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="jobs-body">
            <tr><td colspan="4" class="loading">Loading…</td></tr>
          </tbody>
        </table>
        <div class="pagination" id="jobs-pagination"></div>
      </div>
    </div>

    <!-- Applications -->
    <div id="section-applications" class="section">
      <div class="filter-bar">
        <input type="text" id="apps-search" placeholder="Search applicant…" oninput="filterApps()" style="flex:1; min-width:180px;" />
        <select id="apps-status" onchange="filterApps()">
          <option value="">All statuses</option>
          <option value="new">New</option>
          <option value="reviewed">Reviewed</option>
          <option value="shortlisted">Shortlisted</option>
          <option value="hired">Hired</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="table-wrap">
        <div class="table-header">
          <h2>Applications</h2>
          <span id="apps-count" style="font-size:13px;color:#888;"></span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Job</th>
              <th>Applicant</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
              <th>Applied</th>
              <th>Resume</th>
              <th>Cover Letter</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="apps-body">
            <tr><td colspan="5" class="loading">Loading…</td></tr>
          </tbody>
        </table>
        <div class="pagination" id="apps-pagination"></div>
      </div>
    </div>

    <!-- Settings -->
    <div id="section-settings" class="section">
      <div class="table-wrap" style="max-width:600px;">
        <div class="table-header">
          <h2>Change Password</h2>
        </div>
        <div style="padding:20px;">
          <div style="margin-bottom:15px;">
            <label>Current Password</label>
            <input
              type="password"
              id="current-password"
              style="width:100%;padding:10px;margin-top:5px;"
            />
          </div>

          <div style="margin-bottom:15px;">
            <label>New Password</label>
            <input
              type="password"
              id="new-password"
              style="width:100%;padding:10px;margin-top:5px;"
            />
          </div>

          <div style="margin-bottom:20px;">
            <label>Confirm New Password</label>
            <input
              type="password"
              id="confirm-password"
              style="width:100%;padding:10px;margin-top:5px;"
            />
          </div>

          <button class="btn btn-primary" onclick="changePassword()">
            Update Password
          </button>

          <div id="password-message" style="margin-top:15px;"></div>
        </div>

      </div>
    </div>

  </div>
</div>

<script>
  const API = 'http://localhost/revital_moves/backend';

  const pages = {
    overview: { title: 'Overview', sub: 'Sub heading' },
    jobs: { title: 'Jobs', sub: 'Manage job listings' },
    applications: { title: 'Applications', sub: 'Review submitted applications' },
    settings: { title: 'Change Password', sub: 'Update your account password' },
  };

  function navigate(page) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('nav a').forEach(a => a.classList.remove('active'));
    document.getElementById('section-' + page).classList.add('active');
    document.querySelectorAll('nav a').forEach(a => {
      if (a.getAttribute('onclick')?.includes(page)) a.classList.add('active');
    });
    document.getElementById('page-title').textContent = pages[page].title;
    document.getElementById('page-sub').textContent   = pages[page].sub;
    if (page === 'overview')     loadOverview();
    if (page === 'jobs')         loadJobs();
    if (page === 'applications') loadApplications();
  }

  function fmt(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  }

  function statusBadge(s) {
    const cls = (s ?? '').toLowerCase().replace(/\s+/g, '');
    return `<span class="status ${cls}">${s ?? '—'}</span>`;
  }

  // Overview
  async function loadOverview() {
    try {
      const [jobsRes, appsRes] = await Promise.all([
        axios.get(`${API}/jobs/get_all_jobs.php`, { withCredentials: true }),
        axios.get(`${API}/job_application/get_all_applications.php`, { withCredentials: true }),
      ]);

      const jobs = jobsRes.data.jobs ?? jobsRes.data.data ?? [];
      const apps = appsRes.data.applications ?? appsRes.data.data ?? [];

      document.getElementById('stat-jobs').textContent        = jobs.length;
      document.getElementById('stat-active').textContent      = jobs.filter(j => j.status?.toLowerCase() === 'active').length;
      document.getElementById('stat-apps').textContent        = apps.length;
      document.getElementById('stat-shortlisted').textContent = apps.filter(a => a.status?.toLowerCase() === 'shortlisted').length;

      const recent = jobs.slice(0, 5);
      document.getElementById('recent-jobs-body').innerHTML = recent.length
        ? recent.map(j => `
            <tr>
              <td>${j.title ?? '—'}</td>
              <td>${statusBadge(j.status)}</td>
              <td>${fmt(j.created_at)}</td>
            </tr>`).join('')
        : `<tr><td colspan="3" class="empty-state">No jobs found.</td></tr>`;

    } catch (err) {
      console.error(err);
    }
  }

  // Jobs
  let allJobs = [];
  let jobPage = 1;
  const JOB_PAGE_SIZE = 8;

  async function loadJobs() {
    document.getElementById('jobs-body').innerHTML = '<tr><td colspan="4" class="loading">Loading…</td></tr>';
    try {
      const res = await axios.get(`${API}/jobs/get_all_jobs.php`, { withCredentials: true });
      allJobs = res.data.jobs ?? res.data.data ?? [];
      filterJobs();
    } catch (err) {
      console.error(err);
      document.getElementById('jobs-body').innerHTML = '<tr><td colspan="4" class="empty-state">Failed to load jobs.</td></tr>';
    }
  }

  function filterJobs() { jobPage = 1; renderJobs(); }

  function renderJobs() {
    const q      = document.getElementById('jobs-search').value.toLowerCase();
    const status = document.getElementById('jobs-status').value.toLowerCase();

    const filtered = allJobs.filter(j => {
      const matchQ = !q      || (j.title ?? '').toLowerCase().includes(q);
      const matchS = !status || (j.status ?? '').toLowerCase() === status;
      return matchQ && matchS;
    });

    document.getElementById('jobs-count').textContent = `${filtered.length} result${filtered.length !== 1 ? 's' : ''}`;

    const start = (jobPage - 1) * JOB_PAGE_SIZE;
    const paged = filtered.slice(start, start + JOB_PAGE_SIZE);

    document.getElementById('jobs-body').innerHTML = paged.length
      ? paged.map(j => `
          <tr>
            <td>${j.title ?? '—'}</td>
            <td>${j.job_type ?? '—'}</td>
            <td style="width: 250px;">${j.description ?? '—'}</td>
            <td>${statusBadge(j.status)}</td>
            <td>${fmt(j.created_at)}</td>
            <td>
              ${
                j.responsibilities?.length
                  ? `<ul>
                    ${j.responsibilities
                        .map(r => `<li>${r.responsibility}</li>`)
                        .join('')}
                    </ul>`
                  : '—'
              }
            </td>
            <td style="display:flex;gap:6px;">
              <button class="btn-sm" >Edit</button>
              <button class="btn-danger-sm">Delete</button>
            </td>
          </tr>`).join('')
      : `<tr><td colspan="4" class="empty-state">No jobs match your filters.</td></tr>`;

    renderPagination('jobs-pagination', filtered.length, JOB_PAGE_SIZE, jobPage, p => { jobPage = p; renderJobs(); });
  }

  // Applications
  let allApps = [];
  let appPage = 1;
  const APP_PAGE_SIZE = 8;

  async function loadApplications() {
    document.getElementById('apps-body').innerHTML = '<tr><td colspan="5" class="loading">Loading…</td></tr>';
    try {
      const res = await axios.get(`${API}/job_application/get_all_applications.php`, { withCredentials: true });
      allApps = res.data.applications ?? res.data.data ?? [];
      filterApps();
    } catch (err) {
      console.error(err);
      document.getElementById('apps-body').innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load applications.</td></tr>';
    }
  }

  function filterApps() { appPage = 1; renderApps(); }

  function renderApps() {
    const q      = document.getElementById('apps-search').value.toLowerCase();
    const status = document.getElementById('apps-status').value.toLowerCase();

    const filtered = allApps.filter(a => {
      const name   = (a.applicant_name ?? a.name ?? '').toLowerCase();
      const matchQ = !q      || name.includes(q);
      const matchS = !status || (a.status ?? '').toLowerCase() === status;
      return matchQ && matchS;
    });

    document.getElementById('apps-count').textContent = `${filtered.length} result${filtered.length !== 1 ? 's' : ''}`;

    const start = (appPage - 1) * APP_PAGE_SIZE;
    const paged = filtered.slice(start, start + APP_PAGE_SIZE);

    document.getElementById('apps-body').innerHTML = paged.length
      ? paged.map(a => `
          <tr>
            <td>${a.job_title ?? a.title ?? '—'}</td>
            <td>${a.applicant_name ?? a.name ?? '—'}</td>
            <td>${a.email ?? a.email ?? '—'}</td>
            <td>${a.phone ?? a.phone ?? '—'}</td>
            <td>${statusBadge(a.status)}</td>
            <td>${fmt(a.created_at ?? a.applied_at)}</td>
            <td>
              <button class="btn-sm" onclick="window.location.href='${API}/job_application/download_resume.php?id=${a.id}'">Resume</button>
            </td>
            <td>
              <button class="btn-sm" onclick="window.location.href='${API}/job_application/download_cover_letter.php?id=${a.id}'">Cover Letter</button>
            </td>
            <td style="display:flex;gap:6px;">
              <button class="btn-sm">View</button>
              <button class="btn-danger-sm">Delete</button>
            </td>
          </tr>`).join('')
      : `<tr><td colspan="5" class="empty-state">No applications match your filters.</td></tr>`;

    renderPagination('apps-pagination', filtered.length, APP_PAGE_SIZE, appPage, p => { appPage = p; renderApps(); });
  }

  // Pagination
  function renderPagination(containerId, total, pageSize, current, onPage) {
    const el    = document.getElementById(containerId);
    const pages = Math.ceil(total / pageSize);
    if (pages <= 1) { el.innerHTML = ''; return; }

    let html = `<span>${(current - 1) * pageSize + 1}–${Math.min(current * pageSize, total)} of ${total}</span>`;
    html += `<button class="page-btn" onclick="(${onPage.toString()})(${current - 1})" ${current === 1 ? 'disabled' : ''}>‹</button>`;
    for (let i = 1; i <= pages; i++) {
      html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="(${onPage.toString()})(${i})">${i}</button>`;
    }
    html += `<button class="page-btn" onclick="(${onPage.toString()})(${current + 1})" ${current === pages ? 'disabled' : ''}>›</button>`;
    el.innerHTML = html;
  }

  // Change password
  async function changePassword() {
    const currentPassword =
      document.getElementById('current-password').value;

    const newPassword =
      document.getElementById('new-password').value;

    const confirmPassword =
      document.getElementById('confirm-password').value;

    const msg = document.getElementById('password-message');

    msg.innerHTML = '';

    if (newPassword !== confirmPassword) {
      msg.style.color = 'red';
      msg.textContent = 'Passwords do not match.';
      return;
    }

    try {
      const res = await axios.post(
        `${API}/auth/change_password.php`,
        {
          current_password: currentPassword,
          new_password: newPassword,
          confirm_password: confirmPassword,
        },
        {
          withCredentials: true
        }
      );

      msg.style.color = 'green';
      msg.textContent =
        res.data.message || 'Password updated successfully.';

      document.getElementById('current-password').value = '';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password').value = '';

    } catch (err) {
      msg.style.color = 'red';
      msg.textContent =
        err.response?.data?.message || 'Failed to update password.';
    }
  }

  // Logout
  async function logout() {
    try {
      await axios.post(`${API}/auth/logout.php`, {}, { withCredentials: true });
    } catch (_) {}
    window.location.href = 'loginpage.html';
  }

  // Init
  loadOverview();
</script>
</body>
</html>
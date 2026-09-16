<?php
require_once 'auth.php';
checkPageAccess('SystemLog.php');
$userLevel = getUserAccessLevel();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>  
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SYSTEMLOG.EXE — HMS</title>
<meta name="description" content="HMS System Activity Log - Terminal Interface"/>
<style>
/* ── Terminal Style Reset ───────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#000000;
  --fg:#cccccc;
  --fg-dim:#888888;
  --fg-bright:#ffffff;
  --border:#808080;
  --error:#ff6b6b;
  --warning:#ffdd57;
  --info:#aaaaaa;
  --gray:#666666;
}
html,body{height:100%;background:var(--bg);color:var(--fg);font-family:'Courier New',monospace;font-size:13px;line-height:1.4;overflow-x:hidden}
a{color:var(--fg-bright);text-decoration:none}
button{cursor:pointer;font-family:'Courier New',monospace}

/* ── Layout ────────────────────────────────────────────────── */
.app{display:flex;flex-direction:column;min-height:100vh;padding:10px}

/* ── Top Bar ────────────────────────────────────────────────── */
.topbar{
  border:1px solid var(--border);
  padding:8px 12px;
  margin-bottom:10px;
  background:var(--bg);
}
.topbar-logo{display:inline;color:var(--fg-bright)}
.topbar-logo .logo-icon{display:none}
.topbar-title{display:inline;margin:0 20px}
.topbar-title h1{display:inline;font-size:13px;font-weight:bold;color:var(--fg)}
.topbar-title p{display:inline;font-size:13px;color:var(--fg-dim);margin-left:10px}
.topbar-title p:before{content:" | "}
.topbar-actions{float:right}
.btn{display:inline;padding:2px 8px;border:1px solid var(--border);font-size:12px;background:var(--bg);color:var(--fg);margin-left:5px}
.btn:hover{background:var(--fg);color:var(--bg)}
.btn-primary{border-color:var(--fg-bright);color:var(--fg-bright)}
.btn-primary:hover{background:var(--fg-bright);color:var(--bg)}
.btn-ghost{border-color:var(--fg-dim);color:var(--fg-dim)}
.btn-ghost:hover{background:var(--fg-dim);color:var(--bg)}
.btn-danger{border-color:var(--error);color:var(--error)}
.btn-danger:hover{background:var(--error);color:var(--bg)}
.back-btn{display:inline;padding:2px 8px;color:var(--fg);font-size:12px;border:1px solid var(--border);background:var(--bg);margin-left:5px}
.back-btn:hover{background:var(--fg);color:var(--bg)}

/* ── Main content ───────────────────────────────────────────── */
.main{flex:1;padding:0;max-width:100%;margin:0}

/* ── Stats cards ────────────────────────────────────────────── */
.stats-grid{display:block;margin-bottom:10px;border:1px solid var(--border);padding:8px}
.stat-card{
  display:inline-block;
  padding:5px 10px;
  border-right:1px solid var(--border);
  margin:0;
}
.stat-card:last-child{border-right:none}
.stat-card:before{display:none}
.stat-card .stat-icon{display:none}
.stat-card .stat-value{display:inline;font-size:13px;font-weight:bold;color:var(--fg-bright)}
.stat-card .stat-label{display:inline;font-size:13px;color:var(--fg-dim);margin-left:5px}
.stat-card .stat-label:before{content:"["}
.stat-card .stat-label:after{content:"]"}
.stat-card.total .stat-value{color:var(--fg-bright)}
.stat-card.activities .stat-value{color:var(--fg-bright)}
.stat-card.errors .stat-value{color:var(--error)}
.stat-card.warnings .stat-value{color:var(--warning)}
.stat-card.today .stat-value{color:var(--fg-bright)}

/* ── Filters panel ──────────────────────────────────────────── */
.filter-panel{
  border:1px solid var(--border);
  padding:8px 12px;
  margin-bottom:10px;
  background:var(--bg);
}
.filter-group{display:inline-block;margin-right:15px;margin-bottom:5px}
.filter-group label{font-size:12px;font-weight:bold;color:var(--fg);margin-right:5px}
.filter-group label:after{content:":"}
.filter-group select,
.filter-group input{
  background:var(--bg);border:1px solid var(--border);
  color:var(--fg);font-family:'Courier New',monospace;font-size:12px;padding:2px 6px;
  outline:none;
}
.filter-group select:focus,
.filter-group input:focus{border-color:var(--fg-bright)}
.filter-group select option{background:var(--bg);color:var(--fg)}
.filter-search{display:inline-block;margin-right:15px}
.filter-search input{width:200px}
.filter-actions{display:inline-block}
.filter-actions .btn{margin-left:5px}
.refresh-dot{display:inline-block;width:6px;height:6px;background:var(--fg);margin-right:3px}
.auto-refresh-label{display:inline;font-size:12px;color:var(--fg)}

/* ── Log table ──────────────────────────────────────────────── */
.table-wrapper{
  border:1px solid var(--border);
  background:var(--bg);
}
.table-header{
  padding:6px 12px;border-bottom:1px solid var(--border);
  background:var(--bg);
}
.table-header h2{display:inline;font-size:13px;font-weight:bold;color:var(--fg-bright)}
.table-meta{float:right;font-size:12px;color:var(--fg-dim)}
.log-table{width:100%;border-collapse:collapse;table-layout:fixed}
.log-table th{
  padding:6px 8px;text-align:left;font-size:12px;font-weight:bold;
  color:var(--fg-bright);
  background:var(--bg);border-bottom:1px solid var(--border);
}
.log-table td{
  padding:6px 8px;border-bottom:1px solid var(--border);
  font-size:12px;color:var(--fg);
  word-wrap:break-word;
  overflow:hidden;
}
.log-table tr:last-child td{border-bottom:none}
.log-table tbody tr:hover{background:#1a1a1a}

/* ── Badges ─────────────────────────────────────────────────── */
.badge{display:inline;padding:1px 4px;border:1px solid;font-size:11px;font-weight:bold}
.badge-activity{border-color:var(--fg);color:var(--fg)}
.badge-error{border-color:var(--error);color:var(--error)}
.badge-warning{border-color:var(--warning);color:var(--warning)}

/* ── Category chips ──────────────────────────────────────────── */
.chip{display:inline;padding:1px 4px;font-size:11px;font-weight:normal;border:1px solid var(--border);color:var(--fg)}
.chip-booking,.chip-update,.chip-delete,.chip-checkout,.chip-cash,.chip-menu,.chip-cancellation,.chip-payment,.chip-auth,.chip-general{
  border-color:var(--border);color:var(--fg)
}

/* ── Description cell ────────────────────────────────────────── */
.desc-cell{max-width:300px}
.desc-main{color:var(--fg-bright);font-size:12px;line-height:1.3}
.desc-meta{display:block;margin-top:3px}
.meta-item{display:inline;font-size:11px;color:var(--fg-dim);margin-right:8px}
.meta-key{color:var(--fg-dim)}
.meta-val{color:var(--fg);font-family:'Courier New',monospace;font-size:11px}
.meta-key:after{content:":"}

/* ── Action column ───────────────────────────────────────────── */
.action-cell{font-family:'Courier New',monospace;font-size:12px;color:var(--fg-bright);font-weight:bold}

/* ── User cell ───────────────────────────────────────────────── */
.user-cell{display:block}
.user-avatar{display:none}
.user-name{font-size:12px;color:var(--fg)}

/* ── Time cell ───────────────────────────────────────────────── */
.time-cell{white-space:nowrap;font-size:11px}
.time-date{color:var(--fg)}
.time-hour{color:var(--fg-dim);font-size:11px}

/* ── Pagination ──────────────────────────────────────────────── */
.pagination{padding:8px 12px;border-top:1px solid var(--border);background:var(--bg)}
.pag-info{display:inline;font-size:12px;color:var(--fg-dim)}
.pag-controls{display:inline;float:right}
.pag-btn{padding:2px 6px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font-size:11px;margin-left:3px}
.pag-btn:hover:not(:disabled){background:var(--fg);color:var(--bg)}
.pag-btn:disabled{opacity:.3;cursor:not-allowed}
.pag-btn.active{background:var(--fg-bright);border-color:var(--fg-bright);color:var(--bg)}
.pag-pages{display:inline}

/* ── Empty state ─────────────────────────────────────────────── */
.empty-state{text-align:center;padding:40px 20px}
.empty-icon{display:none}
.empty-title{font-size:13px;color:var(--fg-bright);margin-bottom:6px}
.empty-title:before{content:"[!] "}
.empty-sub{font-size:12px;color:var(--fg-dim)}

/* ── Loading spinner ─────────────────────────────────────────── */
.spinner-wrap{padding:40px;text-align:center}
.spinner{display:inline-block;color:var(--fg)}
.spinner:after{content:"LOADING..."}

/* ── Toast ───────────────────────────────────────────────────── */
.toast-container{position:fixed;bottom:10px;right:10px;z-index:9999}
.toast{padding:6px 10px;border:1px solid;font-size:12px;margin-top:5px;background:var(--bg)}
.toast-success{border-color:var(--fg);color:var(--fg)}
.toast-error{border-color:var(--error);color:var(--error)}

/* ── Scrollbar ───────────────────────────────────────────────── */
::-webkit-scrollbar{width:10px;height:10px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:var(--border)}
::-webkit-scrollbar-thumb:hover{background:var(--fg-dim)}

/* ── Responsive ──────────────────────────────────────────────── */
@media(max-width:900px){
  .log-table th:nth-child(5),
  .log-table td:nth-child(5){display:none}
}
@media(max-width:600px){
  .log-table th:nth-child(4),
  .log-table td:nth-child(4){display:none}
}

/* ── Modal Styles ────────────────────────────────────────────── */
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.9);z-index:9999;display:flex;align-items:center;justify-content:center}
.modal-box{background:var(--bg);border:2px solid var(--border);padding:20px;min-width:400px;max-width:500px}
.modal-title{font-size:14px;font-weight:bold;color:var(--fg-bright);margin-bottom:15px;text-align:center;border-bottom:1px solid var(--border);padding-bottom:10px}
.modal-content{margin:15px 0}
.modal-label{display:block;font-size:12px;color:var(--fg);margin-bottom:5px}
.modal-input{width:100%;background:var(--bg);border:1px solid var(--border);color:var(--fg);font-family:'Courier New',monospace;font-size:13px;padding:8px;box-sizing:border-box}
.modal-input:focus{outline:none;border-color:var(--fg-bright)}
.modal-buttons{display:flex;gap:10px;margin-top:15px}
.modal-btn{flex:1;padding:8px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font-family:'Courier New',monospace;font-size:12px;cursor:pointer;font-weight:bold}
.modal-btn:hover{background:var(--fg);color:var(--bg)}
.modal-btn-danger{border-color:var(--error);color:var(--error)}
.modal-btn-danger:hover{background:var(--error);color:var(--bg)}
.modal-info{font-size:11px;color:var(--fg-dim);margin:10px 0;line-height:1.4}
</style>
</head>
<body>
<div class="app">

  <!-- TOP BAR -->
  <header class="topbar">
    <div class="topbar-logo">
      <span>HMS</span>
    </div>
    <div class="topbar-title">
      <h1>ERRORLOG.EXE</h1>
    </div>
    <div class="topbar-actions">
      <div class="auto-refresh-label">
        <span class="refresh-dot" id="refreshDot"></span>
        <span id="refreshLabel">AUTO</span>
      </div>
      <button class="btn btn-ghost" onclick="toggleAutoRefresh()" id="refreshToggle">PAUSE</button>
      <button class="btn btn-danger" onclick="logoutAllAccounts()" style="border:1px solid #f87171;" title="Force logout all user accounts">ALL ACCOUNT LOGOUT</button>
      <button class="btn btn-primary" onclick="showLogoutModal()">LOGOUT</button>
      <a href="Report.php" class="back-btn">EXIT</a>
    </div>

  </header>

  <!-- MAIN -->
  <main class="main">

    <!-- STATS CARDS -->
    <div class="stats-grid" id="statsGrid">
      <div class="stat-card total">
        <div class="stat-value" id="statTotal">—</div>
        <div class="stat-label">TOTAL</div>
      </div>
      <div class="stat-card activities">
        <div class="stat-value" id="statActivity">—</div>
        <div class="stat-label">OK</div>
      </div>
      <div class="stat-card errors">
        <div class="stat-value" id="statErrors">—</div>
        <div class="stat-label">ERR</div>
      </div>
      <div class="stat-card warnings">
        <div class="stat-value" id="statWarnings">—</div>
        <div class="stat-label">WARN</div>
      </div>
      <div class="stat-card today">
        <div class="stat-value" id="statToday">—</div>
        <div class="stat-label">TODAY</div>
      </div>
    </div>

    <!-- FILTER PANEL -->
    <div class="filter-panel">
      <div class="filter-group">
        <label>TYPE</label>
        <select id="fType" onchange="applyFilters()">
          <option value="">ALL</option>
          <option value="activity">activity</option>
          <option value="error">error</option>
          <option value="warning">warning</option>
        </select>
      </div>
      <div class="filter-group">
        <label>CAT</label>
        <select id="fCategory" onchange="applyFilters()">
          <option value="">ALL</option>
          <option value="booking">booking</option>
          <option value="update">update</option>
          <option value="delete">delete</option>
          <option value="checkout">checkout</option>
          <option value="cash">cash</option>
          <option value="menu">menu</option>
          <option value="cancellation">cancellation</option>
          <option value="payment">payment</option>
          <option value="auth">auth</option>
          <option value="report">report</option>
          <option value="general">general</option>
        </select>
      </div>
      <div class="filter-group">
        <label>FROM</label>
        <input type="date" id="fDateFrom" onchange="applyFilters()"/>
      </div>
      <div class="filter-group">
        <label>TO</label>
        <input type="date" id="fDateTo" onchange="applyFilters()"/>
      </div>
      <div class="filter-search">
        <label>SEARCH</label>
        <input type="text" id="fSearch" placeholder="FILTER..." oninput="debounceSearch()"/>
      </div>
      <div class="filter-actions">
        <button class="btn btn-ghost" onclick="clearFilters()">CLR</button>
        <button class="btn btn-primary" onclick="applyFilters()">GO</button>
      </div>
    </div>

    <!-- LOG TABLE -->
    <div class="table-wrapper">
      <div class="table-header">
        <h2 id="tableTitle">SYSTEM.LOG</h2>
        <span class="table-meta" id="tableMeta">LOADING...</span>
      </div>

      <div id="tableBody">
        <div class="spinner-wrap"><div class="spinner"></div></div>
      </div>

      <div class="pagination" id="paginationBar" style="display:none"></div>
    </div>

  </main>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- LOGOUT MODAL -->
<div class="modal-overlay" id="logoutModal" style="display:none">
  <div class="modal-box">
    <div class="modal-title">SYSTEM LOGOUT</div>
    <div class="modal-content">
      <div class="modal-info">
        [USER] <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?><br>
        [SESSION] Active<br>
        [ACTION] Choose logout option
      </div>
    </div>
    <div class="modal-buttons">
      <button class="modal-btn" onclick="showTurnoverModal()">TURNOVER</button>
      <button class="modal-btn" onclick="confirmBreak()">BREAK</button>
      <button class="modal-btn" onclick="closeLogoutModal()">CANCEL</button>
    </div>
  </div>
</div>

<!-- TURNOVER MODAL -->
<div class="modal-overlay" id="turnoverModal" style="display:none">
  <div class="modal-box">
    <div class="modal-title">CASH TURNOVER</div>
    <div class="modal-content">
      <div class="modal-info">
        [USER] <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?><br>
        [ACTION] Enter cash amount for turnover
      </div>
      <div style="margin-top:15px">
        <label class="modal-label">CASH AMOUNT:</label>
        <input type="number" class="modal-input" id="cashAmount" placeholder="0.00" step="0.01" min="0">
      </div>
      <div style="margin-top:10px">
        <label class="modal-label">TOTAL AMOUNT:</label>
        <input type="number" class="modal-input" id="totalAmount" placeholder="0.00" step="0.01" min="0">
      </div>
    </div>
    <div class="modal-buttons">
      <button class="modal-btn" onclick="submitTurnover()">SUBMIT</button>
      <button class="modal-btn" onclick="backToLogoutModal()">BACK</button>
    </div>
  </div>
</div>

<!-- CONFIRM BREAK MODAL -->
<div class="modal-overlay" id="breakModal" style="display:none">
  <div class="modal-box">
    <div class="modal-title">CONFIRM BREAK</div>
    <div class="modal-content">
      <div class="modal-info">
        [WARNING] Taking a break will pause your session.<br>
        [USER] <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?><br>
        [ACTION] Confirm to proceed
      </div>
    </div>
    <div class="modal-buttons">
      <button class="modal-btn modal-btn-danger" onclick="submitBreak()">CONFIRM</button>
      <button class="modal-btn" onclick="backToLogoutModal()">CANCEL</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────
let currentPage   = 1;
let totalPages    = 1;
let perPage       = 50;
let autoRefresh   = true;
let refreshTimer  = null;
let searchDebounce= null;
let lastData      = null;

// ── Icons by category ──────────────────────────────────────────
const CAT_ICONS = {
  booking:'booking',update:'update',delete:'delete',checkout:'checkout',
  cash:'cash',menu:'menu',cancellation:'cancellation',payment:'payment',
  auth:'auth',general:'general',report:'report'
};

// ── Category chip class ────────────────────────────────────────
function catClass(cat){
  const map={booking:'booking',update:'update',delete:'delete',checkout:'checkout',
    cash:'cash',menu:'menu',cancellation:'cancellation',payment:'payment',auth:'auth'};
  return 'chip-'+(map[cat]||'general');
}

// ── User avatar initials ───────────────────────────────────────
function initials(name){
  if(!name||name==='system')return'SYS';
  return name.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
}

// ── Format datetime ────────────────────────────────────────────
function fmtDt(dt){
  if(!dt)return'—';
  const d=new Date(dt.replace(' ','T'));
  const date=d.toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'});
  const time=d.toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
  return `<div class="time-date">${date}</div><div class="time-hour">${time}</div>`;
}

// ── Build query params ─────────────────────────────────────────
function buildParams(pg=1){
  const p=new URLSearchParams();
  const t=document.getElementById('fType').value;
  const c=document.getElementById('fCategory').value;
  const df=document.getElementById('fDateFrom').value;
  const dt=document.getElementById('fDateTo').value;
  const s=document.getElementById('fSearch').value.trim();
  if(t)p.set('type',t);
  if(c)p.set('category',c);
  if(df)p.set('date_from',df);
  if(dt)p.set('date_to',dt);
  if(s)p.set('search',s);
  p.set('page',pg);
  p.set('per_page',perPage);
  return p;
}

// ── Fetch & render ─────────────────────────────────────────────
async function loadLogs(pg=1){
  currentPage=pg;
  const body=document.getElementById('tableBody');
  body.innerHTML='<div class="spinner-wrap"><div class="spinner"></div></div>';
  document.getElementById('paginationBar').style.display='none';

  try{
    const r=await fetch('get_system_logs.php?'+buildParams(pg));
    const d=await r.json();
    if(!d.success){showToast('Failed to load logs: '+(d.message||'Unknown error'),'error');return;}
    lastData=d;
    updateStats(d.stats);
    renderTable(d);
    renderPagination(d);
  }catch(e){
    showToast('Network error loading logs','error');
    body.innerHTML='<div class="empty-state"><div class="empty-icon">⚠️</div><div class="empty-title">Connection failed</div><div class="empty-sub">'+e.message+'</div></div>';
  }
}

// ── Update stat cards ──────────────────────────────────────────
function updateStats(s){
  if(!s)return;
  document.getElementById('statTotal').textContent=parseInt(s.total)||0;
  document.getElementById('statActivity').textContent=parseInt(s.activities)||0;
  document.getElementById('statErrors').textContent=parseInt(s.errors)||0;
  document.getElementById('statWarnings').textContent=parseInt(s.warnings)||0;
  document.getElementById('statToday').textContent=parseInt(s.today)||0;
}

// ── Render table rows ──────────────────────────────────────────
function renderTable(d){
  const total=parseInt(d.total)||0;
  const from=((d.page-1)*d.per_page)+1;
  const to=Math.min(d.page*d.per_page,total);
  document.getElementById('tableMeta').textContent=
    total===0?'NO RECORDS':`${from}-${to}/${total}`;
  document.getElementById('tableTitle').textContent=
    `SYSTEM.LOG`;

  if(!d.rows||d.rows.length===0){
    document.getElementById('tableBody').innerHTML=`
      <div class="empty-state">
        <div class="empty-title">NO LOGS FOUND</div>
        <div class="empty-sub">Adjust filters or wait for activity.</div>
      </div>`;
    return;
  }

  let html=`<div style="overflow-x:auto"><table class="log-table">
    <thead><tr>
      <th>ID</th>
      <th>TYPE</th>
      <th>CAT</th>
      <th>ACTION</th>
      <th>DESCRIPTION</th>
      <th>USER</th>
      <th>TIMESTAMP</th>
    </tr></thead><tbody>`;

  d.rows.forEach((row,i)=>{
    const icon=CAT_ICONS[row.category]||'general';
    const meta=row.metadata&&Object.keys(row.metadata).length>0
      ?'<div class="desc-meta">'+Object.entries(row.metadata).slice(0,4).map(([k,v])=>
          `<span class="meta-item"><span class="meta-key">${escHtml(k)}</span> <span class="meta-val">${escHtml(String(v).substring(0,40))}</span></span>`
        ).join('')+'</div>'
      :'';

    const typeLabel=row.log_type==='activity'?'OK':row.log_type==='error'?'ERR':'WARN';

    html+=`<tr>
      <td style="color:var(--gray);font-size:11px">${escHtml(String(row.id))}</td>
      <td><span class="badge badge-${escHtml(row.log_type)}">[${typeLabel}]</span></td>
      <td><span class="chip ${catClass(row.category)}">${escHtml(icon)}</span></td>
      <td class="action-cell">${escHtml(row.action)}</td>
      <td class="desc-cell"><div class="desc-main">${escHtml(row.description)}</div>${meta}</td>
      <td>
        <div class="user-cell">
          <span class="user-name" title="${escHtml(row.username)}">${escHtml(row.username)}</span>
        </div>
      </td>
      <td class="time-cell">${fmtDt(row.created_at)}</td>
    </tr>`;
  });

  html+='</tbody></table></div>';
  document.getElementById('tableBody').innerHTML=html;
}

function logTypeDot(type){
  return type==='activity'?'●':type==='error'?'●':'●';
}

// ── Pagination ─────────────────────────────────────────────────
function renderPagination(d){
  totalPages=d.total_pages||1;
  const bar=document.getElementById('paginationBar');
  if(totalPages<=1){bar.style.display='none';return;}
  bar.style.display='block';

  const total=parseInt(d.total)||0;
  const from=((d.page-1)*d.per_page)+1;
  const to=Math.min(d.page*d.per_page,total);

  let pages='';
  const range=3;
  const start=Math.max(1,d.page-range);
  const end=Math.min(totalPages,d.page+range);
  if(start>1){pages+=`<button class="pag-btn" onclick="loadLogs(1)">1</button>`;if(start>2)pages+=`<span style="color:var(--gray)">...</span>`;}
  for(let p=start;p<=end;p++){
    pages+=`<button class="pag-btn${p===d.page?' active':''}" onclick="loadLogs(${p})">${p}</button>`;
  }
  if(end<totalPages){if(end<totalPages-1)pages+=`<span style="color:var(--gray)">...</span>`;pages+=`<button class="pag-btn" onclick="loadLogs(${totalPages})">${totalPages}</button>`;}

  bar.innerHTML=`
    <span class="pag-info">REC ${from}-${to}/${total}</span>
    <div class="pag-controls">
      <button class="pag-btn" onclick="loadLogs(${d.page-1})" ${d.page<=1?'disabled':''}>PREV</button>
      <div class="pag-pages">${pages}</div>
      <button class="pag-btn" onclick="loadLogs(${d.page+1})" ${d.page>=totalPages?'disabled':''}>NEXT</button>
    </div>`;
}

// ── Filters ────────────────────────────────────────────────────
function applyFilters(){currentPage=1;loadLogs(1);}
function clearFilters(){
  ['fType','fCategory'].forEach(id=>document.getElementById(id).value='');
  ['fDateFrom','fDateTo','fSearch'].forEach(id=>document.getElementById(id).value='');
  applyFilters();
}
function debounceSearch(){
  clearTimeout(searchDebounce);
  searchDebounce=setTimeout(()=>applyFilters(),400);
}

// ── Auto refresh ───────────────────────────────────────────────
function startRefresh(){
  if(refreshTimer)clearInterval(refreshTimer);
  refreshTimer=setInterval(()=>{if(autoRefresh)loadLogs(currentPage);},30000);
}
function toggleAutoRefresh(){
  autoRefresh=!autoRefresh;
  document.getElementById('refreshDot').style.background=autoRefresh?'var(--fg)':'var(--gray)';
  document.getElementById('refreshLabel').textContent=autoRefresh?'AUTO':'OFF';
  document.getElementById('refreshToggle').textContent=autoRefresh?'PAUSE':'START';
}

// ── Export CSV ─────────────────────────────────────────────────
async function exportCSV(){
  showToast('PREPARING EXPORT...','success');
  try{
    const p=buildParams(1);
    p.set('per_page','5000');
    const r=await fetch('get_system_logs.php?'+p);
    const d=await r.json();
    if(!d.success||!d.rows){showToast('EXPORT FAILED','error');return;}
    const cols=['id','log_type','category','action','description','username','affected_id','ip_address','created_at'];
    const lines=[cols.join(',')];
    d.rows.forEach(row=>{
      lines.push(cols.map(c=>{
        let v=row[c]??'';
        if(typeof v==='object')v=JSON.stringify(v);
        v=String(v).replace(/"/g,'""');
        return '"'+v+'"';
      }).join(','));
    });
    const blob=new Blob([lines.join('\n')],{type:'text/csv'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='system_logs_'+new Date().toISOString().slice(0,10)+'.csv';
    a.click();
    showToast('EXPORTED '+d.rows.length+' ROWS','success');
  }catch(e){showToast('EXPORT ERROR','error');}
}

// ── Toast ──────────────────────────────────────────────────────
function showToast(msg,type='success'){
  const c=document.getElementById('toastContainer');
  const t=document.createElement('div');
  t.className=`toast toast-${type}`;
  const prefix=type==='success'?'[OK]':'[ERR]';
  t.innerHTML=prefix+' '+escHtml(msg);
  c.appendChild(t);
  setTimeout(()=>t.remove(),3500);
}

// ── Escape HTML ────────────────────────────────────────────────
function escHtml(s){
  const d=document.createElement('div');d.textContent=String(s??'');return d.innerHTML;
}

// ── Set default dates (today) ──────────────────────────────────
function setDefaultDates(){
  const today=new Date().toISOString().slice(0,10);
  // Default: show today only
  document.getElementById('fDateFrom').value=today;
  document.getElementById('fDateTo').value=today;
}

// ── Init ───────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  setDefaultDates();
  loadLogs(1);
  startRefresh();
});

// ── Logout Modal Functions ─────────────────────────────────────
function showLogoutModal(){
  document.getElementById('logoutModal').style.display='flex';
}

function closeLogoutModal(){
  document.getElementById('logoutModal').style.display='none';
}

function showTurnoverModal(){
  document.getElementById('logoutModal').style.display='none';
  document.getElementById('turnoverModal').style.display='flex';
  document.getElementById('cashAmount').value='';
  document.getElementById('totalAmount').value='';
  document.getElementById('cashAmount').focus();
}

function confirmBreak(){
  document.getElementById('logoutModal').style.display='none';
  document.getElementById('breakModal').style.display='flex';
}

function backToLogoutModal(){
  document.getElementById('turnoverModal').style.display='none';
  document.getElementById('breakModal').style.display='none';
  document.getElementById('logoutModal').style.display='flex';
}

async function submitTurnover(){
  const cashAmt=parseFloat(document.getElementById('cashAmount').value)||0;
  const totalAmt=parseFloat(document.getElementById('totalAmount').value)||0;
  
  if(totalAmt<=0){
    showToast('TOTAL AMOUNT REQUIRED','error');
    return;
  }
  
  try{
    const r=await fetch('process_logout.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`action=turnover&cash_amount=${cashAmt}&total_amount=${totalAmt}`
    });
    const d=await r.json();
    
    if(d.success){
      showToast('TURNOVER RECORDED','success');
      setTimeout(()=>{
        window.location.href='logout.php';
      },1500);
    }else{
      showToast('TURNOVER FAILED: '+d.message,'error');
    }
  }catch(e){
    showToast('NETWORK ERROR','error');
  }
}

async function submitBreak(){
  try{
    const r=await fetch('process_logout.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:'action=break'
    });
    const d=await r.json();
    
    if(d.success){
      showToast('BREAK RECORDED','success');
      setTimeout(()=>{
        window.location.href='logout.php';
      },1500);
    }else{
      showToast('BREAK FAILED: '+d.message,'error');
    }
  }catch(e){
    showToast('NETWORK ERROR','error');
  }
}

async function logoutAllAccounts(){
  if(!confirm('ARE YOU SURE YOU WANT TO LOG OUT ALL ACCOUNTS?\nThis will terminate all active user sessions across the system.')) return;
  
  try{
    const r=await fetch('logout_all_accounts.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'}
    });
    const d=await r.json();
    
    if(d.success){
      window.location.href='Login.html?logout=all_accounts';
    }else{
      showToast('LOGOUT ALL FAILED: '+(d.message||'Error'),'error');
    }
  }catch(e){
    window.location.href='Login.html?logout=all_accounts';
  }
}

</script>

</body>
</html>


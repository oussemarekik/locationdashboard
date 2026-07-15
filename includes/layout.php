<?php
function renderHeader(string $title = '', string $active = ''): void {
    $appName = APP_NAME;
    $flash = getFlash();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title ?: $appName) ?> — <?= $appName ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
    --brand:#1a56db;
    --brand-dark:#1342b3;
    --brand-light:#e8f0ff;
    --success:#059669;
    --warning:#d97706;
    --danger:#dc2626;
    --sidebar-bg:#0f172a;
    --sidebar-text:#94a3b8;
    --sidebar-active:#1a56db;
    --bg:#f8fafc;
    --card:#ffffff;
    --border:#e2e8f0;
    --text:#1e293b;
    --text-muted:#64748b;
    --radius:10px;
    --shadow:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
    --shadow-md:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);display:flex;min-height:100vh;font-size:14px}

/* ---- SIDEBAR ---- */
.sidebar{width:240px;min-height:100vh;background:var(--sidebar-bg);display:flex;flex-direction:column;position:fixed;top:0;left:0;z-index:100;transition:.3s}
.sidebar-logo{padding:20px 20px 16px;border-bottom:1px solid #1e293b}
.sidebar-logo h1{font-size:16px;font-weight:700;color:#fff;letter-spacing:.5px}
.sidebar-logo small{font-size:11px;color:var(--sidebar-text);font-family:'JetBrains Mono',monospace}
.sidebar nav{flex:1;padding:12px 0;overflow-y:auto}
.nav-section{padding:16px 20px 6px;font-size:10px;text-transform:uppercase;letter-spacing:1.5px;color:#475569;font-weight:600}
.nav-item{display:flex;align-items:center;gap:10px;padding:9px 20px;color:var(--sidebar-text);text-decoration:none;font-size:13px;font-weight:500;transition:.15s;border-left:3px solid transparent}
.nav-item:hover{background:#1e293b;color:#e2e8f0}
.nav-item.active{background:#1e2d4a;color:#fff;border-left-color:var(--sidebar-active)}
.nav-item svg{width:16px;height:16px;flex-shrink:0;opacity:.8}
.sidebar-footer{padding:16px 20px;border-top:1px solid #1e293b;font-size:11px;color:#475569}

/* ---- MAIN ---- */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--card);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;box-shadow:var(--shadow)}
.topbar h2{font-size:18px;font-weight:600;color:var(--text)}
.topbar-actions{display:flex;gap:8px;align-items:center}
.content{padding:24px 28px;flex:1}

/* ---- BUTTONS ---- */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:.15s;white-space:nowrap}
.btn svg{width:14px;height:14px}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-dark)}
.btn-success{background:var(--success);color:#fff}
.btn-warning{background:var(--warning);color:#fff}
.btn-danger{background:var(--danger);color:#fff}
.btn-light{background:#f1f5f9;color:var(--text);border:1px solid var(--border)}.btn-light:hover{background:#e2e8f0}
.btn-sm{padding:5px 10px;font-size:12px;border-radius:6px}
.btn-outline{background:transparent;border:1.5px solid var(--brand);color:var(--brand)}.btn-outline:hover{background:var(--brand-light)}

/* ---- CARDS ---- */
.card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);box-shadow:var(--shadow);overflow:hidden}
.card-header{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#fafafa}
.card-header h3{font-size:14px;font-weight:600;color:var(--text)}
.card-body{padding:20px}

/* ---- STATS ---- */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:var(--radius);border:1px solid var(--border);padding:20px;box-shadow:var(--shadow)}
.stat-card .label{font-size:12px;color:var(--text-muted);font-weight:500;text-transform:uppercase;letter-spacing:.5px}
.stat-card .value{font-size:28px;font-weight:700;margin:6px 0 4px;font-family:'JetBrains Mono',monospace}
.stat-card .sub{font-size:12px;color:var(--text-muted)}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:12px}

/* ---- TABLE ---- */
.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
thead{background:#f8fafc}
th{padding:10px 14px;text-align:left;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)}
td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafafe}
.mono{font-family:'JetBrains Mono',monospace;font-size:12px}

/* ---- FORMS ---- */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:5px}
.form-group label{font-size:12px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px}
.form-control{padding:9px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:'Inter',sans-serif;color:var(--text);transition:.15s;background:var(--card)}
.form-control:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px rgba(26,86,219,.15)}
select.form-control{cursor:pointer}
textarea.form-control{resize:vertical;min-height:80px}
.form-section{margin-bottom:24px}
.form-section-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--brand);margin-bottom:14px;padding-bottom:8px;border-bottom:2px solid var(--brand-light)}

/* ---- LIGNE ARTICLE ---- */
.lignes-table th{background:#e8f0ff;color:var(--brand)}
.lignes-table tfoot td{background:#f0fdf4;font-weight:700}
#lignes-body tr:hover td{background:#f0f7ff}
.remove-ligne{background:none;border:none;color:#94a3b8;cursor:pointer;padding:4px;border-radius:4px}.remove-ligne:hover{color:var(--danger);background:#fee2e2}

/* ---- ALERTS ---- */
.alert{padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.alert-info{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd}

/* ---- BADGES ---- */
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}

/* ---- PRINT ---- */
@media print{
    .sidebar,.topbar,.no-print{display:none!important}
    .main{margin-left:0}
    body{background:#fff}
    .card{border:none;box-shadow:none}
}
@media(max-width:900px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .sidebar{transform:translateX(-240px)}
    .main{margin-left:0}
}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h1>📦 <?= APP_NAME ?></h1>
        <small>Gestion Location Matériel</small>
    </div>
    <nav>
        <div class="nav-section">Tableau de bord</div>
        <a href="index.php" class="nav-item <?= $active==='dashboard'?'active':'' ?>">
            <?= icon('dashboard') ?> Accueil
        </a>

        <div class="nav-section">Documents</div>
        <a href="devis.php" class="nav-item <?= $active==='devis'?'active':'' ?>">
            <?= icon('devis') ?> Devis
        </a>
        <a href="commandes.php" class="nav-item <?= $active==='commandes'?'active':'' ?>">
            <?= icon('commandes') ?> Bons de Commande
        </a>
        <a href="livraisons.php" class="nav-item <?= $active==='livraisons'?'active':'' ?>">
            <?= icon('livraisons') ?> Bons de Livraison
        </a>
        <a href="factures.php" class="nav-item <?= $active==='factures'?'active':'' ?>">
            <?= icon('factures') ?> Factures
        </a>

        <div class="nav-section">Référentiel</div>
        <a href="clients.php" class="nav-item <?= $active==='clients'?'active':'' ?>">
            <?= icon('clients') ?> Clients
        </a>
        <a href="chauffeurs.php" class="nav-item <?= $active==='chauffeurs'?'active':'' ?>">
            <?= icon('clients') ?> Chauffeurs
        </a>
        <a href="chantiers.php" class="nav-item <?= $active==='chantiers'?'active':'' ?>">
            <?= icon('chantiers') ?> Chantiers
        </a>
        <a href="materiels.php" class="nav-item <?= $active==='materiels'?'active':'' ?>">
            <?= icon('materiels') ?> Matériels
        </a>
    </nav>
    <div class="sidebar-footer">v<?= APP_VERSION ?> &middot; <?= date('d/m/Y') ?></div>
</aside>

<!-- MAIN -->
<div class="main">
<div class="topbar">
    <h2><?= htmlspecialchars($title) ?></h2>
    <div class="topbar-actions">
        <?php if($flash): ?>
        <div class="alert alert-<?= $flash['type']==='success'?'success':($flash['type']==='error'?'error':'info') ?>" style="margin:0;padding:6px 14px">
            <?= htmlspecialchars($flash['msg']) ?>
        </div>
        <?php endif; ?>
        <?php 
        $user = getCurrentUser();
        if ($user): 
        ?>
        <div style="display:flex;align-items:center;gap:12px;margin-left:auto;padding-left:12px;border-left:1px solid var(--border)">
            <div style="text-align:right;font-size:12px">
                <div style="color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.5px">Connecté</div>
                <div style="font-weight:600;color:var(--text)"><?= htmlspecialchars($user['name']) ?></div>
            </div>
            <a href="logout.php" class="btn btn-light btn-sm" style="margin:0">Déconnexion</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="content">
<?php
}

function renderFooter(): void {
    ?>
</div><!-- end content -->
</div><!-- end main -->
<script>
// Auto-dismiss alerts
setTimeout(()=>document.querySelectorAll('.alert').forEach(a=>a.style.opacity='0'),4000);
</script>
</body>
</html>
    <?php
}

// SVG Icons
function icon(string $name): string {
    $icons = [
        'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
        'devis'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>',
        'commandes' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17H7A5 5 0 017 7h10a5 5 0 015 5"/><path d="M16 3l4 4-4 4"/><line x1="21" y1="7" x2="10" y2="7"/><path d="M21 21v-4h-4"/><path d="M21 17H11a2 2 0 000 4h10v-4z"/></svg>',
        'livraisons'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'factures'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
        'clients'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>',
        'materiels' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>',
        'chantiers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-8h6v8"/></svg>',
        'plus'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        'edit'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'print'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
        'eye'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'trash'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>',
        'arrow'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
    ];
    return $icons[$name] ?? '';
}

<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';

// Require user to be logged in
requireLogin();

$action  = $_GET['action'] ?? 'list';
$id      = $_GET['id'] ?? null;
$clients = loadData('clients');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    if ($p['_action'] === 'save') {
        $client = [
            'id'        => $p['client_id'] ?: 'C' . str_pad(count($clients) + 1, 3, '0', STR_PAD_LEFT),
            'nom'       => $p['nom'] ?? '',
            'contact'   => $p['contact'] ?? '',
            'adresse'   => $p['adresse'] ?? '',
            'ville'     => $p['ville'] ?? '',
            'telephone' => $p['telephone'] ?? '',
            'email'     => $p['email'] ?? '',
            'rc'        => $p['rc'] ?? '',
            'tva'       => $p['tva'] ?? '',
            'notes'     => $p['notes'] ?? '',
        ];
        $found = false;
        foreach ($clients as &$c) {
            if ($c['id'] === $client['id']) { $c = $client; $found = true; break; }
        }
        if (!$found) $clients[] = $client;
        saveData('clients', $clients);
        flash('success', 'Client enregistré.');
        header('Location: clients.php');
        exit;
    }
    if ($p['_action'] === 'delete') {
        $clients = array_values(array_filter($clients, fn($c) => $c['id'] !== $p['client_id']));
        saveData('clients', $clients);
        flash('success', 'Client supprimé.');
        header('Location: clients.php');
        exit;
    }
}

$edit = null;
if ($id) foreach ($clients as $c) if ($c['id'] === $id) { $edit = $c; break; }

renderHeader('Clients', 'clients');
$flash2 = getFlash();
if ($flash2): ?>
<div class="alert alert-<?= $flash2['type']==='success'?'success':'error' ?>"><?= htmlspecialchars($flash2['msg']) ?></div>
<?php endif;

if ($action === 'new' || $edit): $c = $edit ?? []; ?>
<div style="margin-bottom:16px"><a href="clients.php" class="btn btn-light btn-sm">← Liste clients</a></div>
<div class="card" style="max-width:700px">
    <div class="card-header"><h3><?= $edit ? 'Modifier' : 'Nouveau' ?> Client</h3></div>
    <div class="card-body">
    <form method="POST">
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="client_id" value="<?= htmlspecialchars($c['id'] ?? '') ?>">
    <div class="form-section">
        <div class="form-section-title">Informations société</div>
        <div class="form-grid">
            <div class="form-group" style="grid-column:1/-1">
                <label>Raison sociale *</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($c['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Contact principal</label>
                <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($c['contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($c['telephone'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($c['email'] ?? '') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Adresse</label>
                <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($c['adresse'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Ville / CP</label>
                <input type="text" name="ville" class="form-control" value="<?= htmlspecialchars($c['ville'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>RC</label>
                <input type="text" name="rc" class="form-control" value="<?= htmlspecialchars($c['rc'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Matricule fiscal</label>
                <input type="text" name="tva" class="form-control" value="<?= htmlspecialchars($c['tva'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('arrow') ?> Enregistrer</button>
    </form>
    </div>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:20px">
    <a href="clients.php?action=new" class="btn btn-primary"><?= icon('plus') ?> Nouveau client</a>
</div>
<div class="card">
    <div class="card-header"><h3>👥 Clients (<?= count($clients) ?>)</h3></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>Nom</th><th>Contact</th><th>Téléphone</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($clients as $c): ?>
        <tr>
            <td class="mono" style="color:#94a3b8"><?= htmlspecialchars($c['id']) ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($c['nom']) ?></td>
            <td><?= htmlspecialchars($c['contact'] ?? '') ?></td>
            <td class="mono"><?= htmlspecialchars($c['telephone'] ?? '') ?></td>
            <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
            <td>
                <div style="display:flex;gap:4px">
                <a href="clients.php?id=<?= $c['id'] ?>" class="btn btn-light btn-sm"><?= icon('edit') ?></a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce client ?')">
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                    <button class="btn btn-danger btn-sm"><?= icon('trash') ?></button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($clients)): ?><tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8">Aucun client. <a href="?action=new" style="color:var(--brand)">Ajouter le premier</a></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif;

renderFooter();

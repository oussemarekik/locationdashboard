<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';

requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$chantiers = loadData('chantiers');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    if (($p['_action'] ?? '') === 'save') {
        $chantier = [
            'id' => $p['chantier_id'] ?: 'CHT' . str_pad(count($chantiers) + 1, 3, '0', STR_PAD_LEFT),
            'nom' => trim($p['nom'] ?? ''),
            'client' => trim($p['client'] ?? ''),
            'adresse' => trim($p['adresse'] ?? ''),
            'responsable' => trim($p['responsable'] ?? ''),
            'telephone' => trim($p['telephone'] ?? ''),
            'date_debut' => $p['date_debut'] ?? '',
            'date_fin' => $p['date_fin'] ?? '',
            'statut' => $p['statut'] ?? 'actif',
            'notes' => $p['notes'] ?? '',
        ];

        $found = false;
        foreach ($chantiers as &$c) {
            if (($c['id'] ?? '') === $chantier['id']) {
                $c = $chantier;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $chantiers[] = $chantier;
        }

        saveData('chantiers', $chantiers);
        flash('success', 'Chantier enregistré.');
        header('Location: chantiers.php');
        exit;
    }

    if (($p['_action'] ?? '') === 'delete') {
        $chantiers = array_values(array_filter($chantiers, fn($c) => ($c['id'] ?? '') !== ($p['chantier_id'] ?? '')));
        saveData('chantiers', $chantiers);
        flash('success', 'Chantier supprimé.');
        header('Location: chantiers.php');
        exit;
    }
}

$edit = null;
if ($id) {
    foreach ($chantiers as $c) {
        if (($c['id'] ?? '') === $id) {
            $edit = $c;
            break;
        }
    }
}

renderHeader('Chantiers', 'chantiers');
$flash2 = getFlash();
if ($flash2): ?>
<div class="alert alert-<?= $flash2['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash2['msg']) ?></div>
<?php endif; ?>

<?php if ($action === 'new' || $edit): $c = $edit ?? []; ?>
<div style="margin-bottom:16px"><a href="chantiers.php" class="btn btn-light btn-sm">← Liste chantiers</a></div>
<div class="card" style="max-width:900px">
    <div class="card-header"><h3><?= $edit ? 'Modifier' : 'Nouveau' ?> chantier</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="chantier_id" value="<?= htmlspecialchars($c['id'] ?? '') ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nom du chantier *</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($c['nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Client</label>
                    <input type="text" name="client" class="form-control" value="<?= htmlspecialchars($c['client'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($c['adresse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Responsable</label>
                    <input type="text" name="responsable" class="form-control" value="<?= htmlspecialchars($c['responsable'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($c['telephone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="statut" class="form-control">
                        <?php foreach(['actif' => 'Actif', 'termine' => 'Terminé', 'suspendu' => 'Suspendu'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($c['statut'] ?? 'actif') === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date début</label>
                    <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($c['date_debut'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Date fin</label>
                    <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($c['date_fin'] ?? '') ?>">
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Autres infos / Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Autres informations du chantier"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= icon('arrow') ?> Enregistrer</button>
        </form>
    </div>
</div>
<?php else: ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div class="badge" style="background:#e0f2fe;color:#0369a1"><?= count($chantiers) ?> chantier(s)</div>
    <a href="chantiers.php?action=new" class="btn btn-primary"><?= icon('plus') ?> Nouveau chantier</a>
</div>
<div class="card">
    <div class="card-header"><h3>🏗️ Référentiel Chantiers</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Client</th><th>Adresse</th><th>Responsable</th><th>Dates</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach(array_reverse($chantiers) as $c): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($c['nom'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['client'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['adresse'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['responsable'] ?? '-') ?></td>
                    <td><?= !empty($c['date_debut']) ? dateFR($c['date_debut']) : '-' ?> / <?= !empty($c['date_fin']) ? dateFR($c['date_fin']) : '-' ?></td>
                    <td><?= statusBadge($c['statut'] ?? 'actif') ?></td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <a href="chantiers.php?id=<?= $c['id'] ?>" class="btn btn-light btn-sm"><?= icon('edit') ?></a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce chantier ?')">
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="chantier_id" value="<?= $c['id'] ?>">
                                <button class="btn btn-danger btn-sm"><?= icon('trash') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($chantiers)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#94a3b8">Aucun chantier. <a href="?action=new" style="color:var(--brand)">Ajouter le premier</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';

requireLogin();

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$chauffeurs = loadData('chauffeurs');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    if (($p['_action'] ?? '') === 'save') {
        $chauffeur = [
            'id' => $p['chauffeur_id'] ?: 'CH' . str_pad(count($chauffeurs) + 1, 3, '0', STR_PAD_LEFT),
            'nom' => trim($p['nom'] ?? ''),
            'telephone' => trim($p['telephone'] ?? ''),
            'adresse' => trim($p['adresse'] ?? ''),
            'permis' => trim($p['permis'] ?? ''),
            'actif' => isset($p['actif']) ? 1 : 0,
            'notes' => $p['notes'] ?? '',
        ];

        $found = false;
        foreach ($chauffeurs as &$c) {
            if (($c['id'] ?? '') === $chauffeur['id']) {
                $c = $chauffeur;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $chauffeurs[] = $chauffeur;
        }

        saveData('chauffeurs', $chauffeurs);
        flash('success', 'Chauffeur enregistré.');
        header('Location: chauffeurs.php');
        exit;
    }

    if (($p['_action'] ?? '') === 'delete') {
        $chauffeurs = array_values(array_filter($chauffeurs, fn($c) => ($c['id'] ?? '') !== ($p['chauffeur_id'] ?? '')));
        saveData('chauffeurs', $chauffeurs);
        flash('success', 'Chauffeur supprimé.');
        header('Location: chauffeurs.php');
        exit;
    }
}

$edit = null;
if ($id) {
    foreach ($chauffeurs as $c) {
        if (($c['id'] ?? '') === $id) {
            $edit = $c;
            break;
        }
    }
}

renderHeader('Chauffeurs', 'chauffeurs');
$flash2 = getFlash();
if ($flash2): ?>
<div class="alert alert-<?= $flash2['type'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($flash2['msg']) ?></div>
<?php endif; ?>

<?php if ($action === 'new' || $edit): $c = $edit ?? []; ?>
<div style="margin-bottom:16px"><a href="chauffeurs.php" class="btn btn-light btn-sm">← Liste chauffeurs</a></div>
<div class="card" style="max-width:760px">
    <div class="card-header"><h3><?= $edit ? 'Modifier' : 'Nouveau' ?> chauffeur</h3></div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="_action" value="save">
            <input type="hidden" name="chauffeur_id" value="<?= htmlspecialchars($c['id'] ?? '') ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($c['nom'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= htmlspecialchars($c['telephone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input type="text" name="adresse" class="form-control" value="<?= htmlspecialchars($c['adresse'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Permis</label>
                    <input type="text" name="permis" class="form-control" value="<?= htmlspecialchars($c['permis'] ?? '') ?>" placeholder="Ex: C, D, E...">
                </div>
                <div class="form-group">
                    <label>Actif</label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;text-transform:none;letter-spacing:0">
                        <input type="checkbox" name="actif" value="1" <?= ($c['actif'] ?? 1) ? 'checked' : '' ?>>
                        Chauffeur disponible
                    </label>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control"><?= htmlspecialchars($c['notes'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= icon('arrow') ?> Enregistrer</button>
        </form>
    </div>
</div>
<?php else: ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div class="badge" style="background:#e0f2fe;color:#0369a1"><?= count($chauffeurs) ?> chauffeur(s)</div>
    <a href="chauffeurs.php?action=new" class="btn btn-primary"><?= icon('plus') ?> Nouveau chauffeur</a>
</div>
<div class="card">
    <div class="card-header"><h3>👷 Référentiel Chauffeurs</h3></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Téléphone</th><th>Adresse</th><th>Permis</th><th>Statut</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach(array_reverse($chauffeurs) as $c): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($c['nom'] ?? '') ?></td>
                    <td><?= htmlspecialchars($c['telephone'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['adresse'] ?? '-') ?></td>
                    <td class="mono"><?= htmlspecialchars($c['permis'] ?? '-') ?></td>
                    <td><?= statusBadge(($c['actif'] ?? 1) ? 'service' : 'panne') ?></td>
                    <td>
                        <div style="display:flex;gap:4px">
                            <a href="chauffeurs.php?id=<?= $c['id'] ?>" class="btn btn-light btn-sm"><?= icon('edit') ?></a>
                            <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce chauffeur ?')">
                                <input type="hidden" name="_action" value="delete">
                                <input type="hidden" name="chauffeur_id" value="<?= $c['id'] ?>">
                                <button class="btn btn-danger btn-sm"><?= icon('trash') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($chauffeurs)): ?>
                <tr><td colspan="6" style="text-align:center;padding:40px;color:#94a3b8">Aucun chauffeur. <a href="?action=new" style="color:var(--brand)">Ajouter le premier</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
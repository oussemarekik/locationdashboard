<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';

// Require user to be logged in
requireLogin();

$action    = $_GET['action'] ?? 'list';
$id        = $_GET['id'] ?? null;
$materiels = loadData('materiels');
$chauffeurs = loadData('chauffeurs');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $p = $_POST;
    if ($p['_action'] === 'save') {
        $mat = [
            'id'          => $p['mat_id'] ?: 'M' . str_pad(count($materiels) + 1, 3, '0', STR_PAD_LEFT),
            'reference'   => $p['reference'] ?? '',
            'designation' => $p['designation'] ?? '',
            'categorie'   => $p['categorie'] ?? '',
            'description' => $p['description'] ?? '',
            'prix_jour'   => (float)str_replace(',','.',$p['prix_jour'] ?? 0),
            'prix_semaine'=> (float)str_replace(',','.',$p['prix_semaine'] ?? 0),
            'prix_mois'   => (float)str_replace(',','.',$p['prix_mois'] ?? 0),
            'unite'       => $p['unite'] ?? 'jour',
            'stock'       => (int)($p['stock'] ?? 1),
            'chauffeur_id' => $p['chauffeur_id'] ?? '',
            'chauffeur_nom' => $p['chauffeur_nom'] ?? '',
            'chauffeur_telephone' => $p['chauffeur_telephone'] ?? '',
            'etat_machine' => $p['etat_machine'] ?? 'service',
            'date_vidange' => $p['date_vidange'] ?? '',
            'montant_reduit' => (float)str_replace(',','.',$p['montant_reduit'] ?? 0),
        ];
        $found = false;
        foreach ($materiels as &$m) {
            if ($m['id'] === $mat['id']) { $m = $mat; $found = true; break; }
        }
        if (!$found) $materiels[] = $mat;
        saveData('materiels', $materiels);
        flash('success', 'Matériel enregistré.');
        header('Location: materiels.php');
        exit;
    }
    if ($p['_action'] === 'delete') {
        $materiels = array_values(array_filter($materiels, fn($m) => $m['id'] !== $p['mat_id']));
        saveData('materiels', $materiels);
        flash('success', 'Matériel supprimé.');
        header('Location: materiels.php');
        exit;
    }
}

$edit = null;
if ($id) foreach ($materiels as $m) if ($m['id'] === $id) { $edit = $m; break; }

renderHeader('Parc Matériel', 'materiels');
$flash2 = getFlash();
if ($flash2): ?>
<div class="alert alert-<?= $flash2['type']==='success'?'success':'error' ?>"><?= htmlspecialchars($flash2['msg']) ?></div>
<?php endif;

// Catégories distinctes
$cats = array_unique(array_filter(array_column($materiels, 'categorie')));

if ($action === 'new' || $edit): $m = $edit ?? []; ?>
<div style="margin-bottom:16px"><a href="materiels.php" class="btn btn-light btn-sm">← Parc matériel</a></div>
<div class="card" style="max-width:700px">
    <div class="card-header"><h3><?= $edit ? 'Modifier' : 'Nouveau' ?> Matériel</h3></div>
    <div class="card-body">
    <form method="POST">
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="mat_id" value="<?= htmlspecialchars($m['id'] ?? '') ?>">
    <div class="form-section">
        <div class="form-section-title">Identification</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Référence</label>
                <input type="text" name="reference" class="form-control mono" value="<?= htmlspecialchars($m['reference'] ?? '') ?>" placeholder="Ex: GR-CAT-320">
            </div>
            <div class="form-group">
                <label>Catégorie</label>
                <input type="text" name="categorie" class="form-control" value="<?= htmlspecialchars($m['categorie'] ?? '') ?>" list="cats_list" placeholder="Ex: Levage">
                <datalist id="cats_list"><?php foreach($cats as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Désignation *</label>
                <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($m['designation'] ?? '') ?>" required>
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Description</label>
                <textarea name="description" class="form-control"><?= htmlspecialchars($m['description'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">Tarification & Stock</div>
        <div class="form-grid-3">
            <div class="form-group">
                <label>Prix / Jour (HT)</label>
                <input type="number" name="prix_jour" class="form-control" step="0.001" min="0" value="<?= $m['prix_jour'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Prix / Semaine (HT)</label>
                <input type="number" name="prix_semaine" class="form-control" step="0.001" min="0" value="<?= $m['prix_semaine'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Prix / Mois (HT)</label>
                <input type="number" name="prix_mois" class="form-control" step="0.001" min="0" value="<?= $m['prix_mois'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Unité par défaut</label>
                <select name="unite" class="form-control">
                    <?php foreach(['jour','semaine','mois','heure','forfait'] as $u): ?>
                    <option value="<?= $u ?>" <?= ($m['unite']??'jour')===$u?'selected':'' ?>><?= $u ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantité en stock</label>
                <input type="number" name="stock" class="form-control" min="0" value="<?= $m['stock'] ?? 1 ?>">
            </div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">Chauffeur & Maintenance</div>
        <div class="form-grid-3">
            <div class="form-group">
                <label>Chauffeur référentiel</label>
                <select name="chauffeur_id" id="chauffeur_select" class="form-control" onchange="fillChauffeur(this)">
                    <option value="">— Choisir un chauffeur —</option>
                    <?php foreach($chauffeurs as $ch): ?>
                    <option value="<?= htmlspecialchars($ch['id'] ?? '') ?>" data-nom="<?= htmlspecialchars($ch['nom'] ?? '') ?>" data-telephone="<?= htmlspecialchars($ch['telephone'] ?? '') ?>" <?= ($m['chauffeur_id'] ?? '') === ($ch['id'] ?? '') ? 'selected' : '' ?>><?= htmlspecialchars($ch['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Chauffeur affecté</label>
                <input type="text" name="chauffeur_nom" id="chauffeur_nom" class="form-control" value="<?= htmlspecialchars($m['chauffeur_nom'] ?? '') ?>" placeholder="Nom du chauffeur">
            </div>
            <div class="form-group">
                <label>Téléphone chauffeur</label>
                <input type="text" name="chauffeur_telephone" id="chauffeur_telephone" class="form-control" value="<?= htmlspecialchars($m['chauffeur_telephone'] ?? '') ?>" placeholder="+216 ...">
            </div>
            <div class="form-group">
                <label>État machine</label>
                <select name="etat_machine" class="form-control">
                    <?php foreach(['service'=>'En service','vidange'=>'Vidange','panne'=>'En panne'] as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($m['etat_machine'] ?? 'service') === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date de vidange</label>
                <input type="date" name="date_vidange" class="form-control" value="<?= htmlspecialchars($m['date_vidange'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Montant réduit</label>
                <input type="number" name="montant_reduit" class="form-control" step="0.001" min="0" value="<?= htmlspecialchars($m['montant_reduit'] ?? 0) ?>" placeholder="Ex: 25">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('arrow') ?> Enregistrer</button>
    </form>
    </div>
</div>

<?php else: ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:20px">
    <a href="materiels.php?action=new" class="btn btn-primary"><?= icon('plus') ?> Nouveau matériel</a>
</div>

<!-- Filtres catégories -->
<?php if($cats): ?>
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <span style="font-size:12px;color:#64748b;margin-top:6px">Catégories:</span>
    <a href="materiels.php" class="badge" style="background:var(--brand-light);color:var(--brand);cursor:pointer">Toutes</a>
    <?php foreach($cats as $cat): ?>
    <a href="?cat=<?= urlencode($cat) ?>" class="badge" style="background:#f1f5f9;color:#475569;cursor:pointer"><?= htmlspecialchars($cat) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$filterCat = $_GET['cat'] ?? '';
$filtered  = $filterCat ? array_filter($materiels, fn($m) => $m['categorie'] === $filterCat) : $materiels;
?>
<div class="card">
    <div class="card-header"><h3>🔧 Parc Matériel (<?= count($filtered) ?>)</h3></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Réf.</th><th>Désignation</th><th>Chauffeur</th><th>État</th><th>Vidange</th><th>Réduit</th><th>Catégorie</th><th>Prix/Jour</th><th>Prix/Semaine</th><th>Prix/Mois</th><th>Stock</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach($filtered as $m): ?>
        <tr>
            <td class="mono" style="color:#64748b;font-size:11px"><?= htmlspecialchars($m['reference'] ?? '') ?></td>
            <td>
                <div style="font-weight:600"><?= htmlspecialchars($m['designation']) ?></div>
                <?php if($m['description'] ?? ''): ?><div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($m['description']) ?></div><?php endif; ?>
            </td>
            <td>
                <div style="font-weight:600"><?= htmlspecialchars($m['chauffeur_nom'] ?? '-') ?></div>
                <?php if($m['chauffeur_telephone'] ?? ''): ?><div style="font-size:11px;color:#94a3b8"><?= htmlspecialchars($m['chauffeur_telephone']) ?></div><?php endif; ?>
            </td>
            <td><span class="badge" style="background:<?= ($m['etat_machine'] ?? 'service') === 'service' ? '#d1fae5' : (($m['etat_machine'] ?? '') === 'vidange' ? '#fef3c7' : '#fee2e2') ?>;color:<?= ($m['etat_machine'] ?? 'service') === 'service' ? '#065f46' : (($m['etat_machine'] ?? '') === 'vidange' ? '#92400e' : '#991b1b') ?>"><?= htmlspecialchars(ucfirst($m['etat_machine'] ?? 'service')) ?></span></td>
            <td><?= $m['date_vidange'] ? dateFR($m['date_vidange']) : '-' ?></td>
            <td class="mono"><?= formatMoney($m['montant_reduit'] ?? 0) ?></td>
            <td><span class="badge" style="background:#eff6ff;color:#1d4ed8"><?= htmlspecialchars($m['categorie'] ?? '') ?></span></td>
            <td class="mono"><?= formatMoney($m['prix_jour'] ?? 0) ?></td>
            <td class="mono"><?= formatMoney($m['prix_semaine'] ?? 0) ?></td>
            <td class="mono"><?= formatMoney($m['prix_mois'] ?? 0) ?></td>
            <td style="font-weight:700;color:<?= ($m['stock']??0)>0?'#059669':'#dc2626' ?>;font-size:16px;text-align:center"><?= $m['stock'] ?? 0 ?></td>
            <td>
                <div style="display:flex;gap:4px">
                <a href="materiels.php?id=<?= $m['id'] ?>" class="btn btn-light btn-sm"><?= icon('edit') ?></a>
                <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce matériel ?')">
                    <input type="hidden" name="_action" value="delete">
                    <input type="hidden" name="mat_id" value="<?= $m['id'] ?>">
                    <button class="btn btn-danger btn-sm"><?= icon('trash') ?></button>
                </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($filtered)): ?><tr><td colspan="12" style="text-align:center;padding:40px;color:#94a3b8">Aucun matériel. <a href="?action=new" style="color:var(--brand)">Ajouter le premier</a></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif;

?>
<script>
function fillChauffeur(select) {
    if (!select || !select.options || select.selectedIndex < 0) return;
    const option = select.options[select.selectedIndex];
    const nom = document.getElementById('chauffeur_nom');
    const telephone = document.getElementById('chauffeur_telephone');
    if (nom) nom.value = option.dataset.nom || '';
    if (telephone) telephone.value = option.dataset.telephone || '';
}
</script>
<?php renderFooter(); ?>

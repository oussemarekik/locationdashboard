<?php
require_once 'includes/config.php';
require_once 'includes/layout.php';

// Require user to be logged in
requireLogin();

// Charger tous les docs
$devis     = loadData('devis');
$commandes = loadData('commandes');
$livraisons= loadData('livraisons');
$factures  = loadData('factures');
$clients   = loadData('clients');
$chauffeursRef = loadData('chauffeurs');
$materiels = loadData('materiels');

// Stats
$totalFA = array_sum(array_map(fn($f) => $f['ttc'] ?? 0, $factures));
$payees  = array_filter($factures, fn($f) => $f['statut'] === 'paye');
$totalPaye = array_sum(array_map(fn($f) => $f['ttc'] ?? 0, $payees));
$materielsAffectes = array_filter($materiels, fn($m) => trim((string)($m['chauffeur_nom'] ?? '')) !== '');
$materielsMaintenance = array_filter($materiels, fn($m) => in_array(($m['etat_machine'] ?? 'service'), ['vidange', 'panne'], true));
$materielsVidange = array_filter($materiels, fn($m) => !empty($m['date_vidange']));
$totalReductionMateriels = array_sum(array_map(fn($m) => (float)($m['montant_reduit'] ?? 0), $materiels));
$chauffeurs = [];
foreach ($chauffeursRef as $chauffeur) {
    $chauffeurs[$chauffeur['id']] = [
        'id' => $chauffeur['id'] ?? '',
        'nom' => $chauffeur['nom'] ?? '',
        'telephone' => $chauffeur['telephone'] ?? '',
        'actif' => (int)($chauffeur['actif'] ?? 1),
        'machines' => [],
        'etat' => 'service',
        'date_vidange' => '',
        'reduction' => 0,
    ];
}
foreach ($materiels as $m) {
    $key = trim((string)($m['chauffeur_id'] ?? ''));
    if ($key !== '' && isset($chauffeurs[$key])) {
        $chauffeurs[$key]['machines'][] = $m['designation'] ?? '-';
        $chauffeurs[$key]['reduction'] += (float)($m['montant_reduit'] ?? 0);
        if (in_array(($m['etat_machine'] ?? 'service'), ['panne', 'vidange'], true)) {
            $chauffeurs[$key]['etat'] = $m['etat_machine'];
        }
        if (!empty($m['date_vidange']) && (empty($chauffeurs[$key]['date_vidange']) || $m['date_vidange'] > $chauffeurs[$key]['date_vidange'])) {
            $chauffeurs[$key]['date_vidange'] = $m['date_vidange'];
        }
        continue;
    }

    $fallbackName = trim((string)($m['chauffeur_nom'] ?? ''));
    if ($fallbackName === '') {
        continue;
    }
    $fallbackKey = 'name:' . mb_strtolower($fallbackName);
    if (!isset($chauffeurs[$fallbackKey])) {
        $chauffeurs[$fallbackKey] = [
            'id' => '',
            'nom' => $fallbackName,
            'telephone' => trim((string)($m['chauffeur_telephone'] ?? '')),
            'actif' => 1,
            'machines' => [],
            'etat' => 'service',
            'date_vidange' => '',
            'reduction' => 0,
        ];
    }
    $chauffeurs[$fallbackKey]['machines'][] = $m['designation'] ?? '-';
    $chauffeurs[$fallbackKey]['reduction'] += (float)($m['montant_reduit'] ?? 0);
    if (in_array(($m['etat_machine'] ?? 'service'), ['panne', 'vidange'], true)) {
        $chauffeurs[$fallbackKey]['etat'] = $m['etat_machine'];
    }
    if (!empty($m['date_vidange']) && (empty($chauffeurs[$fallbackKey]['date_vidange']) || $m['date_vidange'] > $chauffeurs[$fallbackKey]['date_vidange'])) {
        $chauffeurs[$fallbackKey]['date_vidange'] = $m['date_vidange'];
    }
}
$chauffeurs = array_values($chauffeurs);
$chauffeursAffectes = array_values(array_filter($chauffeurs, fn($c) => !empty($c['machines'])));

renderHeader('Tableau de bord', 'dashboard');
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="2.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
        </div>
        <div class="label">Devis</div>
        <div class="value" style="color:#1a56db"><?= count($devis) ?></div>
        <div class="sub"><?= count(array_filter($devis, fn($d)=>$d['statut']==='accepte')) ?> accepté(s)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ede9fe">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.5"><path d="M9 17H7A5 5 0 017 7h10a5 5 0 015 5"/><line x1="21" y1="7" x2="10" y2="7"/></svg>
        </div>
        <div class="label">Bons de Commande</div>
        <div class="value" style="color:#7c3aed"><?= count($commandes) ?></div>
        <div class="sub"><?= count(array_filter($commandes, fn($c)=>$c['statut']==='valide')) ?> validé(s)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><rect x="1" y="3" width="15" height="13"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/></svg>
        </div>
        <div class="label">Bons de Livraison</div>
        <div class="value" style="color:#059669"><?= count($livraisons) ?></div>
        <div class="sub"><?= count(array_filter($livraisons, fn($l)=>$l['statut']==='livre')) ?> livré(s)</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div class="label">Chiffre d'affaires</div>
        <div class="value" style="color:#dc2626;font-size:18px"><?= formatMoney($totalFA) ?></div>
        <div class="sub">Payé: <?= formatMoney($totalPaye) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0f2fe">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.5"><path d="M4 7h16v10H4z"/><path d="M7 17v2M17 17v2M8 7l-2 3h14l-2-3"/></svg>
        </div>
        <div class="label">Chauffeurs affectés</div>
        <div class="value" style="color:#0284c7"><?= count($chauffeursAffectes) ?></div>
        <div class="sub"><?= count($materielsMaintenance) ?> machine(s) en maintenance</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

<!-- Derniers devis -->
<div class="card">
    <div class="card-header">
        <h3>📋 Derniers Devis</h3>
        <a href="devis.php?action=new" class="btn btn-primary btn-sm"><?= icon('plus') ?> Nouveau</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>N°</th><th>Client</th><th>TTC</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach(array_slice(array_reverse($devis), 0, 5) as $d): ?>
            <tr>
                <td><a href="devis.php?id=<?= $d['id'] ?>" class="mono" style="color:var(--brand);text-decoration:none"><?= htmlspecialchars($d['numero']) ?></a></td>
                <td><?= htmlspecialchars($d['client']['nom'] ?? '-') ?></td>
                <td class="mono"><?= formatMoney($d['ttc'] ?? 0) ?></td>
                <td><?= statusBadge($d['statut'] ?? 'brouillon') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($devis)): ?><tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Aucun devis</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Dernières factures -->
<div class="card">
    <div class="card-header">
        <h3>💶 Dernières Factures</h3>
        <a href="factures.php?action=new" class="btn btn-danger btn-sm"><?= icon('plus') ?> Nouvelle</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>N°</th><th>Client</th><th>TTC</th><th>Statut</th></tr></thead>
            <tbody>
            <?php foreach(array_slice(array_reverse($factures), 0, 5) as $f): ?>
            <tr>
                <td><a href="factures.php?id=<?= $f['id'] ?>" class="mono" style="color:#dc2626;text-decoration:none"><?= htmlspecialchars($f['numero']) ?></a></td>
                <td><?= htmlspecialchars($f['client']['nom'] ?? '-') ?></td>
                <td class="mono"><?= formatMoney($f['ttc'] ?? 0) ?></td>
                <td><?= statusBadge($f['statut'] ?? 'brouillon') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($factures)): ?><tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Aucune facture</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Clients -->
<div class="card">
    <div class="card-header">
        <h3>👥 Clients récents</h3>
        <a href="clients.php?action=new" class="btn btn-light btn-sm"><?= icon('plus') ?> Ajouter</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Contact</th><th>Téléphone</th></tr></thead>
            <tbody>
            <?php foreach(array_slice(array_reverse($clients), 0, 5) as $c): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($c['nom']) ?></td>
                <td style="color:var(--text-muted)"><?= htmlspecialchars($c['contact'] ?? '') ?></td>
                <td class="mono"><?= htmlspecialchars($c['telephone'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($clients)): ?><tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:20px">Aucun client</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Matériels -->
<div class="card">
    <div class="card-header">
        <h3>🔧 Parc Matériel</h3>
        <a href="materiels.php?action=new" class="btn btn-light btn-sm"><?= icon('plus') ?> Ajouter</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Désignation</th><th>Catégorie</th><th>Prix/jour</th><th>Stock</th></tr></thead>
            <tbody>
            <?php foreach(array_slice($materiels, 0, 5) as $m): ?>
            <tr>
                <td style="font-weight:600"><?= htmlspecialchars($m['designation']) ?></td>
                <td><span class="badge" style="background:#f1f5f9;color:#475569"><?= htmlspecialchars($m['categorie'] ?? '') ?></span></td>
                <td class="mono"><?= formatMoney($m['prix_jour'] ?? 0) ?></td>
                <td style="font-weight:600;color:<?= ($m['stock']??0)>0?'#059669':'#dc2626' ?>"><?= $m['stock'] ?? 0 ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($materiels)): ?><tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Aucun matériel</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
    <div class="card">
        <div class="card-header">
            <h3>🚚 Chauffeurs et machines</h3>
            <span class="badge" style="background:#e0f2fe;color:#0369a1">Réduction totale: <?= formatMoney($totalReductionMateriels) ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Machine</th><th>Chauffeur</th><th>État</th><th>Vidange</th><th>Réduit</th></tr></thead>
                <tbody>
                <?php foreach(array_slice(array_reverse($materiels), 0, 8) as $m): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($m['designation'] ?? '-') ?></td>
                    <td>
                        <?= htmlspecialchars($m['chauffeur_nom'] ?? '-') ?><br>
                        <span style="color:var(--text-muted);font-size:11px"><?= htmlspecialchars($m['chauffeur_telephone'] ?? '') ?></span>
                    </td>
                    <td><?= statusBadge($m['etat_machine'] ?? 'service') ?></td>
                    <td><?= !empty($m['date_vidange']) ? dateFR($m['date_vidange']) : '-' ?></td>
                    <td class="mono"><?= formatMoney($m['montant_reduit'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($materiels)): ?><tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px">Aucune machine</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>👷 Partie Chauffeurs</h3>
            <span class="badge" style="background:#e0f2fe;color:#0369a1"><?= count($chauffeursRef) ?> chauffeur(s)</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Chauffeur</th><th>Contact</th><th>Machines</th><th>État</th><th>Vidange</th><th>Réduction</th></tr></thead>
                <tbody>
            <?php foreach(array_slice($chauffeurs, 0, 8) as $chauffeur): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($chauffeur['nom']) ?></td>
                    <td><?= htmlspecialchars($chauffeur['telephone'] ?: '-') ?></td>
                    <td>
                        <?= htmlspecialchars(implode(', ', array_slice($chauffeur['machines'], 0, 3))) ?>
                        <?php if(count($chauffeur['machines']) > 3): ?>
                        <span style="color:var(--text-muted)">(+<?= count($chauffeur['machines']) - 3 ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= statusBadge($chauffeur['etat']) ?></td>
                    <td><?= !empty($chauffeur['date_vidange']) ? dateFR($chauffeur['date_vidange']) : '-' ?></td>
                    <td class="mono"><?= formatMoney($chauffeur['reduction']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($chauffeurs)): ?><tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:20px">Aucun chauffeur affecté</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>🛠️ Maintenance / Vidange</h3>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Machine</th><th>État</th><th>Date</th><th>Réduction</th></tr></thead>
                <tbody>
                <?php foreach(array_slice(array_reverse($materielsVidange), 0, 8) as $m): ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($m['designation'] ?? '-') ?></td>
                    <td><?= statusBadge($m['etat_machine'] ?? 'service') ?></td>
                    <td><?= !empty($m['date_vidange']) ? dateFR($m['date_vidange']) : '-' ?></td>
                    <td class="mono"><?= formatMoney($m['montant_reduit'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($materielsVidange)): ?><tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:20px">Aucune vidange renseignée</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderFooter(); ?>

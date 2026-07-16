<?php
// Génération HTML imprimable pour tous les documents
function renderDocument(array $doc, string $type): string {
    $client = $doc['client'] ?? [];
    $lignes = $doc['lignes'] ?? [];
    $issuer = getIssuerProfile($doc['issuer_profile'] ?? 'societe');
    $issuerName = $issuer['name'] ?: COMPANY_NAME;
    $issuerLines = [];
    if (!empty($issuer['address'])) $issuerLines[] = $issuer['address'];
    if (!empty($issuer['city'])) $issuerLines[] = $issuer['city'];
    $contactLine = trim((!empty($issuer['phone']) ? ('Tél: ' . $issuer['phone']) : '') . (!empty($issuer['email']) ? ((!empty($issuer['phone']) ? ' | ' : '') . $issuer['email']) : ''));
    if ($contactLine !== '') $issuerLines[] = $contactLine;
    if (!empty($issuer['rc'])) $issuerLines[] = $issuer['rc'];
    if (!empty($issuer['tva'])) $issuerLines[] = $issuer['tva'];
    if (!empty($issuer['cin'])) $issuerLines[] = $issuer['cin'];
    if (!empty($issuer['rib'])) $issuerLines[] = $issuer['rib'];
    
    $typeLabels = [
        'devis'    => ['label' => 'DEVIS',             'prefix' => 'DV', 'color' => '#1a56db'],
        'commande' => ['label' => 'BON DE COMMANDE',   'prefix' => 'BC', 'color' => '#7c3aed'],
        'livraison'=> ['label' => 'BON DE LIVRAISON',  'prefix' => 'BL', 'color' => '#059669'],
        'facture'  => ['label' => 'FACTURE',           'prefix' => 'FA', 'color' => '#dc2626'],
    ];
    $t = $typeLabels[$type];
    $color = $t['color'];

    // Totaux
    $ht = 0;
    foreach ($lignes as $l) $ht += ($l['qte'] ?? 0) * ($l['pu'] ?? 0);
    $remise_pct = $doc['remise'] ?? 0;
    $remise_val = $ht * $remise_pct / 100;
    $ht_net = $ht - $remise_val;
    $tva_pct = $doc['tva'] ?? TVA_RATE;
    $tva_val = $ht_net * $tva_pct / 100;
    $ttc = $ht_net + $tva_val;
    // Timbre fiscal (ex: 1 DT) stored on factures
    $timbre = isset($doc['timbre']) ? (float)$doc['timbre'] : 0.0;
    if ($timbre > 0) $ttc += $timbre;

    $dateDoc = dateFR($doc['date'] ?? date('Y-m-d'));
    $dateEch = isset($doc['date_echeance']) ? dateFR($doc['date_echeance']) : '';
    $dateLoc = $doc['date_debut'] ?? '';
    $dateFin = $doc['date_fin'] ?? '';
    $dateLivraison = $doc['date_livraison'] ?? '';
    $linkedFactureId = $doc['linked_facture_id'] ?? '';
    $linkedLivraisonId = $doc['linked_livraison_id'] ?? '';
    $linkedFactureNumero = '';
    $linkedLivraisonNumero = '';
    if ($linkedFactureId !== '') {
        foreach (loadData('factures') as $linkedDoc) {
            if (($linkedDoc['id'] ?? '') === $linkedFactureId) {
                $linkedFactureNumero = $linkedDoc['numero'] ?? $linkedFactureId;
                break;
            }
        }
    }
    if ($linkedLivraisonId !== '') {
        foreach (loadData('livraisons') as $linkedDoc) {
            if (($linkedDoc['id'] ?? '') === $linkedLivraisonId) {
                $linkedLivraisonNumero = $linkedDoc['numero'] ?? $linkedLivraisonId;
                break;
            }
        }
    }
    $moyenTransport = $doc['moyen_transport'] ?? '';
    $nomCamion = $doc['nom_camion'] ?? '';
    $matriculeCamion = $doc['matricule_camion'] ?? '';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $t['label'] ?> <?= htmlspecialchars($doc['numero'] ?? '') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:11px;color:#1e293b;background:#fff;padding:0}
.doc{max-width:800px;margin:0 auto;padding:30px 36px;min-height:1100px;display:flex;flex-direction:column}

/* EN-TÊTE */
.doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;padding-bottom:20px;border-bottom:3px solid <?= $color ?>}
.company-info h2{font-size:20px;font-weight:800;color:<?= $color ?>;letter-spacing:-.5px;margin-bottom:4px}
.company-info p{font-size:10px;color:#64748b;line-height:1.6}
.doc-title-block{text-align:right}
.doc-type{font-size:22px;font-weight:900;color:<?= $color ?>;letter-spacing:1px;text-transform:uppercase}
.doc-number{font-size:13px;font-weight:700;background:<?= $color ?>;color:#fff;padding:4px 12px;border-radius:20px;display:inline-block;margin-top:6px;letter-spacing:.5px}
.doc-date{font-size:10px;color:#64748b;margin-top:4px}

/* PARTIES */
.parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px}
.partie-box{background:#f8fafc;border-radius:8px;padding:14px 16px;border:1px solid #e2e8f0}
.partie-box .partie-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:<?= $color ?>;margin-bottom:8px}
.partie-box .partie-name{font-size:13px;font-weight:700;margin-bottom:2px}
.partie-box p{font-size:10px;color:#475569;line-height:1.7}

/* INFOS LOCATION */
.location-bar{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.info-chip{background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 14px}
.info-chip .chip-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#1d4ed8;margin-bottom:3px}
.info-chip .chip-value{font-size:12px;font-weight:600;color:#1e293b}

/* TABLE LIGNES */
.lignes-section{margin-bottom:20px;flex:1}
table{width:100%;border-collapse:collapse;font-size:10.5px}
thead tr{background:<?= $color ?>;color:#fff}
th{padding:9px 10px;text-align:left;font-weight:600;letter-spacing:.3px}
th.right,td.right{text-align:right}
th.center,td.center{text-align:center}
tbody tr:nth-child(even){background:#f8fafc}
td{padding:8px 10px;border-bottom:1px solid #e2e8f0;vertical-align:top}
.ref{color:#64748b;font-size:9.5px}
.designation-main{font-weight:600}
tfoot td{background:#f0f9ff;font-weight:700;border-top:2px solid <?= $color ?>;padding:9px 10px}

/* TOTAUX */
.doc-footer{display:grid;grid-template-columns:1fr auto;gap:24px;align-items:end}
.totaux{min-width:280px}
.total-row{display:flex;justify-content:space-between;padding:6px 12px;font-size:11px}
.total-row.ht{color:#475569}
.total-row.remise{color:#dc2626}
.total-row.tva{color:#475569;border-top:1px dashed #e2e8f0}
.total-row.ttc{background:<?= $color ?>;color:#fff;border-radius:6px;padding:10px 14px;font-size:14px;font-weight:800;margin-top:6px}
.total-row span:last-child{font-family:'Courier New',monospace;font-weight:700}

/* NOTES & MENTIONS */
.notes-section{margin-top:20px;padding:14px;background:#fffbeb;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0;font-size:10px;color:#78350f}
.mentions{margin-top:16px;font-size:9px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:12px;line-height:1.8}

/* SIGNATURES */
.signatures{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:24px;padding-top:16px;border-top:1px dashed #e2e8f0}
.sig-block{text-align:center}
.sig-block .sig-label{font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:40px}
.sig-block .sig-line{border-top:1px solid #cbd5e1;padding-top:6px;font-size:9px;color:#64748b}

/* FILIGRANE BROUILLON */
<?php if(($doc['statut'] ?? '') === 'brouillon'): ?>
.doc::before{content:'BROUILLON';position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:80px;font-weight:900;color:rgba(0,0,0,.05);z-index:0;pointer-events:none;white-space:nowrap}
<?php endif; ?>

@media print{
    body{padding:0}
    .doc{padding:18px 22px}
    .no-print{display:none!important}
    @page{size:A4;margin:10mm}
}
</style>
</head>
<body>
<div class="doc">

    <!-- EN-TÊTE -->
    <div class="doc-header">
        <div class="company-info">
            <h2><?= htmlspecialchars($issuerName) ?></h2>
            <?php foreach ($issuerLines as $line): ?>
            <p><?= htmlspecialchars($line) ?></p>
            <?php endforeach; ?>
        </div>
        <div class="doc-title-block">
            <div class="doc-type"><?= $t['label'] ?></div>
            <div class="doc-number"><?= htmlspecialchars($doc['numero'] ?? 'N/A') ?></div>
            <div class="doc-date">Date : <?= $dateDoc ?></div>
            <?php if($dateEch): ?><div class="doc-date">Échéance : <?= $dateEch ?></div><?php endif; ?>
                <?php if($type === 'livraison' && $linkedFactureId): ?><div class="doc-date">Facture liée : <?= htmlspecialchars($linkedFactureNumero ?: $linkedFactureId) ?></div><?php endif; ?>
                <?php if($type === 'facture' && $linkedLivraisonId): ?><div class="doc-date">BL lié : <?= htmlspecialchars($linkedLivraisonNumero ?: $linkedLivraisonId) ?></div><?php endif; ?>
        </div>
    </div>

    <!-- PARTIES -->
    <div class="parties">
        <div class="partie-box">
            <div class="partie-label">📤 Émetteur</div>
            <div class="partie-name"><?= htmlspecialchars($issuerName) ?></div>
            <?php if(!empty($issuerLines)): ?>
            <p>
                <?php foreach ($issuerLines as $index => $line): ?>
                <?= htmlspecialchars($line) ?><?php if ($index < count($issuerLines) - 1): ?><br><?php endif; ?>
                <?php endforeach; ?>
            </p>
            <?php endif; ?>
        </div>
        <div class="partie-box">
            <div class="partie-label">📥 <?= $type==='commande' ? 'Fournisseur / Client' : 'Client' ?></div>
            <div class="partie-name"><?= htmlspecialchars($client['nom'] ?? '') ?></div>
            <p>
                <?= htmlspecialchars($client['contact'] ?? '') ?><br>
                <?= htmlspecialchars($client['adresse'] ?? '') ?><br>
                <?= htmlspecialchars($client['ville'] ?? '') ?><br>
                <?php if($client['telephone'] ?? ''): ?>Tél: <?= htmlspecialchars($client['telephone']) ?><br><?php endif; ?>
                <?php if($client['email'] ?? ''): ?><?= htmlspecialchars($client['email']) ?><br><?php endif; ?>
                <?php if($client['rc'] ?? ''): ?><?= htmlspecialchars($client['rc']) ?> | <?= htmlspecialchars($client['tva'] ?? '') ?><?php endif; ?>
            </p>
        </div>
    </div>

    <!-- INFOS LOCATION -->
    <?php if($dateLoc || ($doc['chantier'] ?? '') || ($doc['lieu_livraison'] ?? '') || ($type === 'livraison' && ($dateLivraison || $moyenTransport || $nomCamion || $matriculeCamion))): ?>
    <div class="location-bar">
        <?php if($type === 'livraison' && $dateLivraison): ?>
        <div class="info-chip">
            <div class="chip-label">📅 Date livraison</div>
            <div class="chip-value"><?= dateFR($dateLivraison) ?></div>
        </div>
        <?php endif; ?>
        <?php if($dateLoc): ?>
        <div class="info-chip">
            <div class="chip-label">📅 Début location</div>
            <div class="chip-value"><?= dateFR($dateLoc) ?></div>
        </div>
        <?php endif; ?>
        <?php if($dateFin): ?>
        <div class="info-chip">
            <div class="chip-label">📅 Fin location</div>
            <div class="chip-value"><?= dateFR($dateFin) ?></div>
        </div>
        <?php endif; ?>
        <?php if($doc['chantier'] ?? ''): ?>
        <div class="info-chip">
            <div class="chip-label">🏗️ Chantier / Projet</div>
            <div class="chip-value"><?= htmlspecialchars($doc['chantier']) ?></div>
        </div>
        <?php endif; ?>
        <?php if($doc['lieu_livraison'] ?? ''): ?>
        <div class="info-chip">
            <div class="chip-label">📍 Lieu livraison</div>
            <div class="chip-value"><?= htmlspecialchars($doc['lieu_livraison']) ?></div>
        </div>
        <?php endif; ?>
        <?php if($type === 'livraison' && $moyenTransport): ?>
        <div class="info-chip">
            <div class="chip-label">🚚 Moyen de transport</div>
            <div class="chip-value"><?= htmlspecialchars($moyenTransport) ?></div>
        </div>
        <?php endif; ?>
        <?php if($type === 'livraison' && $nomCamion): ?>
        <div class="info-chip">
            <div class="chip-label">🚛 Nom du camion</div>
            <div class="chip-value"><?= htmlspecialchars($nomCamion) ?></div>
        </div>
        <?php endif; ?>
        <?php if($type === 'livraison' && $matriculeCamion): ?>
        <div class="info-chip">
            <div class="chip-label">🪪 Matricule du camion</div>
            <div class="chip-value"><?= htmlspecialchars($matriculeCamion) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LIGNES -->
    <div class="lignes-section">
        <table>
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:12%">Réf.</th>
                    <th>Désignation</th>
                    <th class="center" style="width:8%">Qté</th>
                    <th class="center" style="width:8%">Unité</th>
                    <th class="right" style="width:13%">P.U. HT</th>
                    <th class="right" style="width:14%">Total HT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($lignes as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="ref"><?= htmlspecialchars($l['ref'] ?? '') ?></td>
                    <td>
                        <div class="designation-main"><?= htmlspecialchars($l['designation'] ?? '') ?></div>
                        <?php if($l['description'] ?? ''): ?>
                        <div class="ref"><?= htmlspecialchars($l['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="center"><?= number_format($l['qte'] ?? 0, 2) ?></td>
                    <td class="center"><?= htmlspecialchars($l['unite'] ?? 'jour') ?></td>
                    <td class="right"><?= formatMoney($l['pu'] ?? 0) ?></td>
                    <td class="right"><?= formatMoney(($l['qte'] ?? 0) * ($l['pu'] ?? 0)) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($lignes)): ?>
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:20px">Aucune ligne</td></tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="6" style="text-align:right">Total HT :</td><td class="right"><?= formatMoney($ht) ?></td></tr>
            </tfoot>
        </table>
    </div>

    <!-- PIED DE PAGE -->
    <div class="doc-footer">
        <!-- NOTES -->
        <div>
            <?php if($doc['notes'] ?? ''): ?>
            <div class="notes-section">
                <strong>Notes / Conditions :</strong><br>
                <?= nl2br(htmlspecialchars($doc['notes'])) ?>
            </div>
            <?php endif; ?>
            <?php if($type === 'facture'): ?>
            <div class="mentions">
                Mode de règlement : <?= htmlspecialchars($doc['mode_reglement'] ?? 'Virement bancaire') ?><br>
                RIB : <?= COMPANY_RIB ?><br>
                Tout retard de paiement entraîne l'application d'une pénalité de 1,5% par mois.
            </div>
            <?php elseif($type === 'devis'): ?>
            <div class="mentions">
                Devis valable 30 jours à compter de la date d'émission.<br>
                Pour acceptation, merci de retourner ce document signé et cacheté.
            </div>
            <?php endif; ?>
        </div>

        <!-- TOTAUX -->
        <div class="totaux">
            <div class="total-row ht">
                <span>Total HT :</span>
                <span><?= formatMoney($ht) ?></span>
            </div>
            <?php if($remise_pct > 0): ?>
            <div class="total-row remise">
                <span>Remise (<?= $remise_pct ?>%) :</span>
                <span>- <?= formatMoney($remise_val) ?></span>
            </div>
            <div class="total-row ht">
                <span>HT Net :</span>
                <span><?= formatMoney($ht_net) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row tva">
                <span>TVA (<?= $tva_pct ?>%) :</span>
                <span><?= formatMoney($tva_val) ?></span>
            </div>
            <?php if($timbre > 0): ?>
            <div class="total-row">
                <span>Timbre fiscal :</span>
                <span><?= formatMoney($timbre) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-row ttc">
                <span>TOTAL TTC :</span>
                <span><?= formatMoney($ttc) ?></span>
            </div>
        </div>
    </div>

    <!-- SIGNATURES -->
    <div class="signatures">
        <div class="sig-block">
            <div class="sig-label">Signature & Cachet Client</div>
            <div class="sig-line">Lu et approuvé — <?= htmlspecialchars($client['nom'] ?? '') ?></div>
        </div>
        <div class="sig-block">
            <div class="sig-label">Signature <?= htmlspecialchars($issuerName) ?></div>
            <div class="sig-line">Le Responsable Commercial</div>
        </div>
    </div>

</div>
</body>
</html>
    <?php
    return ob_get_clean();
}

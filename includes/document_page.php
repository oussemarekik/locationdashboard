<?php
// document_page.php — page générique pour tous les types de documents
// Inclure avec: $docType, $docConfig déjà définis

require_once 'includes/config.php';
require_once 'includes/layout.php';
require_once 'includes/document_render.php';

// Require user to be logged in
requireLogin();

// Enable debug output when requested
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

$action   = $_GET['action'] ?? 'list';
$id       = $_GET['id'] ?? null;
$dataKey  = $docConfig['data_key'];
$docs     = loadData($dataKey);
$clients  = loadData('clients');
$chantiers = loadData('chantiers');
$materiels= loadData('materiels');

function findDocumentIndexById(array $docs, string $id): ?int {
    foreach ($docs as $index => $doc) {
        if (($doc['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

function buildFactureFromLivraison(array $livraison, array $existingFacture = []): array {
    $facture = $existingFacture;
    $facture['id'] = $facture['id'] ?? uniqid();
    $facture['numero'] = $facture['numero'] ?? generateNumber('FA');
    $facture['date'] = $livraison['date'] ?? date('Y-m-d');
    $facture['issuer_profile'] = $livraison['issuer_profile'] ?? 'societe';
    $facture['date_echeance'] = $facture['date_echeance'] ?? '';
    $facture['date_debut'] = $livraison['date_debut'] ?? '';
    $facture['date_fin'] = $livraison['date_fin'] ?? '';
    $facture['date_livraison'] = $livraison['date_livraison'] ?? '';
    $facture['chantier'] = $livraison['chantier'] ?? '';
    $facture['lieu_livraison'] = $livraison['lieu_livraison'] ?? '';
    $facture['moyen_transport'] = $livraison['moyen_transport'] ?? '';
    $facture['nom_camion'] = $livraison['nom_camion'] ?? '';
    $facture['matricule_camion'] = $livraison['matricule_camion'] ?? '';
    $facture['client'] = $livraison['client'] ?? [];
    $facture['lignes'] = $livraison['lignes'] ?? [];
    $facture['remise'] = $livraison['remise'] ?? 0;
    $facture['tva'] = $livraison['tva'] ?? TVA_RATE;
    $facture['ht'] = $livraison['ht'] ?? 0;
    $facture['ht_net'] = $livraison['ht_net'] ?? 0;
    $facture['tva_val'] = $livraison['tva_val'] ?? 0;
    $facture['timbre'] = $existingFacture['timbre'] ?? 1.0;
    $facture['ttc'] = ($livraison['ttc'] ?? 0) + (($facture['timbre'] ?? 0) > 0 ? (float)$facture['timbre'] : 0.0);
    $facture['notes'] = $existingFacture['notes'] ?? ($livraison['notes'] ?? '');
    $facture['mode_reglement'] = $existingFacture['mode_reglement'] ?? '';
    $facture['statut'] = $existingFacture['statut'] ?? 'brouillon';
    $facture['created_at'] = $existingFacture['created_at'] ?? ($livraison['created_at'] ?? date('Y-m-d H:i:s'));
    $facture['updated_at'] = date('Y-m-d H:i:s');
    $facture['linked_livraison_id'] = $livraison['id'] ?? '';
    return $facture;
}

// ---- ACTIONS POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST;

    if (($post['_action'] ?? '') === 'save') {
            error_log('DOCUMENT_PAGE: Received save POST for ' . ($docConfig['type'] ?? 'doc'));
        // Construire les lignes
        $lignes = [];
        $refs   = $post['ligne_ref']   ?? [];
        $desigs = $post['ligne_desig'] ?? [];
        $qtes   = $post['ligne_qte']   ?? [];
        $pus    = $post['ligne_pu']    ?? [];
        $unites = $post['ligne_unite'] ?? [];
        $descs  = $post['ligne_desc']  ?? [];
        
        for ($i = 0; $i < count($desigs); $i++) {
            if (empty(trim($desigs[$i]))) continue;
            $lignes[] = [
                'ref'         => $refs[$i] ?? '',
                'designation' => $desigs[$i],
                'qte'         => (float)str_replace(',','.',$qtes[$i] ?? 0),
                'pu'          => (float)str_replace(',','.',$pus[$i]  ?? 0),
                'unite'       => $unites[$i] ?? 'jour',
                'description' => $descs[$i]  ?? '',
            ];
        }
        
        // Client
        $cid = $post['client_id'] ?? '';
        $clientData = [];
        foreach ($clients as $c) {
            if ($c['id'] === $cid) { $clientData = $c; break; }
        }
        // Si saisi manuellement
        if (empty($clientData) && !empty($post['client_nom'])) {
            $clientData = [
                'id'        => '',
                'nom'       => $post['client_nom'] ?? '',
                'contact'   => $post['client_contact'] ?? '',
                'adresse'   => $post['client_adresse'] ?? '',
                'ville'     => $post['client_ville'] ?? '',
                'telephone' => $post['client_telephone'] ?? '',
                'email'     => $post['client_email'] ?? '',
                'rc'        => $post['client_rc'] ?? '',
                'tva'       => $post['client_tva_num'] ?? '',
            ];
        }
        
        // Calculs
        $ht = array_sum(array_map(fn($l) => $l['qte'] * $l['pu'], $lignes));
        $remise = (float)($post['remise'] ?? 0);
        $tva    = (float)($post['tva']    ?? TVA_RATE);
        $ht_net = $ht * (1 - $remise / 100);
        $tva_val = $ht_net * $tva / 100;
        $ttc = $ht_net + $tva_val;
        // Timbre modifiable sur factures uniquement
        $timbre = ($docConfig['type'] === 'facture') ? (float)($post['timbre'] ?? 1.0) : 0.0;
        $ttc = $ttc + $timbre;
        
        $doc = [
            'id'              => $post['doc_id'] ?: uniqid(),
            'numero'          => $post['numero'],
            'date'            => $post['date'] ?? date('Y-m-d'),
            'issuer_profile'  => $post['issuer_profile'] ?? 'societe',
            'date_echeance'   => $post['date_echeance'] ?? '',
            'date_debut'      => $post['date_debut'] ?? '',
            'date_fin'        => $post['date_fin'] ?? '',
            'date_livraison'  => $post['date_livraison'] ?? '',
            'chantier'        => $post['chantier'] ?? '',
            'lieu_livraison'  => $post['lieu_livraison'] ?? '',
            'moyen_transport' => $post['moyen_transport'] ?? '',
            'nom_camion'      => $post['nom_camion'] ?? '',
            'matricule_camion'=> $post['matricule_camion'] ?? '',
            'client'          => $clientData,
            'lignes'          => $lignes,
            'remise'          => $remise,
            'tva'             => $tva,
            'ht'              => $ht,
            'ht_net'          => $ht_net,
            'tva_val'         => $tva_val,
            'timbre'          => $timbre,
            'ttc'             => $ttc,
            'statut'          => $post['statut'] ?? 'brouillon',
            'notes'           => $post['notes'] ?? '',
            'mode_reglement'  => $post['mode_reglement'] ?? '',
            'created_at'      => $post['created_at'] ?: date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        
        // Save (update or insert)
        $found = false;
        foreach ($docs as &$d) {
            if ($d['id'] === $doc['id']) { $d = $doc; $found = true; break; }
        }
        // If new document, only reserve (increment) the counter when the posted
        // numero matches the previewed generated number. This avoids incrementing
        // on mere refreshes of the form.
        if (!$found) {
            $postedNumero = trim((string)($post['numero'] ?? ''));
            $prefix = strtoupper($docConfig['prefix']);
            $preview = generateNumber($prefix);
            // If the user left the numero empty, reserve one now.
            if ($postedNumero === '' || $postedNumero === $preview) {
                try {
                    $newVal = incrementCounter(strtolower($prefix));
                    $doc['numero'] = $prefix . '-' . date('Y') . '-' . str_pad((string)$newVal, 4, '0', STR_PAD_LEFT);
                    error_log('DOCUMENT_PAGE: Reserved new numero ' . $doc['numero']);
                } catch (Throwable $e) {
                    error_log('DOCUMENT_PAGE: incrementCounter failed: ' . $e->getMessage());
                }
            } else {
                // Keep posted value as-is (user provided custom number)
                $doc['numero'] = $postedNumero;
            }
            $docs[] = $doc;
        }
            $saved = saveData($dataKey, $docs);

            if ($saved === false) {
                // save failed
                error_log('DOCUMENT_PAGE: saveData returned false for ' . $dataKey);
                throw new Exception('Échec de l\'enregistrement des données (saveData returned false).');
            }

            error_log('DOCUMENT_PAGE: Saving document id=' . $doc['id'] . ' numero=' . ($doc['numero'] ?? ''));

            if ($docConfig['type'] === 'livraison') {
                $factures = loadData('factures');
                $linkedFactureId = trim((string)($doc['linked_facture_id'] ?? ''));
                $linkedIndex = $linkedFactureId !== '' ? findDocumentIndexById($factures, $linkedFactureId) : null;

                if ($linkedIndex === null) {
                    foreach ($factures as $index => $facture) {
                        if (($facture['linked_livraison_id'] ?? '') === $doc['id']) {
                            $linkedIndex = $index;
                            break;
                        }
                    }
                }

                $existingFacture = $linkedIndex !== null ? ($factures[$linkedIndex] ?? []) : [];
                $facture = buildFactureFromLivraison($doc, $existingFacture);
                $facture['linked_livraison_id'] = $doc['id'];
                $doc['linked_facture_id'] = $facture['id'];
                $docs[array_search($doc['id'], array_column($docs, 'id'), true)] = $doc;
                if ($linkedIndex !== null) {
                    $factures[$linkedIndex] = $facture;
                } else {
                    $factures[] = $facture;
                }
                saveData('factures', $factures);
                saveData($dataKey, $docs);
            }

            flash('success', $docConfig['label'] . ' enregistré avec succès.');
        $location = $docConfig['file'] . '?id=' . $doc['id'];
        if (!headers_sent()) {
            header('Location: ' . $location);
            exit;
        } else {
            // Fallback: output a small confirmation if redirect is not possible
            echo "<div style='padding:30px;max-width:700px;margin:40px auto'>";
            echo "<h2>{$docConfig['label']} enregistré</h2>";
            echo "<p>Numéro assigné : <strong>" . htmlspecialchars($doc['numero']) . "</strong></p>";
            echo "<p><a href=\"{$location}\">Voir le document</a> &middot; <a href=\"{$docConfig['file']}\">Retour à la liste</a></p>";
            echo "</div>";
            exit;
        }
    }
    
    if ($post['_action'] === 'delete' && isset($post['doc_id'])) {
        $docs = array_filter($docs, fn($d) => $d['id'] !== $post['doc_id']);
        saveData($dataKey, array_values($docs));
        flash('success', $docConfig['label'] . ' supprimé.');
        header('Location: ' . $docConfig['file']);
        exit;
    }
    
    if ($post['_action'] === 'status' && isset($post['doc_id'])) {
        foreach ($docs as &$d) {
            if ($d['id'] === $post['doc_id']) {
                $d['statut'] = $post['new_status'];
                break;
            }
        }
        saveData($dataKey, $docs);
        flash('success', 'Statut mis à jour.');
        header('Location: ' . $docConfig['file'] . '?id=' . $post['doc_id']);
        exit;
    }
}

// ---- VIEW DOC ----
if ($action === 'print' && $id) {
    $doc = null;
    foreach ($docs as $d) if ($d['id'] === $id) { $doc = $d; break; }
    if ($doc) {
        echo renderDocument($doc, $docConfig['type']);
        exit;
    }
}

// ---- EDIT / VIEW ----
$editDoc = null;
if ($id) {
    foreach ($docs as $d) if ($d['id'] === $id) { $editDoc = $d; break; }
}

renderHeader($docConfig['title'], $docConfig['nav_key']);

// Flash inline
$flash2 = getFlash();
if ($flash2): ?>
<div class="alert alert-<?= $flash2['type']==='success'?'success':($flash2['type']==='error'?'error':'info') ?>">
    <?= htmlspecialchars($flash2['msg']) ?>
</div>
<?php endif;

// ========== FORMULAIRE ==========
if ($action === 'new' || ($id && $editDoc)):
    $doc = $editDoc ?? [
        'numero' => generateNumber(strtoupper($docConfig['prefix'])),
        'date'   => date('Y-m-d'),
        'issuer_profile' => 'societe',
        'statut' => 'brouillon',
        'tva'    => TVA_RATE,
        'timbre' => $docConfig['type'] === 'facture' ? 1.0 : 0.0,
    ];
    $isNew = !$editDoc;
?>

<div style="display:flex;gap:12px;margin-bottom:20px;align-items:center">
    <a href="<?= $docConfig['file'] ?>" class="btn btn-light btn-sm">← Liste</a>
    <?php if(!$isNew): ?>
    <a href="<?= $docConfig['file'] ?>?action=print&id=<?= $doc['id'] ?>" target="_blank" class="btn btn-outline btn-sm"><?= icon('print') ?> Imprimer / PDF</a>
    <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce document ?')">
        <input type="hidden" name="_action" value="delete">
        <input type="hidden" name="doc_id" value="<?= $doc['id'] ?>">
        <button class="btn btn-danger btn-sm"><?= icon('trash') ?> Supprimer</button>
    </form>
    <?php endif; ?>
</div>

<form method="POST" id="docForm">
<input type="hidden" name="_action" value="save">
<input type="hidden" name="doc_id" value="<?= $doc['id'] ?? '' ?>">
<input type="hidden" name="created_at" value="<?= $doc['created_at'] ?? '' ?>">



<div class="doc-edit-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

<!-- COLONNE GAUCHE -->
<div>

<!-- INFOS DOC -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3><?= icon($docConfig['nav_key']) ?> <?= $docConfig['label'] ?></h3></div>
    <div class="card-body">
        <div class="form-grid-3">
            <div class="form-group">
                <label>Numéro</label>
                <input type="text" name="numero" class="form-control mono" value="<?= htmlspecialchars($doc['numero'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= $doc['date'] ?? date('Y-m-d') ?>">
            </div>
            <?php if(in_array($docConfig['type'], ['facture','devis'])): ?>
            <div class="form-group">
                <label>Date échéance</label>
                <input type="date" name="date_echeance" class="form-control" value="<?= $doc['date_echeance'] ?? '' ?>">
            </div>
            <?php endif; ?>
            <div class="form-group">
                <label>Début location</label>
                <input type="date" name="date_debut" class="form-control" value="<?= $doc['date_debut'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Fin location</label>
                <input type="date" name="date_fin" class="form-control" value="<?= $doc['date_fin'] ?? '' ?>">
            </div>
            <div class="form-group">
                <label>Statut</label>
                <select name="statut" class="form-control">
                    <?php foreach($docConfig['statuts'] as $s => $l): ?>
                    <option value="<?= $s ?>" <?= ($doc['statut']??'')===$s?'selected':'' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group" style="margin-top:12px">
            <label>Émetteur du document</label>
            <select name="issuer_profile" class="form-control">
                <?php foreach(ISSUER_PROFILES as $key => $issuer): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= ($doc['issuer_profile'] ?? 'societe') === $key ? 'selected' : '' ?>>
                    <?= htmlspecialchars($issuer['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid" style="margin-top:12px">
            <div class="form-group">
                <label>Chantier référentiel</label>
                <select id="chantier_select" class="form-control" onchange="fillChantier(this.value)">
                    <option value="">— Choisir un chantier —</option>
                    <?php foreach($chantiers as $ch): ?>
                    <option value="<?= htmlspecialchars(json_encode($ch)) ?>" data-adresse="<?= htmlspecialchars($ch['adresse'] ?? '') ?>"><?= htmlspecialchars($ch['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Chantier / Projet</label>
                <input type="text" name="chantier" id="chantier_field" class="form-control" value="<?= htmlspecialchars($doc['chantier'] ?? '') ?>" placeholder="Ex: Résidence Les Oliviers">
            </div>
            <div class="form-group">
                <label>Lieu de livraison</label>
                <input type="text" name="lieu_livraison" class="form-control" value="<?= htmlspecialchars($doc['lieu_livraison'] ?? '') ?>" placeholder="Adresse chantier">
            </div>
            <?php if($docConfig['type'] === 'livraison'): ?>
            <div class="form-group">
                <label>Date de livraison</label>
                <input type="date" name="date_livraison" class="form-control" value="<?= htmlspecialchars($doc['date_livraison'] ?? '') ?>">
            </div>
            <?php endif; ?>
        </div>
        <?php if($docConfig['type'] === 'livraison'): ?>
        <div class="form-grid" style="margin-top:12px">
            <div class="form-group">
                <label>Moyen de transport</label>
                <input type="text" name="moyen_transport" class="form-control" value="<?= htmlspecialchars($doc['moyen_transport'] ?? '') ?>" placeholder="Ex: Camion">
            </div>
            <div class="form-group">
                <label>Nom du camion</label>
                <input type="text" name="nom_camion" class="form-control" value="<?= htmlspecialchars($doc['nom_camion'] ?? '') ?>" placeholder="Ex: Mercedes Actros">
            </div>
            <div class="form-group">
                <label>Matricule du camion</label>
                <input type="text" name="matricule_camion" class="form-control" value="<?= htmlspecialchars($doc['matricule_camion'] ?? '') ?>" placeholder="Ex: 1854682T/A/M/000">
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- CLIENT -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3>👥 Client</h3></div>
    <div class="card-body">
        <div class="form-group" style="margin-bottom:14px">
            <label>Sélectionner un client existant</label>
            <select id="client_select" class="form-control" onchange="fillClient(this.value)">
                <option value="">— Saisie manuelle —</option>
                <?php foreach($clients as $c): ?>
                <option value="<?= htmlspecialchars(json_encode($c)) ?>" <?= ($doc['client']['id']??'')===$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['nom']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="client_id" id="client_id_field" value="<?= htmlspecialchars($doc['client']['id'] ?? '') ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>Raison sociale *</label>
                <input type="text" name="client_nom" id="f_nom" class="form-control" value="<?= htmlspecialchars($doc['client']['nom'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Contact</label>
                <input type="text" name="client_contact" id="f_contact" class="form-control" value="<?= htmlspecialchars($doc['client']['contact'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Adresse</label>
                <input type="text" name="client_adresse" id="f_adresse" class="form-control" value="<?= htmlspecialchars($doc['client']['adresse'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Ville / CP</label>
                <input type="text" name="client_ville" id="f_ville" class="form-control" value="<?= htmlspecialchars($doc['client']['ville'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="client_telephone" id="f_telephone" class="form-control" value="<?= htmlspecialchars($doc['client']['telephone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="client_email" id="f_email" class="form-control" value="<?= htmlspecialchars($doc['client']['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>RC</label>
                <input type="text" name="client_rc" id="f_rc" class="form-control" value="<?= htmlspecialchars($doc['client']['rc'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Matricule fiscal</label>
                <input type="text" name="client_tva_num" id="f_tva" class="form-control" value="<?= htmlspecialchars($doc['client']['tva'] ?? '') ?>">
            </div>
        </div>
    </div>
</div>

<!-- LIGNES -->
<div class="card" style="margin-bottom:20px">
    <div class="card-header">
        <h3>📦 Lignes de location</h3>
        <div style="display:flex;gap:8px">
            <select id="materiel_quick" class="form-control" style="font-size:12px;padding:5px 8px" onchange="addMaterielLigne(this)">
                <option value="">+ Ajouter matériel du parc</option>
                <?php foreach($materiels as $m): ?>
                <option value="<?= htmlspecialchars(json_encode($m)) ?>"><?= htmlspecialchars($m['designation']) ?> (<?= formatMoney($m['prix_jour']) ?>/j)</option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-light btn-sm" onclick="addLigne()">+ Ligne vide</button>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <table class="lignes-table">
            <thead>
                <tr>
                    <th style="width:9%">Réf.</th>
                    <th>Désignation *</th>
                    <th style="width:8%">Qté</th>
                    <th style="width:10%">Unité</th>
                    <th style="width:12%">P.U. HT</th>
                    <th style="width:12%">Total HT</th>
                    <th style="width:4%"></th>
                </tr>
            </thead>
            <tbody id="lignes-body">
            <?php
            $initLignes = $doc['lignes'] ?? [['ref'=>'','designation'=>'','qte'=>1,'pu'=>0,'unite'=>'jour','description'=>'']];
            foreach($initLignes as $idx => $l): ?>
            <tr class="ligne-row" data-idx="<?= $idx ?>">
                <td><input type="text" name="ligne_ref[]" class="form-control" style="font-size:11px" value="<?= htmlspecialchars($l['ref'] ?? '') ?>" placeholder="Réf."></td>
                <td>
                    <input type="text" name="ligne_desig[]" class="form-control" value="<?= htmlspecialchars($l['designation'] ?? '') ?>" placeholder="Désignation" required>
                    <input type="text" name="ligne_desc[]" class="form-control" style="font-size:11px;margin-top:4px;color:#64748b" value="<?= htmlspecialchars($l['description'] ?? '') ?>" placeholder="Description (optionnel)">
                </td>
                <td><input type="number" name="ligne_qte[]" class="form-control total-calc" step="0.01" min="0" value="<?= $l['qte'] ?? 1 ?>" oninput="recalc()"></td>
                <td>
                    <select name="ligne_unite[]" class="form-control" style="font-size:12px">
                        <?php foreach(['jour','semaine','mois','heure','forfait','u'] as $u): ?>
                        <option value="<?= $u ?>" <?= ($l['unite']??'jour')===$u?'selected':'' ?>><?= $u ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="ligne_pu[]" class="form-control total-calc" step="0.001" min="0" value="<?= $l['pu'] ?? 0 ?>" oninput="recalc()"></td>
                <td class="mono total-cell" style="font-weight:600;color:var(--brand)"><?= formatMoney(($l['qte']??0)*($l['pu']??0)) ?></td>
                <td><button type="button" class="remove-ligne" onclick="removeLigne(this)">✕</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="5" style="text-align:right;font-weight:700">Total HT :</td>
                    <td colspan="2" id="total-ht-foot" class="mono" style="color:var(--brand);font-weight:700"><?= formatMoney($doc['ht'] ?? 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

</div><!-- /col gauche -->

<!-- COLONNE DROITE -->
<div>
<div class="card" style="margin-bottom:20px;position:sticky;top:70px">
    <div class="card-header"><h3>💰 Totaux</h3></div>
    <div class="card-body">
        <div class="form-group" style="margin-bottom:12px">
            <label>Remise (%)</label>
            <input type="number" name="remise" id="remise_field" class="form-control" min="0" max="100" step="0.1" value="<?= $doc['remise'] ?? 0 ?>" oninput="recalc()">
        </div>
        <div class="form-group" style="margin-bottom:16px">
            <label>TVA (%)</label>
            <input type="number" name="tva" id="tva_field" class="form-control" min="0" max="100" step="0.1" value="<?= $doc['tva'] ?? TVA_RATE ?>" oninput="recalc()">
        </div>
        <?php if($docConfig['type'] === 'facture'): ?>
        <div class="form-group" style="margin-bottom:16px">
            <label>Timbre fiscal</label>
            <input type="number" name="timbre" id="timbre_field" class="form-control" min="0" step="0.001" value="<?= htmlspecialchars($doc['timbre'] ?? 1.0) ?>" oninput="recalc()">
        </div>
        <?php endif; ?>
        <div style="background:#f8fafc;border-radius:8px;padding:14px;font-size:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:#64748b">
                <span>Total HT :</span><span id="disp-ht" class="mono"><?= formatMoney($doc['ht']??0) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:#dc2626" id="remise-row" <?= ($doc['remise']??0)?'':'style="display:none!important"' ?>>
                <span>Remise :</span><span id="disp-remise" class="mono"></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:#64748b">
                <span>HT Net :</span><span id="disp-ht-net" class="mono"><?= formatMoney($doc['ht_net']??0) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;color:#64748b;border-top:1px dashed #e2e8f0;padding-top:8px">
                <span>TVA :</span><span id="disp-tva" class="mono"><?= formatMoney($doc['tva_val']??0) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;background:var(--brand);color:#fff;border-radius:6px;padding:10px;font-weight:700;font-size:14px;margin-top:8px">
                <span>TOTAL TTC :</span><span id="disp-ttc" class="mono"><?= formatMoney($doc['ttc']??0) ?></span>
            </div>
        </div>
        
        <?php if($docConfig['type'] === 'facture'): ?>
        <div class="form-group" style="margin-top:14px">
            <label>Mode de règlement</label>
            <select name="mode_reglement" class="form-control">
                <option value="Virement bancaire" <?= ($doc['mode_reglement']??'')==='Virement bancaire'?'selected':'' ?>>Virement bancaire</option>
                <option value="Chèque" <?= ($doc['mode_reglement']??'')==='Chèque'?'selected':'' ?>>Chèque</option>
                <option value="Espèces" <?= ($doc['mode_reglement']??'')==='Espèces'?'selected':'' ?>>Espèces</option>
                <option value="Traite" <?= ($doc['mode_reglement']??'')==='Traite'?'selected':'' ?>>Traite</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-group" style="margin-top:14px">
            <label>Notes / Conditions</label>
            <textarea name="notes" class="form-control" rows="4" placeholder="Conditions particulières, remarques..."><?= htmlspecialchars($doc['notes'] ?? '') ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px;justify-content:center"><?= icon('arrow') ?> Enregistrer</button>
        
        <?php if(!$isNew): ?>
        <a href="<?= $docConfig['file'] ?>?action=print&id=<?= $doc['id'] ?>" target="_blank" class="btn btn-outline" style="width:100%;margin-top:8px;justify-content:center"><?= icon('print') ?> Aperçu / Imprimer</a>
        <?php endif; ?>
    </div>
</div>
</div>

</div><!-- /grid -->
</form>

<script>
const CURRENCY = '<?= CURRENCY ?>';

function fmt(n) {
    return n.toLocaleString('fr-TN',{minimumFractionDigits:3,maximumFractionDigits:3}) + ' ' + CURRENCY;
}

function recalc() {
    let ht = 0;
    document.querySelectorAll('.ligne-row').forEach(row => {
        const q = parseFloat(row.querySelector('[name="ligne_qte[]"]')?.value || 0);
        const p = parseFloat(row.querySelector('[name="ligne_pu[]"]')?.value  || 0);
        const total = q * p;
        const cell = row.querySelector('.total-cell');
        if (cell) cell.textContent = fmt(total);
        ht += total;
    });
    const remise_pct = parseFloat(document.getElementById('remise_field').value || 0);
    const tva_pct    = parseFloat(document.getElementById('tva_field').value    || 0);
    const timbreField = document.getElementById('timbre_field');
    const timbre = timbreField ? parseFloat(timbreField.value || 0) : 0;
    const remise_val = ht * remise_pct / 100;
    const ht_net = ht - remise_val;
    const tva_val = ht_net * tva_pct / 100;
    const ttc = ht_net + tva_val + timbre;
    
    document.getElementById('disp-ht').textContent     = fmt(ht);
    document.getElementById('disp-ht-net').textContent = fmt(ht_net);
    document.getElementById('disp-tva').textContent    = fmt(tva_val);
    document.getElementById('disp-ttc').textContent    = fmt(ttc);
    document.getElementById('total-ht-foot').textContent = fmt(ht);
    
    const remRow = document.getElementById('remise-row');
    if (remise_pct > 0) {
        remRow.style.display = '';
        document.getElementById('disp-remise').textContent = '- ' + fmt(remise_val);
    } else {
        remRow.style.display = 'none';
    }
}

let ligneCount = <?= count($initLignes) ?>;

function addLigne(ref='', desig='', qte=1, pu=0, unite='jour', desc='') {
    const idx = ligneCount++;
    const tr = document.createElement('tr');
    tr.className = 'ligne-row';
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td><input type="text" name="ligne_ref[]" class="form-control" style="font-size:11px" value="${ref}" placeholder="Réf."></td>
        <td>
            <input type="text" name="ligne_desig[]" class="form-control" value="${desig}" placeholder="Désignation" required>
            <input type="text" name="ligne_desc[]" class="form-control" style="font-size:11px;margin-top:4px;color:#64748b" value="${desc}" placeholder="Description (optionnel)">
        </td>
        <td><input type="number" name="ligne_qte[]" class="form-control total-calc" step="0.01" min="0" value="${qte}" oninput="recalc()"></td>
        <td>
            <select name="ligne_unite[]" class="form-control" style="font-size:12px">
                ${['jour','semaine','mois','heure','forfait','u'].map(u=>`<option value="${u}" ${u===unite?'selected':''}>${u}</option>`).join('')}
            </select>
        </td>
        <td><input type="number" name="ligne_pu[]" class="form-control total-calc" step="0.001" min="0" value="${pu}" oninput="recalc()"></td>
        <td class="mono total-cell" style="font-weight:600;color:var(--brand)">${fmt(qte*pu)}</td>
        <td><button type="button" class="remove-ligne" onclick="removeLigne(this)">✕</button></td>
    `;
    document.getElementById('lignes-body').appendChild(tr);
    recalc();
}

function addMaterielLigne(sel) {
    if (!sel.value) return;
    const m = JSON.parse(sel.value);
    addLigne(m.reference||'', m.designation||'', 1, m.prix_jour||0, 'jour', m.description||'');
    sel.selectedIndex = 0;
}

function removeLigne(btn) {
    const rows = document.querySelectorAll('.ligne-row');
    if (rows.length <= 1) return;
    btn.closest('tr').remove();
    recalc();
}

function fillClient(val) {
    if (!val) return;
    const c = JSON.parse(val);
    document.getElementById('client_id_field').value = c.id || '';
    document.getElementById('f_nom').value       = c.nom || '';
    document.getElementById('f_contact').value   = c.contact || '';
    document.getElementById('f_adresse').value   = c.adresse || '';
    document.getElementById('f_ville').value     = c.ville || '';
    document.getElementById('f_telephone').value = c.telephone || '';
    document.getElementById('f_email').value     = c.email || '';
    document.getElementById('f_rc').value        = c.rc || '';
    document.getElementById('f_tva').value       = c.tva || '';
}

function fillChantier(val) {
    if (!val) return;
    const c = JSON.parse(val);
    const field = document.getElementById('chantier_field');
    const adresse = document.querySelector('#chantier_select option:checked')?.dataset.adresse || '';
    if (field) field.value = c.nom || '';
    const livraison = document.querySelector('input[name="lieu_livraison"]');
    if (livraison && adresse) livraison.value = adresse;
}
</script>

<?php
// ========== LISTE ==========
elseif(!$id):
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
    <div></div>
    <a href="<?= $docConfig['file'] ?>?action=new" class="btn btn-primary"><?= icon('plus') ?> Nouveau <?= $docConfig['label'] ?></a>
</div>

<div class="card">
    <div class="card-header">
        <h3><?= $docConfig['title'] ?> (<?= count($docs) ?>)</h3>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Numéro</th>
                    <th>Date</th>
                    <th>Client</th>
                    <?php if(in_array($docConfig['type'],['devis','commande','facture'])): ?>
                    <th>Début</th><th>Fin</th>
                    <?php endif; ?>
                    <th>HT</th>
                    <th>TTC</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach(array_reverse($docs) as $d): ?>
            <tr>
                <td><span class="mono" style="font-weight:700;color:var(--brand)"><?= htmlspecialchars($d['numero'] ?? '') ?></span></td>
                <td><?= dateFR($d['date'] ?? '') ?></td>
                <td style="font-weight:500"><?= htmlspecialchars($d['client']['nom'] ?? '-') ?></td>
                <?php if(in_array($docConfig['type'],['devis','commande','facture'])): ?>
                <td><?= $d['date_debut'] ? dateFR($d['date_debut']) : '-' ?></td>
                <td><?= $d['date_fin'] ? dateFR($d['date_fin']) : '-' ?></td>
                <?php endif; ?>
                <td class="mono"><?= formatMoney($d['ht'] ?? 0) ?></td>
                <td class="mono" style="font-weight:700"><?= formatMoney($d['ttc'] ?? 0) ?></td>
                <td><?= statusBadge($d['statut'] ?? 'brouillon') ?></td>
                <td>
                    <div style="display:flex;gap:4px">
                        <a href="<?= $docConfig['file'] ?>?id=<?= $d['id'] ?>" class="btn btn-light btn-sm" title="Modifier"><?= icon('edit') ?></a>
                        <a href="<?= $docConfig['file'] ?>?action=print&id=<?= $d['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="Imprimer"><?= icon('print') ?></a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($docs)): ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#94a3b8">
                <div style="font-size:40px;margin-bottom:10px">📄</div>
                Aucun document. <a href="?action=new" style="color:var(--brand)">Créer le premier <?= strtolower($docConfig['label']) ?></a>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif;

renderFooter();

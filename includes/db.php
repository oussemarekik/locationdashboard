<?php

function getDbConnection(): ?PDO {
    static $pdo = null;
    static $resolved = false;
    static $schemaReady = false;

    if ($resolved) {
        return $pdo;
    }

    $resolved = true;

    if (!defined('DB_NAME') || DB_NAME === '') {
        return null;
    }

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $e) {
        try {
            $serverDsn = sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
            $serverPdo = new PDO($serverDsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $serverPdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            $pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $inner) {
            $pdo = null;
        }
    }

    if ($pdo && !$schemaReady) {
        try {
            ensureMysqlSchema($pdo);
            $schemaReady = true;
        } catch (Throwable $e) {
            $pdo = null;
        }
    }

    return $pdo;
}

function usingMySql(): bool {
    return getDbConnection() instanceof PDO;
}

function mysqlTableName(string $file): ?string {
    return [
        'users' => 'users',
        'clients' => 'clients',
        'chauffeurs' => 'chauffeurs',
        'chantiers' => 'chantiers',
        'materiels' => 'materiels',
        'devis' => 'documents_devis',
        'commandes' => 'documents_commandes',
        'livraisons' => 'documents_livraisons',
        'factures' => 'documents_factures',
        'counters' => 'counters',
    ][$file] ?? null;
}

function isListArray(array $data): bool {
    if ($data === []) {
        return true;
    }

    return array_keys($data) === range(0, count($data) - 1);
}

function normalizeRows(array $data): array {
    if ($data === []) {
        return [];
    }

    if (isListArray($data)) {
        return $data;
    }

    $rows = [];
    foreach ($data as $key => $row) {
        if (is_array($row)) {
            if (!isset($row['id']) && is_string($key)) {
                $row['id'] = $key;
            }
            $rows[] = $row;
            continue;
        }

        $rows[] = ['id' => (string)$key, 'value' => $row];
    }

    return $rows;
}

function decodeJsonField($value): array {
    if ($value === null || $value === '') {
        return [];
    }

    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function loadDataFromMysql(PDO $pdo, string $file): ?array {
    $table = mysqlTableName($file);
    if (!$table) {
        return null;
    }

    if ($file === 'counters') {
        $stmt = $pdo->query('SELECT name, value FROM counters ORDER BY name ASC');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['name']] = (int)$row['value'];
        }
        return $result;
    }

    $stmt = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY created_at ASC, id ASC');
    $rows = $stmt->fetchAll();

    if ($file === 'users') {
        $result = [];
        foreach ($rows as $row) {
            $result[$row['id']] = $row;
        }
        return $result;
    }

    if ($file === 'clients' || $file === 'chauffeurs' || $file === 'chantiers' || $file === 'materiels') {
        return $rows;
    }

    if (in_array($file, ['devis', 'commandes', 'livraisons', 'factures'], true)) {
        foreach ($rows as &$row) {
            $row['client'] = decodeJsonField($row['client_json'] ?? null);
            $row['lignes'] = decodeJsonField($row['lignes_json'] ?? null);
            unset($row['client_json'], $row['lignes_json']);

            $row['issuer_profile'] = $row['issuer_profile'] ?? 'societe';
            $row['linked_facture_id'] = $row['linked_facture_id'] ?? '';
            $row['linked_livraison_id'] = $row['linked_livraison_id'] ?? '';

            foreach (['remise', 'tva', 'ht', 'ht_net', 'tva_val', 'ttc'] as $numericField) {
                if (isset($row[$numericField])) {
                    $row[$numericField] = (float)$row[$numericField];
                }
            }
            // timbre may be present
            if (isset($row['timbre'])) {
                $row['timbre'] = (float)$row['timbre'];
            } else {
                $row['timbre'] = 0.0;
            }
        }

        return $rows;
    }

    return null;
}

function saveDataToMysql(PDO $pdo, string $file, array $data): ?bool {
    $table = mysqlTableName($file);
    if (!$table) {
        return null;
    }

    try {
        $pdo->beginTransaction();

        if ($file === 'counters') {
            $pdo->exec('DELETE FROM counters');
            $stmt = $pdo->prepare('INSERT INTO counters (name, value) VALUES (:name, :value)');
            foreach ($data as $name => $value) {
                $stmt->execute([
                    ':name' => (string)$name,
                    ':value' => (int)$value,
                ]);
            }
            $pdo->commit();
            return true;
        }

        $pdo->exec('DELETE FROM ' . $table);

        if ($file === 'users') {
            $stmt = $pdo->prepare('INSERT INTO users (id, email, password_hash, name, role, created_at, last_login) VALUES (:id, :email, :password_hash, :name, :role, :created_at, :last_login)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid('user_'),
                    ':email' => $row['email'] ?? '',
                    ':password_hash' => $row['password_hash'] ?? '',
                    ':name' => $row['name'] ?? '',
                    ':role' => $row['role'] ?? 'user',
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':last_login' => $row['last_login'] ?? null,
                ]);
            }

            $pdo->commit();
            return true;
        }

        if ($file === 'clients') {
            $stmt = $pdo->prepare('INSERT INTO clients (id, nom, contact, adresse, ville, telephone, email, rc, tva, notes, created_at, updated_at) VALUES (:id, :nom, :contact, :adresse, :ville, :telephone, :email, :rc, :tva, :notes, :created_at, :updated_at)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid('C'),
                    ':nom' => $row['nom'] ?? '',
                    ':contact' => $row['contact'] ?? '',
                    ':adresse' => $row['adresse'] ?? '',
                    ':ville' => $row['ville'] ?? '',
                    ':telephone' => $row['telephone'] ?? '',
                    ':email' => $row['email'] ?? '',
                    ':rc' => $row['rc'] ?? '',
                    ':tva' => $row['tva'] ?? '',
                    ':notes' => $row['notes'] ?? '',
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        }

        if ($file === 'chauffeurs') {
            $stmt = $pdo->prepare('INSERT INTO chauffeurs (id, nom, telephone, adresse, permis, actif, notes, created_at, updated_at) VALUES (:id, :nom, :telephone, :adresse, :permis, :actif, :notes, :created_at, :updated_at)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid('CH'),
                    ':nom' => $row['nom'] ?? '',
                    ':telephone' => $row['telephone'] ?? '',
                    ':adresse' => $row['adresse'] ?? '',
                    ':permis' => $row['permis'] ?? '',
                    ':actif' => isset($row['actif']) ? (int)$row['actif'] : 1,
                    ':notes' => $row['notes'] ?? '',
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        }

        if ($file === 'chantiers') {
            $stmt = $pdo->prepare('INSERT INTO chantiers (id, nom, client, adresse, responsable, telephone, date_debut, date_fin, statut, notes, created_at, updated_at) VALUES (:id, :nom, :client, :adresse, :responsable, :telephone, :date_debut, :date_fin, :statut, :notes, :created_at, :updated_at)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid('CHT'),
                    ':nom' => $row['nom'] ?? '',
                    ':client' => $row['client'] ?? '',
                    ':adresse' => $row['adresse'] ?? '',
                    ':responsable' => $row['responsable'] ?? '',
                    ':telephone' => $row['telephone'] ?? '',
                    ':date_debut' => $row['date_debut'] ?? '',
                    ':date_fin' => $row['date_fin'] ?? '',
                    ':statut' => $row['statut'] ?? 'actif',
                    ':notes' => $row['notes'] ?? '',
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        }

        if ($file === 'materiels') {
            $stmt = $pdo->prepare('INSERT INTO materiels (id, reference, designation, categorie, description, prix_jour, prix_semaine, prix_mois, unite, stock, chauffeur_id, chauffeur_nom, chauffeur_telephone, etat_machine, date_vidange, montant_reduit, created_at, updated_at) VALUES (:id, :reference, :designation, :categorie, :description, :prix_jour, :prix_semaine, :prix_mois, :unite, :stock, :chauffeur_id, :chauffeur_nom, :chauffeur_telephone, :etat_machine, :date_vidange, :montant_reduit, :created_at, :updated_at)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid('M'),
                    ':reference' => $row['reference'] ?? '',
                    ':designation' => $row['designation'] ?? '',
                    ':categorie' => $row['categorie'] ?? '',
                    ':description' => $row['description'] ?? '',
                    ':prix_jour' => $row['prix_jour'] ?? 0,
                    ':prix_semaine' => $row['prix_semaine'] ?? 0,
                    ':prix_mois' => $row['prix_mois'] ?? 0,
                    ':unite' => $row['unite'] ?? 'jour',
                    ':stock' => $row['stock'] ?? 0,
                    ':chauffeur_id' => $row['chauffeur_id'] ?? '',
                    ':chauffeur_nom' => $row['chauffeur_nom'] ?? '',
                    ':chauffeur_telephone' => $row['chauffeur_telephone'] ?? '',
                    ':etat_machine' => $row['etat_machine'] ?? 'service',
                    ':date_vidange' => $row['date_vidange'] ?? '',
                    ':montant_reduit' => $row['montant_reduit'] ?? 0,
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        }

        if (in_array($file, ['devis', 'commandes', 'livraisons', 'factures'], true)) {
            $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (id, numero, date, issuer_profile, linked_facture_id, linked_livraison_id, date_echeance, date_debut, date_fin, date_livraison, chantier, lieu_livraison, moyen_transport, nom_camion, matricule_camion, client_json, lignes_json, remise, tva, ht, ht_net, tva_val, timbre, ttc, statut, notes, mode_reglement, created_at, updated_at) VALUES (:id, :numero, :date, :issuer_profile, :linked_facture_id, :linked_livraison_id, :date_echeance, :date_debut, :date_fin, :date_livraison, :chantier, :lieu_livraison, :moyen_transport, :nom_camion, :matricule_camion, :client_json, :lignes_json, :remise, :tva, :ht, :ht_net, :tva_val, :timbre, :ttc, :statut, :notes, :mode_reglement, :created_at, :updated_at)');
            foreach (normalizeRows($data) as $row) {
                $stmt->execute([
                    ':id' => $row['id'] ?? uniqid(),
                    ':numero' => $row['numero'] ?? '',
                    ':date' => $row['date'] ?? date('Y-m-d'),
                    ':issuer_profile' => $row['issuer_profile'] ?? 'societe',
                    ':linked_facture_id' => $row['linked_facture_id'] ?? '',
                    ':linked_livraison_id' => $row['linked_livraison_id'] ?? '',
                    ':date_echeance' => $row['date_echeance'] ?? '',
                    ':date_debut' => $row['date_debut'] ?? '',
                    ':date_fin' => $row['date_fin'] ?? '',
                    ':date_livraison' => $row['date_livraison'] ?? '',
                    ':chantier' => $row['chantier'] ?? '',
                    ':lieu_livraison' => $row['lieu_livraison'] ?? '',
                    ':moyen_transport' => $row['moyen_transport'] ?? '',
                    ':nom_camion' => $row['nom_camion'] ?? '',
                    ':matricule_camion' => $row['matricule_camion'] ?? '',
                    ':client_json' => json_encode($row['client'] ?? [], JSON_UNESCAPED_UNICODE),
                    ':lignes_json' => json_encode($row['lignes'] ?? [], JSON_UNESCAPED_UNICODE),
                    ':remise' => $row['remise'] ?? 0,
                    ':tva' => $row['tva'] ?? TVA_RATE,
                    ':ht' => $row['ht'] ?? 0,
                    ':ht_net' => $row['ht_net'] ?? 0,
                    ':tva_val' => $row['tva_val'] ?? 0,
                    ':timbre' => $row['timbre'] ?? 0,
                    ':ttc' => $row['ttc'] ?? 0,
                    ':statut' => $row['statut'] ?? 'brouillon',
                    ':notes' => $row['notes'] ?? '',
                    ':mode_reglement' => $row['mode_reglement'] ?? '',
                    ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->commit();
            return true;
        }

        $pdo->rollBack();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('DB save error for ' . $file . ': ' . $e->getMessage());

        return false;
    }
}

function mysqlColumnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute([
        ':table' => $table,
        ':column' => $column,
    ]);

    return (int)$stmt->fetchColumn() > 0;
}

function ensureColumnExists(PDO $pdo, string $table, string $column, string $definition): void {
    if (!mysqlColumnExists($pdo, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

function incrementCounter(string $key): int {
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('INSERT INTO counters (name, value) VALUES (:name, 1) ON DUPLICATE KEY UPDATE value = LAST_INSERT_ID(value + 1)');
            $stmt->execute([':name' => $key]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            // Fall back to JSON below.
        }
    }

    $counter = loadData('counters');
    $counter[$key] = ($counter[$key] ?? 0) + 1;
    saveData('counters', $counter);
    return $counter[$key];
}

function ensureMysqlSchema(PDO $pdo): void {
    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS counters (
    name VARCHAR(50) NOT NULL PRIMARY KEY,
    value INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(190) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL,
    last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS clients (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    contact VARCHAR(190) NULL,
    adresse VARCHAR(255) NULL,
    ville VARCHAR(120) NULL,
    telephone VARCHAR(80) NULL,
    email VARCHAR(190) NULL,
    rc VARCHAR(120) NULL,
    tva VARCHAR(120) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS chauffeurs (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    telephone VARCHAR(80) NULL,
    adresse VARCHAR(255) NULL,
    permis VARCHAR(80) NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS chantiers (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    nom VARCHAR(190) NOT NULL,
    client VARCHAR(190) NULL,
    adresse VARCHAR(255) NULL,
    responsable VARCHAR(190) NULL,
    telephone VARCHAR(80) NULL,
    date_debut DATE NULL,
    date_fin DATE NULL,
    statut VARCHAR(50) NOT NULL DEFAULT 'actif',
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS materiels (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    reference VARCHAR(120) NULL,
    designation VARCHAR(190) NOT NULL,
    categorie VARCHAR(120) NULL,
    description TEXT NULL,
    prix_jour DECIMAL(12,3) NOT NULL DEFAULT 0,
    prix_semaine DECIMAL(12,3) NOT NULL DEFAULT 0,
    prix_mois DECIMAL(12,3) NOT NULL DEFAULT 0,
    unite VARCHAR(30) NOT NULL DEFAULT 'jour',
    stock INT NOT NULL DEFAULT 0,
    chauffeur_id VARCHAR(80) NULL,
    chauffeur_nom VARCHAR(190) NULL,
    chauffeur_telephone VARCHAR(80) NULL,
    etat_machine VARCHAR(50) NOT NULL DEFAULT 'service',
    date_vidange DATE NULL,
    montant_reduit DECIMAL(12,3) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    ensureColumnExists($pdo, 'materiels', 'chauffeur_id', 'VARCHAR(80) NULL');

    ensureColumnExists($pdo, 'materiels', 'chauffeur_nom', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'materiels', 'chauffeur_telephone', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'materiels', 'etat_machine', "VARCHAR(50) NOT NULL DEFAULT 'service'");
    ensureColumnExists($pdo, 'materiels', 'date_vidange', 'DATE NULL');
    ensureColumnExists($pdo, 'materiels', 'montant_reduit', 'DECIMAL(12,3) NOT NULL DEFAULT 0');

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS documents_devis (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    numero VARCHAR(80) NOT NULL,
    date DATE NOT NULL,
    date_echeance DATE NULL,
    date_debut DATE NULL,
    date_fin DATE NULL,
    date_livraison DATE NULL,
    chantier VARCHAR(190) NULL,
    lieu_livraison VARCHAR(190) NULL,
    moyen_transport VARCHAR(190) NULL,
    nom_camion VARCHAR(190) NULL,
    matricule_camion VARCHAR(190) NULL,
    client_json JSON NULL,
    lignes_json JSON NULL,
    remise DECIMAL(12,3) NOT NULL DEFAULT 0,
    tva DECIMAL(12,3) NOT NULL DEFAULT 19,
    ht DECIMAL(12,3) NOT NULL DEFAULT 0,
    ht_net DECIMAL(12,3) NOT NULL DEFAULT 0,
    tva_val DECIMAL(12,3) NOT NULL DEFAULT 0,
    timbre DECIMAL(12,3) NOT NULL DEFAULT 0,
    ttc DECIMAL(12,3) NOT NULL DEFAULT 0,
    issuer_profile VARCHAR(50) NOT NULL DEFAULT 'societe',
    statut VARCHAR(50) NOT NULL DEFAULT 'brouillon',
    notes TEXT NULL,
    mode_reglement VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL);

    $pdo->exec('CREATE TABLE IF NOT EXISTS documents_commandes LIKE documents_devis');
    $pdo->exec('CREATE TABLE IF NOT EXISTS documents_livraisons LIKE documents_devis');
    $pdo->exec('CREATE TABLE IF NOT EXISTS documents_factures LIKE documents_devis');

    // Backward-compatible migrations for existing installs
    ensureColumnExists($pdo, 'documents_devis', 'timbre', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    ensureColumnExists($pdo, 'documents_commandes', 'timbre', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    ensureColumnExists($pdo, 'documents_livraisons', 'timbre', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    ensureColumnExists($pdo, 'documents_factures', 'timbre', 'DECIMAL(12,3) NOT NULL DEFAULT 0');
    ensureColumnExists($pdo, 'documents_devis', 'issuer_profile', "VARCHAR(50) NOT NULL DEFAULT 'societe'");
    ensureColumnExists($pdo, 'documents_commandes', 'issuer_profile', "VARCHAR(50) NOT NULL DEFAULT 'societe'");
    ensureColumnExists($pdo, 'documents_livraisons', 'issuer_profile', "VARCHAR(50) NOT NULL DEFAULT 'societe'");
    ensureColumnExists($pdo, 'documents_factures', 'issuer_profile', "VARCHAR(50) NOT NULL DEFAULT 'societe'");
    ensureColumnExists($pdo, 'documents_devis', 'linked_facture_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'linked_facture_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'linked_facture_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_factures', 'linked_facture_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_devis', 'linked_livraison_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'linked_livraison_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'linked_livraison_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_factures', 'linked_livraison_id', 'VARCHAR(80) NULL');
    ensureColumnExists($pdo, 'documents_devis', 'date_livraison', 'DATE NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'date_livraison', 'DATE NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'date_livraison', 'DATE NULL');
    ensureColumnExists($pdo, 'documents_factures', 'date_livraison', 'DATE NULL');
    ensureColumnExists($pdo, 'documents_devis', 'moyen_transport', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'moyen_transport', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'moyen_transport', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_factures', 'moyen_transport', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_devis', 'nom_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'nom_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'nom_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_factures', 'nom_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_devis', 'matricule_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_commandes', 'matricule_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_livraisons', 'matricule_camion', 'VARCHAR(190) NULL');
    ensureColumnExists($pdo, 'documents_factures', 'matricule_camion', 'VARCHAR(190) NULL');
}

function getCounterValue(string $key): int {
    $pdo = getDbConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->prepare('SELECT value FROM counters WHERE name = :name');
            $stmt->execute([':name' => $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return (int)$row['value'];
            return 0;
        } catch (Throwable $e) {
            // fall through to JSON fallback
        }
    }

    $counter = loadData('counters');
    return (int)($counter[$key] ?? 0);
}
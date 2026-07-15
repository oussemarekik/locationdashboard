CREATE TABLE IF NOT EXISTS counters (
    name VARCHAR(50) NOT NULL PRIMARY KEY,
    value INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(80) NOT NULL PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(190) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'user',
    created_at DATETIME NOT NULL,
    last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    statut VARCHAR(50) NOT NULL DEFAULT 'brouillon',
    notes TEXT NULL,
    mode_reglement VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS documents_commandes LIKE documents_devis;
CREATE TABLE IF NOT EXISTS documents_livraisons LIKE documents_devis;
CREATE TABLE IF NOT EXISTS documents_factures LIKE documents_devis;

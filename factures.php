<?php
$docType   = 'facture';
$docConfig = [
    'type'     => 'facture',
    'label'    => 'Facture',
    'title'    => '💶 Factures',
    'nav_key'  => 'factures',
    'file'     => 'factures.php',
    'prefix'   => 'FA',
    'data_key' => 'factures',
    'statuts'  => [
        'brouillon' => 'Brouillon',
        'envoye'    => 'Envoyée',
        'en_cours'  => 'En attente',
        'paye'      => 'Payée',
        'annule'    => 'Annulée',
    ],
];
require_once 'includes/document_page.php';

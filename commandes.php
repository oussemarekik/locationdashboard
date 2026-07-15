<?php
$docType   = 'commande';
$docConfig = [
    'type'     => 'commande',
    'label'    => 'Bon de Commande',
    'title'    => '🛒 Bons de Commande',
    'nav_key'  => 'commandes',
    'file'     => 'commandes.php',
    'prefix'   => 'BC',
    'data_key' => 'commandes',
    'statuts'  => [
        'brouillon' => 'Brouillon',
        'envoye'    => 'Envoyé',
        'valide'    => 'Validé',
        'en_cours'  => 'En cours',
        'annule'    => 'Annulé',
    ],
];
require_once 'includes/document_page.php';

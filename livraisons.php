<?php
$docType   = 'livraison';
$docConfig = [
    'type'     => 'livraison',
    'label'    => 'Bon de Livraison',
    'title'    => '🚚 Bons de Livraison',
    'nav_key'  => 'livraisons',
    'file'     => 'livraisons.php',
    'prefix'   => 'BL',
    'data_key' => 'livraisons',
    'statuts'  => [
        'brouillon' => 'Brouillon',
        'en_cours'  => 'En cours',
        'livre'     => 'Livré',
        'retour'    => 'Retourné',
        'annule'    => 'Annulé',
    ],
];
require_once 'includes/document_page.php';

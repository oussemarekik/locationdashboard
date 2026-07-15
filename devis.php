<?php
$docType   = 'devis';
$docConfig = [
    'type'     => 'devis',
    'label'    => 'Devis',
    'title'    => '📋 Devis',
    'nav_key'  => 'devis',
    'file'     => 'devis.php',
    'prefix'   => 'DV',
    'data_key' => 'devis',
    'statuts'  => [
        'brouillon' => 'Brouillon',
        'envoye'    => 'Envoyé',
        'accepte'   => 'Accepté',
        'refuse'    => 'Refusé',
        'annule'    => 'Annulé',
    ],
];
require_once 'includes/document_page.php';

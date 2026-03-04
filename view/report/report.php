<?php
$rid = siteaudit::$report['meta']['id'] ?? '';

$siteauditQuickRid = $rid;
require __DIR__ . "/../_partial/quickhelp.php";

echo '<div class="siteauditToolbar">';

echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0),
    'value' => template::ico('left') . ' Retour'
]);

echo template::button('siteauditReports', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/reports',
    'value' => template::ico('list') . ' Rapports',
    'help'  => 'Historique'
]);

if ($rid) {
    echo template::button('siteauditActions', [
        'class' => 'buttonGrey siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $rid,
        'value' => template::ico('gear') . ' Centre d’actions',
        'help'  => 'Centre d’actions'
    ]);

    echo template::button('siteauditExport', [
        'class' => 'buttonGrey siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/exportmd/' . $rid,
        'value' => template::ico('download') . ' Exporter (MD)',
        'help'  => 'Exporter en Markdown'
    ]);
}

if ($rid && $this->getUser('role') >= siteaudit::ROLE_ADMIN) {
    echo template::button('siteauditMedia', [
        'class' => 'buttonGrey siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/1',
        'value' => template::ico('list') . ' Médias',
        'help'  => 'Médias non utilisés'
    ]);

    echo template::button('siteauditFixAll', [
        'class' => 'buttonGreen siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/fixfileall/' . $rid,
        'value' => template::ico('check') . ' Réparer les liens sûrs',
        'help'  => 'Réparer tous les liens de fichiers réparables (suggestion unique)'
    ]);
}

echo template::button('siteauditRun', [
    'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Relancer un audit',
    'help'  => 'Relancer un audit'
]);

echo '</div>';

echo '<hr>';

if (empty(siteaudit::$report)) {
    echo '<p>Aucun rapport à afficher.</p>';
    return;
}

$r = siteaudit::$report;
$meta = $r['meta'] ?? [];
$sum = $r['summary'] ?? [];
$checks = $r['checks'] ?? [];

echo '<h2>Rapport — ' . htmlspecialchars($meta['id'] ?? '') . '</h2>';
echo '<p><strong>Date :</strong> ' . htmlspecialchars(siteaudit::formatDateDisplay((string)($meta['created_at'] ?? ''))) . '<br>';
echo '<strong>PHP :</strong> ' . htmlspecialchars($meta['php'] ?? '') . '<br>';
echo '<strong>Zwii :</strong> ' . htmlspecialchars($meta['zwii'] ?? '') . '</p>';

// Cartes résumé
$cards = [
    ['k' => 'pages', 'label' => 'Pages', 'val' => (int)($sum['pages'] ?? 0)],
    ['k' => 'orphans', 'label' => 'Orphelines', 'val' => (int)($sum['orphans'] ?? 0)],
    ['k' => 'brokenPageLinks', 'label' => 'Liens internes cassés', 'val' => (int)($sum['brokenPageLinks'] ?? 0)],
    ['k' => 'brokenSpaceLinks', 'label' => 'Liens espaces cassés', 'val' => (int)($sum['brokenSpaceLinks'] ?? 0)],
    ['k' => 'spacesTotal', 'label' => 'Espaces', 'val' => (int)($sum['spacesTotal'] ?? 0)],
    ['k' => 'totalEnrolments', 'label' => 'Inscriptions', 'val' => (int)($sum['totalEnrolments'] ?? 0)],
    ['k' => 'brokenFileLinks', 'label' => 'Liens fichiers manquants', 'val' => (int)($sum['brokenFileLinks'] ?? 0)],
    ['k' => 'fixableFileLinks', 'label' => 'Liens réparables', 'val' => (int)($sum['fixableFileLinks'] ?? 0)],
    ['k' => 'ambiguousFileLinks', 'label' => 'Liens ambigus', 'val' => (int)($sum['ambiguousFileLinks'] ?? 0)],
    ['k' => 'mediaUnused', 'label' => 'Médias inutilisés', 'val' => (int)($sum['mediaUnused'] ?? 0)],
    ['k' => 'mediaUnusedBytes', 'label' => 'Taille estimée', 'val' => isset($sum['mediaUnusedBytes']) ? siteaudit::formatBytes((int)$sum['mediaUnusedBytes']) : '—'],
    ['k' => 'heavyFiles', 'label' => 'Fichiers lourds', 'val' => (int)($sum['heavyFiles'] ?? 0)],
];

echo '<div class="siteauditCards">';
foreach ($cards as $c) {
    echo '<div class="siteauditCard"><div class="siteauditCardLabel">' . htmlspecialchars($c['label']) . '</div><div class="siteauditCardValue">' . htmlspecialchars((string)$c['val']) . '</div></div>';
}
echo '</div>';

function renderList($title, $items, $cols, $headers, $rowBuilder) {
    echo '<h3>' . htmlspecialchars($title) . '</h3>';
    if (empty($items)) {
        echo '<p><em>Rien à signaler.</em></p>';
        return;
    }
    $rows = [];
    foreach ($items as $i => $it) {
        $rows[] = $rowBuilder($it, $i);
    }
    echo template::table($cols, $rows, $headers);
}

renderList('Pages orphelines', $checks['orphans'] ?? [], [3,3,6], ['Page', 'Langue', 'Titre'], function($it, $i){
    return [ htmlspecialchars($it['pageId'] ?? ''), htmlspecialchars($it['locale'] ?? ''), htmlspecialchars($it['title'] ?? '') ];
});

renderList('Problèmes de hiérarchie', $checks['hierarchyIssues'] ?? [], [3,3,6], ['Page', 'Langue', 'Détail'], function($it, $i){
    $detail = ($it['issue'] ?? '') . (isset($it['parent']) ? ' — parent : ' . $it['parent'] : '');
    return [ htmlspecialchars($it['pageId'] ?? ''), htmlspecialchars($it['locale'] ?? ''), htmlspecialchars($detail) ];
});

renderList('Liens internes cassés', $checks['brokenPageLinks'] ?? [], [3,9], ['Depuis', 'URL'], function($it, $i){
    return [ htmlspecialchars($it['from'] ?? ''), htmlspecialchars((string)($it['url'] ?? '')) ];
});

renderList('Liens vers espaces cassés', $checks['brokenSpaceLinks'] ?? [], [3,3,6], ['Depuis', 'Espace', 'URL'], function($it, $i){
    return [
        htmlspecialchars($it['from'] ?? ''),
        htmlspecialchars((string)($it['spaceId'] ?? '')),
        htmlspecialchars((string)($it['url'] ?? ''))
    ];
});


renderList('Liens vers fichiers manquants', $checks['brokenFileLinks'] ?? [], [3,4,3,2], ['Page', 'Fichier', 'Suggestion', 'Action'], function($it, $i) use ($rid){
    $from = htmlspecialchars($it['from'] ?? '');
    $file = htmlspecialchars($it['file'] ?? '');
    $suggested = htmlspecialchars($it['suggested'] ?? '');
    $opts = (array)($it['suggestions'] ?? []);
    $fixed = !empty($it['fixed']);
    $badge = $fixed ? '<span class="siteauditBadgeOk">Corrigé</span>' : '';

    $suggestTxt = '<em>—</em>';
    if ($suggested !== '') {
        $suggestTxt = $suggested;
    } elseif (!empty($opts)) {
        $suggestTxt = '<em>Choix requis</em> <span class="siteauditBadgeGrey">' . count($opts) . '</span>';
    }

    $btn = '';
    if (!$fixed && $this->getUser('role') >= siteaudit::ROLE_ADMIN) {
        if ($suggested !== '') {
            $btn = template::button('siteauditFix' . $i, [
                'class' => 'buttonGreen',
                'href'  => helper::baseUrl() . $this->getUrl(0) . '/fixfile/' . $rid . '/' . $i,
                'value' => template::ico('check') . ' Corriger',
                'help'  => 'Corriger ce lien (safe)'
            ]);
        } elseif (!empty($opts)) {
            $btn = template::button('siteauditChoose' . $i, [
                'class' => 'buttonGrey',
                'href'  => helper::baseUrl() . $this->getUrl(0) . '/choosefix/' . $rid . '/' . $i,
                'value' => template::ico('pencil') . ' Choisir',
                'help'  => 'Choisir le bon fichier'
            ]);
        }
    }

    return [ $from, $file, $suggestTxt . $badge, '<div class="siteauditRowBtns">' . $btn . '</div>' ];
});

renderList('Médias non utilisés (échantillon)', $checks['unusedMedia'] ?? [], [8,2,2], ['Chemin', 'Ext', 'Taille'], function($it, $i){
    $size = isset($it['size']) ? siteaudit::formatBytes((int)$it['size']) : '';
    return [ htmlspecialchars($it['rel'] ?? ''), htmlspecialchars($it['ext'] ?? ''), htmlspecialchars($size) ];
});

renderList('Fichiers trop lourds (échantillon)', $checks['heavyFiles'] ?? [], [8,2,2], ['Chemin', 'Ext', 'Taille'], function($it, $i){
    $size = isset($it['size']) ? siteaudit::formatBytes((int)$it['size']) : '';
    return [ htmlspecialchars($it['rel'] ?? ''), htmlspecialchars($it['ext'] ?? ''), htmlspecialchars($size) ];
});

renderList('Titres en doublon', $checks['titleDuplicates'] ?? [], [3,4,5], ['Langue', 'Titre', 'Pages'], function($it, $i){
    $pages = isset($it['pages']) ? implode(', ', $it['pages']) : '';
    return [ htmlspecialchars($it['locale'] ?? ''), htmlspecialchars($it['title'] ?? ''), htmlspecialchars($pages) ];
});
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
.siteauditCards{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin:15px 0}
.siteauditCard{background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:10px}
.siteauditCardLabel{font-size:.85em;opacity:.75}
.siteauditCardValue{font-size:1.2em;font-weight:700;margin-top:4px}
.siteauditBadgeOk{display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(0,0,0,.03);font-size:.85em}
.siteauditBadgeGrey{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.02);font-size:.85em}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
@media (max-width: 980px){.siteauditCards{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width: 560px){.siteauditCards{grid-template-columns:repeat(2,minmax(0,1fr));}.siteauditToolbar a{flex:1 1 140px}}
</style>

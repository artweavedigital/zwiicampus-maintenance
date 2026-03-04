<?php
$last = $this->getData(['module', $this->getUrl(0), 'lastReport']);
$lastId = is_array($last) ? (string)($last['id'] ?? '') : '';

$siteauditQuickRid = $lastId;
require __DIR__ . "/../_partial/quickhelp.php";

echo '<div class="siteauditToolbar">';

echo template::button('siteauditConfig', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/config',
    'value' => template::ico('gear') . ' Configuration',
    'help'  => 'Configuration'
]);

echo template::button('siteauditReports', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/reports',
    'value' => template::ico('list') . ' Rapports',
    'help'  => 'Historique des rapports'
]);

echo template::button('siteauditSpaces', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/spaces',
    'value' => template::ico('graduation-cap') . ' Espaces',
    'help'  => 'Espaces de formation'
]);

echo template::button('siteauditEnrolments', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/enrolments',
    'value' => template::ico('users') . ' Inscriptions',
    'help'  => 'Inscriptions'
]);


echo template::button('siteauditRun', [
    'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Lancer un audit',
    'help'  => 'Lancer un audit'
]);

if ($this->getUser('role') >= siteaudit::ROLE_ADMIN) {
    echo template::button('siteauditClearTmp', [
        'class' => 'buttonRed siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/cleartmp',
        'value' => template::ico('trash') . ' Nettoyer /site/tmp',
        'help'  => 'Vider /site/tmp'
    ]);

    if ($lastId !== '') {
        echo template::button('siteauditActionsLast', [
            'class' => 'buttonGrey siteauditBtn',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $lastId,
            'value' => template::ico('gear') . ' Centre d’actions',
            'help'  => 'Centre d’actions'
        ]);

        echo template::button('siteauditTrashLast', [
            'class' => 'buttonGrey siteauditBtn',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $lastId . '/1',
            'value' => template::ico('trash') . ' Corbeille',
            'help'  => 'Corbeille'
        ]);
    }
}

echo '</div>';

// Dernier rapport
if ($lastId !== '') {
    echo '<hr>';
    echo '<h2>Dernier rapport</h2>';
    echo '<p><strong>ID :</strong> ' . htmlspecialchars($lastId) . '<br>';
    echo '<strong>Date :</strong> ' . htmlspecialchars(siteaudit::formatDateDisplay((string)($last['createdAt'] ?? ''))) . '</p>';

    $s = is_array($last['summary'] ?? null) ? $last['summary'] : [];
    echo '<div class="siteauditCards">';
    foreach ([
        'pages' => 'Pages',
        'orphans' => 'Orphelines',
        'brokenPageLinks' => 'Liens internes cassés',
        'brokenFileLinks' => 'Liens fichiers manquants',
        'mediaUnused' => 'Médias inutilisés',
        'heavyFiles' => 'Fichiers lourds',
        'spacesTotal' => 'Espaces',
        'brokenSpaceLinks' => 'Liens espaces cassés',
        'totalEnrolments' => 'Inscriptions'
    ] as $k => $label) {
        $v = isset($s[$k]) ? (int)$s[$k] : 0;
        echo '<div class="siteauditCard"><div class="siteauditCardLabel">' . htmlspecialchars($label) . '</div><div class="siteauditCardValue">' . $v . '</div></div>';
    }
    echo '</div>';

    echo template::button('siteauditOpenLast', [
        'class' => 'buttonGrey',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/report/' . $lastId,
        'value' => template::ico('eye') . ' Ouvrir le rapport',
        'help'  => 'Ouvrir le rapport'
    ]);
}

// Historique récent
if (!empty(siteaudit::$reportsList)) {
    echo '<hr>';
    echo '<h2>Historique récent</h2>';

    $rows = [];
    foreach (siteaudit::$reportsList as $r) {
        $rid = (string)($r['id'] ?? '');
        $rows[] = [
            htmlspecialchars(siteaudit::formatDateDisplay((string)($r['created_at'] ?? ''))),
            htmlspecialchars($rid),
            '<div class="siteauditRowBtns">' .
            template::button('siteauditOpen' . $rid, [
                'class' => 'buttonGrey',
                'href' => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
                'value' => template::ico('eye'),
                'help' => 'Ouvrir'
            ]) .
            '</div>'
        ];
    }

    echo template::table([3,6,3], $rows, ['Date', 'ID', '']);
}
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
.siteauditCards{display:grid;grid-template-columns:repeat(9,minmax(0,1fr));gap:10px;margin:15px 0}
.siteauditCard{background:rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:10px}
.siteauditCardLabel{font-size:.85em;opacity:.75}
.siteauditCardValue{font-size:1.4em;font-weight:700;margin-top:4px}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end}
@media (max-width: 980px){.siteauditCards{grid-template-columns:repeat(3,minmax(0,1fr));}}
@media (max-width: 560px){.siteauditCards{grid-template-columns:repeat(2,minmax(0,1fr));}.siteauditToolbar a{flex:1 1 140px}}
</style>

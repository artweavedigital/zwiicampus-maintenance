<?php
$pager = siteaudit::$trashPager;
$rid = (string)($pager['rid'] ?? '');
$page = (int)($pager['page'] ?? 1);
$pages = (int)($pager['pages'] ?? 1);
$total = (int)($pager['total'] ?? 0);
$perPage = (int)($pager['perPage'] ?? 50);

echo '<div class="siteauditToolbar">';

echo template::button('siteauditBackToReport', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
    'value' => template::ico('left') . ' Retour au rapport'
]);

echo template::button('siteauditActions', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $rid,
    'value' => template::ico('gear') . ' Centre d’actions'
]);

echo template::button('siteauditMedia', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $page,
    'value' => template::ico('list') . ' Médias'
]);

echo '</div>';

echo '<hr>';

echo '<h2>Corbeille — quarantaine</h2>';
echo '<p class="help">Ici, tu peux <strong>restaurer</strong> ou <strong>supprimer définitivement</strong> les fichiers déjà déplacés en quarantaine (après rétention).</p>';

echo '<p><strong>Rapport :</strong> ' . htmlspecialchars($rid) . '<br>';
echo '<strong>Total :</strong> ' . $total . ' — <strong>Page :</strong> ' . $page . '/' . $pages . ' — <strong>Par page :</strong> ' . $perPage . '</p>';

if ($pages > 1) {
    echo '<div class="siteauditPager">';
    if ($page > 1) {
        echo template::button('siteauditPrev', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . ($page - 1),
            'value' => template::ico('left') . ' Précédent'
        ]);
    }
    if ($page < $pages) {
        echo template::button('siteauditNext', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . ($page + 1),
            'value' => template::ico('right') . ' Suivant'
        ]);
    }
    echo '</div>';
}

if (empty(siteaudit::$trash)) {
    echo '<p><em>Corbeille vide pour ce rapport.</em></p>';
    return;
}

$rows = [];
foreach (siteaudit::$trash as $it) {
    $idx = (int)($it['idx'] ?? 0);
    $rel = htmlspecialchars($it['rel'] ?? '');
    $size = isset($it['size']) ? siteaudit::formatBytes((int)$it['size']) : '';
    $qAt = siteaudit::formatDateDisplay((string)($it['quarantined_at'] ?? ''));
    $exists = !empty($it['exists']);
    $canDelete = !empty($it['can_delete']);

    $state = $exists ? '<span class="siteauditBadgeOk">OK</span>' : '<span class="siteauditBadgeDanger">Manquant</span>';

    $actions = '<div class="siteauditRowBtns">';
    $actions .= template::button('siteauditRestore' . $idx, [
        'class' => 'buttonGrey',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/restoremedia/' . $rid . '/' . $idx . '/' . $page,
        'value' => template::ico('left') . ' Restaurer',
        'help'  => 'Restaurer'
    ]);

    if ($canDelete) {
        $actions .= template::button('siteauditPurge' . $idx, [
            'class' => 'buttonRed',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/purgetrash/' . $rid . '/' . $idx . '/' . $page,
            'value' => template::ico('trash') . ' Supprimer',
            'help'  => 'Supprimer définitivement'
        ]);
    } else {
        $actions .= '<span class="siteauditBadgeGrey">Rétention</span>';
    }
    $actions .= '</div>';

    $rows[] = [$rel, htmlspecialchars($size), htmlspecialchars($qAt), $state, $actions];
}

echo template::table([6,2,2,1,1], $rows, ['Chemin', 'Taille', 'Quarantaine', 'État', 'Actions']);
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditPager{display:flex;gap:10px;margin:10px 0}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.siteauditBadgeOk{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(0,0,0,.03);font-size:.85em}
.siteauditBadgeDanger{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(255,0,0,.08);font-size:.85em}
.siteauditBadgeGrey{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.02);font-size:.85em}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

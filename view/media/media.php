<?php
$pager = siteaudit::$mediaPager;
$rid = (string)($pager['rid'] ?? '');
$page = (int)($pager['page'] ?? 1);
$pages = (int)($pager['pages'] ?? 1);
$total = (int)($pager['total'] ?? 0);
$perPage = (int)($pager['perPage'] ?? 50);
$offset = (int)($pager['offset'] ?? 0);
$todoCount = (int)($pager['todoCount'] ?? 0);
$todoBytes = (int)($pager['todoBytes'] ?? 0);

$siteauditQuickRid = $rid;
require __DIR__ . "/../_partial/quickhelp.php";

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

echo template::button('siteauditTrash', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/1',
    'value' => template::ico('trash') . ' Corbeille'
]);

echo '</div>';

echo '<hr>';

echo '<h2>Médias non utilisés</h2>';

echo '<p><strong>Rapport :</strong> ' . htmlspecialchars($rid) . '<br>';
echo '<strong>Total détecté :</strong> ' . $total . ' — <strong>Page :</strong> ' . $page . '/' . $pages . ' — <strong>Par page :</strong> ' . $perPage . '</p>';

echo '<div class="siteauditCards">';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">À traiter (encore)</div><div class="siteauditCardValue">' . $todoCount . '</div></div>';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">Économies estimées</div><div class="siteauditCardValue">' . htmlspecialchars(siteaudit::formatBytes($todoBytes)) . '</div></div>';
echo '</div>';

if ($pages > 1) {
    echo '<div class="siteauditPager">';
    if ($page > 1) {
        echo template::button('siteauditPrev', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . ($page - 1),
            'value' => template::ico('left') . ' Précédent',
            'help'  => 'Page précédente'
        ]);
    }
    if ($page < $pages) {
        echo template::button('siteauditNext', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . ($page + 1),
            'value' => template::ico('right') . ' Suivant',
            'help'  => 'Page suivante'
        ]);
    }
    echo '</div>';
}

if (empty(siteaudit::$media)) {
    echo '<p><em>Rien à afficher.</em></p>';
    return;
}

echo '<h3>Quarantaine par sélection</h3>';
echo '<p class="help">Coche, puis <strong>Simuler</strong> : tu obtiens un plan (dry-run) avant exécution, avec lots et rétention.</p>';

echo template::formOpen('siteauditMediaSelectForm');

echo '<div class="siteauditInlineActions">';
echo '<label class="siteauditSelectAll"><input type="checkbox" id="siteauditSelectAll" /> Tout sélectionner (page)</label>';
echo template::submit('siteauditSimulate', ['value' => 'Simuler la quarantaine']);
echo '</div>';

$rows = [];
foreach (siteaudit::$media as $i => $it) {
    $abs = $offset + $i;
    $rel = (string)($it['rel'] ?? '');
    $ext = (string)($it['ext'] ?? '');
    $size = isset($it['size']) ? siteaudit::formatBytes((int)$it['size']) : '';

    $isDeleted = !empty($it['deleted_at']);
    $isQuarantined = !empty($it['quarantine']) || !empty($it['quarantined_at']);

    $select = '';
    if (!$isDeleted && !$isQuarantined) {
        $select = '<input class="siteauditChk" type="checkbox" name="siteauditSelect[]" value="' . (int)$abs . '">';
    }

    $state = '';
    if ($isDeleted) {
        $state = '<span class="siteauditBadgeDanger">Supprimé</span>';
    } elseif ($isQuarantined) {
        $state = '<span class="siteauditBadgeOk">Quarantaine</span>';
    } else {
        $state = '<span class="siteauditBadgeGrey">À traiter</span>';
    }

    $actions = '<div class="siteauditRowBtns">';
    if (!$isDeleted && !$isQuarantined) {
        $actions .= template::button('siteauditQ' . $abs, [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/quarantine/' . $rid . '/' . $abs . '/' . $page,
            'value' => template::ico('check') . ' Quarantaine',
            'help'  => 'Mettre en quarantaine (réversible)'
        ]);
    } elseif ($isQuarantined) {
        $actions .= template::button('siteauditGoTrash' . $abs, [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . $page,
            'value' => template::ico('trash') . ' Corbeille',
            'help'  => 'Gérer la quarantaine'
        ]);
    }
    $actions .= '</div>';

    $rows[] = [
        $select,
        htmlspecialchars($rel),
        htmlspecialchars($ext),
        htmlspecialchars($size),
        $state,
        $actions
    ];
}

echo template::table([1,6,1,2,1,1], $rows, ['','Chemin', 'Ext', 'Taille', 'État', 'Actions']);

echo template::formClose();
?>
<script>
(function(){
  const all = document.getElementById('siteauditSelectAll');
  if(!all) return;
  all.addEventListener('change', function(){
    document.querySelectorAll('.siteauditChk').forEach(chk => { chk.checked = all.checked; });
  });
})();
</script>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditCards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:12px 0 14px}
.siteauditCard{border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:10px}
.siteauditCardLabel{font-size:.85em;opacity:.75}
.siteauditCardValue{font-size:1.2em;font-weight:700;margin-top:4px}
.siteauditPager{display:flex;gap:10px;margin:10px 0}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.siteauditInlineActions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:8px 0 12px}
.siteauditSelectAll{display:flex;gap:8px;align-items:center}
.siteauditBadgeOk{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(0,0,0,.03);font-size:.85em}
.siteauditBadgeDanger{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(255,0,0,.08);font-size:.85em}
.siteauditBadgeGrey{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.02);font-size:.85em}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

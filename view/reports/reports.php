<?php
echo '<div class="siteauditToolbar">';

echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0),
    'value' => template::ico('left') . ' Retour'
]);

echo template::button('siteauditRun', [
    'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Lancer un audit',
    'help'  => 'Lancer un audit'
]);

echo '</div>';

echo '<hr><h2>Rapports</h2>';

if (empty(siteaudit::$reportsList)) {
    echo '<p>Aucun rapport pour l’instant.</p>';
    return;
}

$rows = [];
foreach (siteaudit::$reportsList as $r) {
    $id = (string)($r['id'] ?? '');
    $rows[] = [
        htmlspecialchars(siteaudit::formatDateDisplay((string)($r['created_at'] ?? ''))),
        htmlspecialchars($id),
        '<div class="siteauditRowBtns">' .
        template::button('siteauditOpen' . $id, [
            'class' => 'buttonGrey',
            'href' => helper::baseUrl() . $this->getUrl(0) . '/report/' . $id,
            'value' => template::ico('eye'),
            'help' => 'Ouvrir'
        ]) .
        template::button('siteauditExport' . $id, [
            'class' => 'buttonGrey',
            'href' => helper::baseUrl() . $this->getUrl(0) . '/exportmd/' . $id,
            'value' => template::ico('download'),
            'help' => 'Exporter en Markdown'
        ]) .
        template::button('siteauditDel' . $id, [
            'class' => 'buttonRed',
            'href' => helper::baseUrl() . $this->getUrl(0) . '/deleteReport/' . $id,
            'value' => template::ico('trash'),
            'help' => 'Supprimer'
        ]) .
        '</div>'
    ];
}

echo template::table([3,5,4], $rows, ['Date', 'ID', 'Actions']);
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

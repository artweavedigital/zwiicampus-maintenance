<?php
$plan = siteaudit::$plan;
$token = (string)($plan['token'] ?? '');
$rid = (string)($plan['reportId'] ?? '');
$total = (int)($plan['total'] ?? 0);
$done = (int)($plan['done'] ?? 0);
$bytes = (int)($plan['bytes'] ?? 0);
$cursor = (int)($plan['cursor'] ?? 0);
$maxBatch = (int)($plan['maxBatch'] ?? 25);
$retention = (int)($plan['trashRetentionDays'] ?? 14);

$returnPage = (int)($plan['return']['page'] ?? 1);
$isDone = !empty($plan['completed_at']) || ($done >= $total && $total > 0);

echo '<div class="siteauditToolbar">';

echo template::button('siteauditBackMedia', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
    'value' => template::ico('left') . ' Retour aux médias'
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

echo '<h2>Simulation — quarantaine</h2>';
echo '<p class="help">Aucune modification n’est faite tant que tu n’appliques pas les lots. Chaque lot est journalisé (undo possible tant que la page n’a pas été modifiée depuis).</p>';

echo '<p><strong>Rapport :</strong> ' . htmlspecialchars($rid) . '<br>';
echo '<strong>Créé :</strong> ' . htmlspecialchars(siteaudit::formatDateDisplay((string)($plan['created_at'] ?? ''))) . '<br>';
echo '<strong>Sélection :</strong> ' . $total . ' fichier(s) — <strong>Taille :</strong> ' . htmlspecialchars(siteaudit::formatBytes($bytes)) . '<br>';
echo '<strong>Lots :</strong> ' . $maxBatch . ' / clic — <strong>Rétention corbeille :</strong> ' . $retention . ' jour(s)</p>';

$progress = ($total > 0) ? (int)round(($done / $total) * 100) : 0;
echo '<div class="siteauditProgress"><div class="siteauditProgressBar" style="width:' . $progress . '%"></div></div>';
echo '<p><strong>Progression :</strong> ' . $done . ' / ' . $total . ' (' . $progress . '%)</p>';

if (!$isDone) {
    echo template::button('siteauditApplyPlan', [
        'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/applyplan/' . $token,
        'value' => template::ico('check') . ' Appliquer le lot suivant',
        'help'  => 'Exécuter un lot'
    ]);
} else {
    echo '<p><span class="siteauditBadgeOk">Plan terminé</span></p>';
}

$items = (array)($plan['items'] ?? []);
$preview = array_slice($items, 0, 120);
if (!empty($preview)) {
    echo '<h3>Plan (aperçu)</h3>';
    echo '<p class="help">Aperçu limité à 120 entrées. La liste complète est stockée dans le plan.</p>';
    $rows = [];
    foreach ($preview as $it) {
        $rows[] = [
            (int)($it['idx'] ?? 0),
            htmlspecialchars((string)($it['rel'] ?? '')),
            htmlspecialchars(siteaudit::formatBytes((int)($it['size'] ?? 0)))
        ];
    }
    echo template::table([1,8,3], $rows, ['#', 'Fichier', 'Taille']);
}
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
.siteauditProgress{height:10px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.03);overflow:hidden;margin:10px 0}
.siteauditProgressBar{height:100%;background:rgba(0,0,0,.22)}
.siteauditBadgeOk{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.15);background:rgba(0,0,0,.03);font-size:.85em}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

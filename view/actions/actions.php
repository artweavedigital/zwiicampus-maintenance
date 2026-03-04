<?php
$sum = siteaudit::$actionsSummary;
$rid = (string)($sum['rid'] ?? '');
$fixCursor = (int)($sum['fixCursor'] ?? 0);
$mediaCursor = (int)($sum['mediaCursor'] ?? 0);
$maxBatch = (int)($sum['maxBatch'] ?? 25);

echo '<div class="siteauditToolbar">';

echo template::button('siteauditBackToReport', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
    'value' => template::ico('left') . ' Retour au rapport'
]);

echo template::button('siteauditReports', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/reports',
    'value' => template::ico('list') . ' Rapports'
]);

echo template::button('siteauditTrash', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/1',
    'value' => template::ico('trash') . ' Corbeille'
]);

echo '</div>';

echo '<hr>';

echo '<h2>Centre d’actions</h2>';

if ($rid === '') {
    echo '<p><em>Aucun rapport sélectionné.</em></p>';
    return;
}

$brokenFixable = (int)($sum['broken_fixable'] ?? 0);
$brokenAmb = (int)($sum['broken_ambiguous'] ?? 0);
$unusedTodo = (int)($sum['unused_todo'] ?? 0);
$unusedBytes = (int)($sum['unused_bytes'] ?? 0);
$retention = (int)($sum['trashRetentionDays'] ?? 14);

echo '<div class="siteauditCards">';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">Liens “sûrs” réparables</div><div class="siteauditCardValue">' . $brokenFixable . '</div><div class="siteauditCardHint">Suggestion unique</div></div>';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">Liens ambigus</div><div class="siteauditCardValue">' . $brokenAmb . '</div><div class="siteauditCardHint">Choix requis</div></div>';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">Médias à mettre en quarantaine</div><div class="siteauditCardValue">' . $unusedTodo . '</div><div class="siteauditCardHint">Réversible</div></div>';
echo '<div class="siteauditCard"><div class="siteauditCardLabel">Économies estimées</div><div class="siteauditCardValue">' . htmlspecialchars(siteaudit::formatBytes($unusedBytes)) . '</div><div class="siteauditCardHint">Sur ce rapport</div></div>';
echo '</div>';

echo '<h3>Actions “safe” (par lots)</h3>';
echo '<p class="help">Pensé pour les gros sites : chaque clic traite un lot (max. ' . $maxBatch . '). Chaque lot est journalisé et peut être annulé.</p>';

echo '<div class="siteauditActionsBar">';
if ($this->getUser('role') >= siteaudit::ROLE_ADMIN) {
    echo template::button('siteauditFixSafe', [
        'class' => 'buttonGreen siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/fixfilesafe/' . $rid . '/' . $fixCursor . '/' . $mediaCursor,
        'value' => template::ico('check') . ' Réparer (lot)',
        'help'  => 'Réparer les liens sûrs (lot)'
    ]);

    echo template::button('siteauditQuarantineSafe', [
        'class' => 'buttonGrey siteauditBtn',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/quarantinesafe/' . $rid . '/' . $mediaCursor . '/' . $fixCursor,
        'value' => template::ico('check') . ' Quarantaine (lot)',
        'help'  => 'Mettre en quarantaine (lot)'
    ]);
} else {
    echo '<p><em>Connecte-toi en administrateur pour exécuter des actions.</em></p>';
}
echo '</div>';

echo '<p class="help">Suppression définitive : uniquement depuis la corbeille (rétention : ' . $retention . ' jour(s)).</p>';

echo '<h3>Historique des actions</h3>';

$actions = siteaudit::$actionsList ?? [];
if (empty($actions)) {
    echo '<p><em>Aucune action enregistrée.</em></p>';
} else {
    $rows = [];
    foreach ($actions as $a) {
        $id = htmlspecialchars($a['id'] ?? '');
        $type = htmlspecialchars($a['type'] ?? '');
        $dt = htmlspecialchars(siteaudit::formatDateDisplay((string)($a['created_at'] ?? '')));
        $undone = !empty($a['undone_at']);
        $badge = $undone ? '<span class="siteauditBadgeGrey">Annulée</span>' : '';
        $sumTxt = '';
        $s = $a['summary'] ?? [];
        if (is_array($s)) {
            if (isset($s['links'])) $sumTxt .= 'Liens : ' . (int)$s['links'] . ' ';
            if (isset($s['replacements'])) $sumTxt .= 'Rempl. : ' . (int)$s['replacements'] . ' ';
            if (isset($s['files'])) $sumTxt .= 'Fichiers : ' . (int)$s['files'] . ' ';
            if (isset($s['bytes'])) $sumTxt .= 'Taille : ' . siteaudit::formatBytes((int)$s['bytes']);
        }
        $sumTxt = htmlspecialchars(trim($sumTxt));

        $btn = '<div class="siteauditRowBtns">' . $badge;
        if (!$undone && $this->getUser('role') >= siteaudit::ROLE_ADMIN && in_array(($a['type'] ?? ''), ['fix_links','quarantine_media'], true)) {
            $btn .= template::button('siteauditUndo' . $id, [
                'class' => 'buttonGrey',
                'href'  => helper::baseUrl() . $this->getUrl(0) . '/undo/' . ($a['id'] ?? ''),
                'value' => template::ico('left') . ' Annuler',
                'help'  => 'Annuler cette action'
            ]);
        }
        $btn .= '</div>';

        $rows[] = [$dt, $type, $sumTxt, $btn];
        if (count($rows) >= 20) break;
    }

    echo template::table([2,2,5,3], $rows, ['Date', 'Type', 'Résumé', '']);
}
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditCards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0 18px}
.siteauditCard{border:1px solid rgba(0,0,0,.12);border-radius:10px;padding:10px}
.siteauditCardLabel{font-size:.85em;opacity:.75}
.siteauditCardValue{font-size:1.2em;font-weight:700;margin-top:4px}
.siteauditCardHint{margin-top:6px;font-size:.85em;opacity:.75}
.siteauditActionsBar{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 18px}
.siteauditBadgeGrey{display:inline-block;margin-left:6px;padding:1px 7px;border-radius:999px;border:1px solid rgba(0,0,0,.12);background:rgba(0,0,0,.02);font-size:.85em}
.siteauditRowBtns{display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
@media (max-width: 980px){.siteauditCards{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

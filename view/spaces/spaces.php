<?php
$spaces = siteaudit::$spaces ?? [];

echo '<div class="siteauditToolbar">';
echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0),
    'value' => template::ico('left-open') . ' Retour',
]);
echo template::button('siteauditRun2', [
    'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Lancer un audit',
]);
echo '</div>';

if (empty($spaces)) {
    echo '<p>Aucun espace de formation trouvé.</p>';
    return;
}

$rows = [];
foreach ($spaces as $id => $s) {
    $rows[] = [
        htmlspecialchars((string)($s['title'] ?? $id)),
        htmlspecialchars((string)$id),
        htmlspecialchars((string)($s['author'] ?? '')),
        (int)($s['pageCount'] ?? 0),
        (int)($s['enrolmentCount'] ?? 0),
        htmlspecialchars(siteaudit::formatBytes((int)($s['contentSize'] ?? 0))),
        '<a class="buttonGrey" style="display:inline-flex;align-items:center;gap:6px" href="' .
            helper::baseUrl() . $id . '">' . template::ico('eye') . ' Ouvrir</a>'
    ];
}

echo '<h2>Espaces de formation</h2>';
echo '<p>Liste des cours détectés via <code>site/data/&lt;courseId&gt;/</code>.</p>';

echo template::table([
    '35%',
    '12%',
    '18%',
    '8%',
    '10%',
    '10%',
    '7%'
], $rows, ['Titre', 'ID', 'Auteur', 'Pages', 'Inscrits', 'Contenu', '']);
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
</style>

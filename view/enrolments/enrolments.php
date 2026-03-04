<?php
$enrolments = siteaudit::$enrolments ?? [];

echo '<div class="siteauditToolbar">';
echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0),
    'value' => template::ico('left-open') . ' Retour',
]);
echo template::button('siteauditSpaces', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/spaces',
    'value' => template::ico('graduation-cap') . ' Espaces',
]);
echo '</div>';

if (empty($enrolments)) {
    echo '<p>Aucune inscription trouvée.</p>';
    return;
}

// Pagination simple via URL : /enrolments/<page>
$page = max(1, (int)($this->getUrl(2) ?: 1));
$perPage = 50;
$total = count($enrolments);
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$slice = array_slice($enrolments, $offset, $perPage);

$rows = [];
foreach ($slice as $e) {
    $rows[] = [
        htmlspecialchars((string)($e['courseTitle'] ?? '')),
        htmlspecialchars((string)($e['userName'] ?? '')),
        htmlspecialchars((string)($e['userEmail'] ?? '')),
        htmlspecialchars((string)($e['group'] ?? '')),
        (int)($e['progress'] ?? 0) . '%',
        htmlspecialchars((string)($e['lastPageView'] ?? '')),
    ];
}

echo '<h2>Inscriptions</h2>';
echo '<p><strong>' . $total . '</strong> inscription(s) — page <strong>' . $page . '</strong>/' . $pages . '</p>';

echo template::table([
    '24%',
    '18%',
    '22%',
    '10%',
    '8%',
    '18%'
], $rows, ['Espace', 'Apprenant', 'Email', 'Groupe', 'Progression', 'Dernière page']);

// Navigation
$nav = '<div class="siteauditPager">';
if ($page > 1) {
    $nav .= '<a class="buttonGrey" href="' . helper::baseUrl() . $this->getUrl(0) . '/enrolments/' . ($page - 1) . '">' . template::ico('left-open') . ' Précédent</a>';
}
if ($page < $pages) {
    $nav .= '<a class="buttonGrey" href="' . helper::baseUrl() . $this->getUrl(0) . '/enrolments/' . ($page + 1) . '">Suivant ' . template::ico('right-open') . '</a>';
}
$nav .= '</div>';

echo $nav;
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditPager{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
</style>

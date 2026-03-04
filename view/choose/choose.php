<?php
$ctx = siteaudit::$chooseFix;
$rid = (string)($ctx['rid'] ?? '');
$idx = (int)($ctx['idx'] ?? 0);

echo '<div class="siteauditToolbar">';
echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
    'value' => template::ico('left') . ' Retour au rapport'
]);
echo '</div>';

echo '<hr>';

echo '<h2>Choisir la correction</h2>';
echo '<p class="help">Plusieurs fichiers correspondent. Sélectionne le bon emplacement, puis valide. La correction est réversible via l’historique des actions (si la page n’a pas été modifiée depuis).</p>';

echo '<p><strong>Page :</strong> ' . htmlspecialchars((string)($ctx['from'] ?? '')) . '<br>';
echo '<strong>Lien :</strong> ' . htmlspecialchars((string)($ctx['url'] ?? '')) . '</p>';

$options = $ctx['options'] ?? [];
if (empty($options)) {
    echo '<p><em>Aucune option.</em></p>';
    return;
}

echo template::formOpen('siteauditChooseFixForm');

echo '<div class="row"><div class="col12">';
echo '<label for="siteauditChoiceRel"><strong>Fichier trouvé</strong></label><br>';
echo '<select name="siteauditChoiceRel" id="siteauditChoiceRel" style="width:100%;padding:10px;border-radius:10px;border:1px solid rgba(0,0,0,.15)">';
foreach ($options as $o) {
    $rel = (string)($o['rel'] ?? '');
    $size = isset($o['size']) ? (' — ' . siteaudit::formatBytes((int)$o['size'])) : '';
    echo '<option value="' . htmlspecialchars($rel) . '">' . htmlspecialchars($rel . $size) . '</option>';
}
echo '</select>';
echo '</div></div>';

echo '<div class="row"><div class="col12">';
echo template::submit('siteauditChooseSubmit', ['value' => 'Appliquer la correction']);
echo '</div></div>';

echo template::formClose();
?>
<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 220px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

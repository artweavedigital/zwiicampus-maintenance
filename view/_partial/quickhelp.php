<?php
/**
 * Aide rapide — affichage “débutant” directement dans les vues.
 *
 * Variables optionnelles :
 * - $siteauditQuickRid : ID du rapport courant (string)
 */

$rid = isset($siteauditQuickRid) ? (string)$siteauditQuickRid : '';

echo '<details class="siteauditQuickHelp">';
echo '<summary>Aide rapide — bien démarrer</summary>';

echo '<div class="siteauditHelpWrap">';

echo '<div class="siteauditHelpCol">';
echo '<ol class="siteauditHelpSteps">';
echo '<li><strong>Lancer un audit</strong>, puis ouvrir le rapport.</li>';
echo '<li><strong>Liens cassés</strong> : utilise <em>Réparer les liens sûrs</em> (correction automatique seulement si la correspondance est unique). Les cas ambigus se traitent via <em>Choisir</em>.</li>';
echo '<li><strong>Médias non utilisés</strong> : va dans <em>Médias</em>, coche, puis <strong>Simuler</strong> — tu obtiens un <em>plan</em> (dry-run) avant exécution, avec lots et économies estimées.</li>';
echo '<li><strong>Annuler / Undo</strong> : Centre d’actions → Historique → Annuler (quarantaine et corrections de liens).</li>';
echo '</ol>';
echo '<p class="siteauditHelpNote"><strong>Conseil “gros site” :</strong> travaille par lots (10 à 25) et privilégie la quarantaine (réversible) plutôt que la suppression directe.</p>';
echo '</div>';

echo '<div class="siteauditHelpCol">';
echo '<div class="siteauditHelpActions">';

echo template::button('siteauditQuickRun', [
    'class' => 'buttonGreen',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Lancer un audit',
    'help'  => 'Lancer un audit'
]);

if ($rid !== '') {
    echo template::button('siteauditQuickReport', [
        'class' => 'buttonGrey',
        'href'  => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
        'value' => template::ico('eye') . ' Ouvrir le rapport',
        'help'  => 'Ouvrir le rapport'
    ]);

    if ($this->getUser('role') >= siteaudit::ROLE_ADMIN) {
        echo template::button('siteauditQuickActions', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $rid,
            'value' => template::ico('gear') . ' Centre d’actions',
            'help'  => 'Centre d’actions'
        ]);

        echo template::button('siteauditQuickMedia', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/1',
            'value' => template::ico('list') . ' Médias',
            'help'  => 'Médias non utilisés'
        ]);

        echo template::button('siteauditQuickTrash', [
            'class' => 'buttonGrey',
            'href'  => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/1',
            'value' => template::ico('trash') . ' Corbeille',
            'help'  => 'Corbeille / rétention'
        ]);
    }
}

echo '</div>';
echo '</div>';

echo '</div>';
echo '</details>';
?>

<style>
.siteauditQuickHelp{background:rgba(0,0,0,.02);border:1px solid rgba(0,0,0,.10);border-radius:12px;padding:10px 12px;margin:0 0 12px}
.siteauditQuickHelp summary{cursor:pointer;font-weight:700;user-select:none}
.siteauditHelpWrap{display:grid;grid-template-columns:1.3fr .9fr;gap:14px;margin-top:10px}
.siteauditHelpSteps{margin:0;padding-left:18px}
.siteauditHelpSteps li{margin:0 0 8px}
.siteauditHelpNote{margin:10px 0 0;opacity:.85}
.siteauditHelpActions{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch}
.siteauditHelpActions a{flex:1 1 190px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
@media (max-width: 980px){.siteauditHelpWrap{grid-template-columns:1fr}}
</style>

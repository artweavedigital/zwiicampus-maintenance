<?php
echo '<div class="siteauditToolbar">';

echo template::button('siteauditBack', [
    'class' => 'buttonGrey siteauditBtn',
    'href'  => helper::baseUrl() . $this->getUrl(0),
    'value' => template::ico('left') . ' Retour'
]);

echo template::button('siteauditRunFromConfig', [
    'class' => 'buttonGreen siteauditBtn siteauditBtnPrimary',
    'href'  => helper::baseUrl() . $this->getUrl(0) . '/run',
    'value' => template::ico('check') . ' Lancer un audit',
    'help'  => 'Lancer un audit'
]);

echo '</div>';

$cfg = $this->getData(['module', $this->getUrl(0), 'config']);
$cfg = is_array($cfg) ? $cfg : [];
?>
<hr>
<?php echo template::formOpen('siteauditConfigForm'); ?>
<div class="row">
    <div class="col6">
        <?php echo template::text('siteauditMaxFileMb', [
            'label' => 'Seuil “fichier lourd” (Mo)',
            'value' => $cfg['maxFileMb'] ?? 2,
            'type'  => 'number',
            'required' => true
        ]); ?>
    </div>
    <div class="col6">
        <?php echo template::text('siteauditMaxItems', [
            'label' => 'Limite d’éléments affichés par contrôle',
            'value' => $cfg['maxItems'] ?? 200,
            'type'  => 'number',
            'required' => true
        ]); ?>
    </div>
</div>

<div class="row">
    <div class="col6">
        <?php echo template::checkbox('siteauditScanOrphans', true, 'Détecter les pages orphelines', [
            'checked' => (!empty($cfg['scanOrphans']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanLinks', true, 'Détecter les liens internes cassés', [
            'checked' => (!empty($cfg['scanLinks']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanFileLinks', true, 'Détecter les liens vers fichiers manquants', [
            'checked' => (!empty($cfg['scanFileLinks']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanUnusedMedia', true, 'Détecter les médias non utilisés', [
            'checked' => (!empty($cfg['scanUnusedMedia']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanThemeFiles', true, 'Inclure le thème dans la recherche d’usage des médias', [
            'checked' => (!empty($cfg['scanThemeFiles']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanModuleFiles', true, 'Inclure les modules dans la recherche d’usage (plus lent)', [
            'checked' => (!empty($cfg['scanModuleFiles']) ? 'checked' : '')
        ]); ?>
        

        <hr>
        <p><strong>ZwiiCampus</strong> — analyse des espaces de formation :</p>
        <?php echo template::checkbox('siteauditScanSpaces', true, 'Analyser les espaces de formation (cours)', [
            'checked' => (!empty($cfg['scanSpaces']) ? 'checked' : '')
        ]); ?>
        <?php echo template::checkbox('siteauditScanEnrolments', true, 'Inclure les inscriptions (statistiques)', [
            'checked' => (!empty($cfg['scanEnrolments']) ? 'checked' : '')
        ]); ?>

<?php echo template::checkbox('siteauditStoreFullUnusedMedia', true, 'Conserver la liste complète des médias non utilisés (pour suppression/quarantaine)', [
            'checked' => (!empty($cfg['storeFullUnusedMedia']) ? 'checked' : '')
        ]); ?>
    </div>
    <div class="col6">
        <?php echo template::textarea('siteauditIgnoreDirs', [
            'label' => 'Dossiers à ignorer (CSV)',
            'value' => $cfg['ignoreDirs'] ?? 'thumb,tmp,cache,backup,.git',
            'help'  => 'Ex. : thumb,tmp,cache — ces noms de dossier seront ignorés lors du scan de /site/file/source/'
        ]); ?>
        <?php echo template::text('siteauditQuarantineFolder', [
            'label' => 'Dossier d’export (dans /site/file/source/)',
            'value' => $cfg['quarantineFolder'] ?? '_siteaudit',
            'help'  => 'Les exports Markdown y seront créés.'
        ]); ?>
    </div>
</div>


<div class="row">
    <div class="col4">
        <?php echo template::text('siteauditMaxBatch', [
            'label' => 'Taille des lots (actions)',
            'value' => $cfg['maxBatch'] ?? 25,
            'type'  => 'number',
            'help'  => 'Pour les gros sites : exécute les corrections en plusieurs lots.'
        ]); ?>
    </div>
    <div class="col4">
        <?php echo template::text('siteauditTrashRetentionDays', [
            'label' => 'Rétention corbeille (jours)',
            'value' => $cfg['trashRetentionDays'] ?? 14,
            'type'  => 'number',
            'help'  => 'Avant suppression définitive. 0 = suppression autorisée immédiatement depuis la corbeille.'
        ]); ?>
    </div>
    <div class="col4">
        <?php echo template::checkbox('siteauditAllowHardDelete', true, 'Autoriser la suppression directe', [
            'checked' => (!empty($cfg['allowHardDelete']) ? 'checked' : ''),
            'help' => 'Déconseillé sur un gros site : préfère Quarantaine → Corbeille.'
        ]); ?>
    </div>
</div>

<div class="row">
    <div class="col12">
        <?php echo template::checkbox('siteauditStrictUniqueFix', true, 'Réparation automatique strictement “unique”', [
            'checked' => (!empty($cfg['strictUniqueFix']) ? 'checked' : ''),
        ]); ?>
        <p class="help">Si plusieurs fichiers possibles sont trouvés, le module demandera un choix manuel (plus sûr).</p>
    </div>
</div>
<div class="row">
    <div class="col12">
        <?php echo template::submit('siteauditSave', ['value' => 'Enregistrer']); ?>
    </div>
</div>
<?php echo template::formClose(); ?>

<style>
.siteauditToolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch;margin:0 0 12px}
.siteauditToolbar a{flex:1 1 180px;display:flex;justify-content:center;align-items:center;gap:8px;text-align:center}
.siteauditToolbar a.siteauditBtnPrimary{flex:2 1 260px}
@media (max-width: 560px){.siteauditToolbar a{flex:1 1 140px}}
</style>

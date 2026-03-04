<?php
/**
 * SiteAudit — Audit & Maintenance (ZwiiCMS)
 * - Analyse des pages (orphelines, titres en doublon, hiérarchie)
 * - Détection de liens internes cassés
 * - Détection de liens vers fichiers manquants
 * - Détection de médias non utilisés + fichiers trop lourds
 *
 * Compatible PHP 8.1+ (testé en 8.3) — stockage flat-file.
 */
class siteaudit extends common {

    const VERSION = '2.0.1';
    const REALNAME = 'SiteAudit Campus';
    const DELETE = true;
    const UPDATE = '0.0';
    // Dossier dédié aux données volumineuses (rapports) : site/data/siteaudit/
    const DATADIRECTORY = self::DATA_DIR . 'siteaudit/';

    public static $actions = [

        'index'        => self::ROLE_EDITOR,
        'config'       => self::ROLE_EDITOR,
        'run'          => self::ROLE_EDITOR,
        'reports'      => self::ROLE_EDITOR,
        'report'       => self::ROLE_EDITOR,
        'exportmd'     => self::ROLE_EDITOR,
        'deleteReport' => self::ROLE_EDITOR,

        // Outils admin
        'cleartmp'     => self::ROLE_ADMIN,

        // Réparations & actions (admin uniquement)
        'fixfile'        => self::ROLE_ADMIN,
        'fixfileall'     => self::ROLE_ADMIN,
        'choosefix'      => self::ROLE_ADMIN,

        // Gestion médias (admin uniquement)
        'media'        => self::ROLE_ADMIN,
        'plan'         => self::ROLE_ADMIN,
        'applyplan'    => self::ROLE_ADMIN,
        'quarantine'   => self::ROLE_ADMIN,
        'deletemedia'  => self::ROLE_ADMIN,
        'trash'        => self::ROLE_ADMIN,
        'restoremedia' => self::ROLE_ADMIN,
        'purgetrash'   => self::ROLE_ADMIN,

        // Centre d’actions + lots + undo (admin uniquement)
        'actions'        => self::ROLE_ADMIN,
        'fixfilesafe'    => self::ROLE_ADMIN,
        'quarantinesafe' => self::ROLE_ADMIN,
        'undo'           => self::ROLE_ADMIN,

        // Campus
        'spaces'       => self::ROLE_EDITOR,
        'enrolments'   => self::ROLE_EDITOR,
    ];

    public static $report = [];
    public static $reportsList = [];
    public static $media = [];
    public static $mediaPager = [];

    // Pages / vues complémentaires
    public static $chooseFix = [];
    public static $actionsSummary = [];
    public static $actionsList = [];
    public static $trash = [];
    public static $trashPager = [];
    public static $plan = [];

    // Données Campus
    public static $spaces = [];
    public static $enrolments = [];

    

    /* =========================
     * Helpers d’affichage
     * ========================= */

    public static function formatDateDisplay(?string $iso, bool $withTime = true): string {
        if (!$iso) return '';
        $ts = strtotime($iso);
        if ($ts === false) return (string)$iso;
        return $withTime ? date('d-m-Y H:i', $ts) : date('d-m-Y', $ts);
    }

    public static function formatBytes(int $bytes): string {
        if ($bytes <= 0) return '0 Mo';
        $mb = $bytes / 1024 / 1024;
        if ($mb < 1) {
            $kb = $bytes / 1024;
            return round($kb, 1) . ' Ko';
        }
        return round($mb, 2) . ' Mo';
    }



    /**
     * Tableau de bord
     */
    public function index() {
        $this->initConfig();
        // Liste courte
        self::$reportsList = $this->listReports(12);

        $this->addOutput([
            'title' => helper::translate('SiteAudit Campus'),
            'view'  => 'index'
        ]);
    }

    /**
     * Vue — espaces de formation
     */
    public function spaces() {
        $this->initConfig();
        self::$spaces = $this->loadCampusSpaces();

        $this->addOutput([
            'title' => helper::translate('Espaces de formation'),
            'view'  => 'spaces'
        ]);
    }

    /**
     * Vue — inscriptions
     */
    public function enrolments() {
        $this->initConfig();
        self::$enrolments = $this->loadCampusEnrolments();

        $this->addOutput([
            'title' => helper::translate('Inscriptions'),
            'view'  => 'enrolments'
        ]);
    }


    /**
     * Configuration
     */
    public function config() {
        $this->initConfig();

        if ($this->getUser('permission', __CLASS__, __FUNCTION__) === true && $this->isPost()) {
            $config = [

                'maxFileMb'        => $this->getInput('siteauditMaxFileMb', helper::FILTER_INT, true),
                'maxItems'         => $this->getInput('siteauditMaxItems', helper::FILTER_INT, true),

                'scanOrphans'      => $this->getInput('siteauditScanOrphans', helper::FILTER_BOOLEAN),
                'scanLinks'        => $this->getInput('siteauditScanLinks', helper::FILTER_BOOLEAN),
                'scanFileLinks'    => $this->getInput('siteauditScanFileLinks', helper::FILTER_BOOLEAN),
                'scanUnusedMedia'  => $this->getInput('siteauditScanUnusedMedia', helper::FILTER_BOOLEAN),
                'scanThemeFiles'   => $this->getInput('siteauditScanThemeFiles', helper::FILTER_BOOLEAN),
                'scanModuleFiles'  => $this->getInput('siteauditScanModuleFiles', helper::FILTER_BOOLEAN),

                // Campus
                'scanSpaces'       => $this->getInput('siteauditScanSpaces', helper::FILTER_BOOLEAN),
                'scanEnrolments'   => $this->getInput('siteauditScanEnrolments', helper::FILTER_BOOLEAN),

                'storeFullUnusedMedia' => $this->getInput('siteauditStoreFullUnusedMedia', helper::FILTER_BOOLEAN),

                'ignoreDirs'       => $this->getInput('siteauditIgnoreDirs', helper::FILTER_STRING_LONG),
                'quarantineFolder' => $this->getInput('siteauditQuarantineFolder', helper::FILTER_STRING_SHORT, true),

                // Gros sites : lots, corbeille, sécurité
                'maxBatch'            => $this->getInput('siteauditMaxBatch', helper::FILTER_INT, true),
                'trashRetentionDays'  => $this->getInput('siteauditTrashRetentionDays', helper::FILTER_INT, true),
                'allowHardDelete'     => $this->getInput('siteauditAllowHardDelete', helper::FILTER_BOOLEAN),
                'strictUniqueFix'     => $this->getInput('siteauditStrictUniqueFix', helper::FILTER_BOOLEAN),
            ];
            $this->setData(['module', $this->getUrl(0), 'config', $config]);

            $this->addOutput([
                'redirect'      => helper::baseUrl() . $this->getUrl(0) . '/config',
                'notification'  => helper::translate('Modifications enregistrées'),
                'state'         => true
            ]);
        }

        $this->addOutput([
            'title' => helper::translate('Configuration du module'),
            'view'  => 'config'
        ]);
    }

    /**
     * Lance un audit et enregistre un rapport (JSON) dans site/data/siteaudit/reports/
     */
    public function run() {
        $this->initConfig();

        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $report = $this->buildReport();

        $this->ensureDirs();
        $id = $report['meta']['id'];
        $path = $this->reportPath($id);

        $this->writeJsonAtomic($path, $report);
        $this->indexReport($report);

        // Pointeur "dernier rapport" dans module.json (léger)
        $this->setData(['module', $this->getUrl(0), 'lastReport', [
            'id'        => $id,
            'createdAt' => $report['meta']['created_at'],
            'summary'   => $report['summary']
        ]]);

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/report/' . $id,
            'notification' => helper::translate('Audit terminé'),
            'state'        => true
        ]);
    }

    /**
     * Liste des rapports
     */
    public function reports() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        self::$reportsList = $this->listReports(200);

        $this->addOutput([
            'title' => helper::translate('Rapports'),
            'view'  => 'reports'
        ]);
    }

    /**
     * Affiche un rapport
     */
    public function report() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $id = $this->getUrl(2);
        if (!$id) {
            $last = $this->getData(['module', $this->getUrl(0), 'lastReport', 'id']);
            $id = $last ? $last : '';
        }

        $path = $this->reportPath($id);
        if (!$id || !is_file($path)) {
            self::$report = [];
        } else {
            self::$report = json_decode(file_get_contents($path), true) ?: [];
        }

        self::$reportsList = $this->listReports(20);

        $this->addOutput([
            'title' => helper::translate('Rapport d’audit'),
            'view'  => 'report'
        ]);
    }

    /**
     * Répare UN lien de fichier manquant (si une suggestion unique a été trouvée).
     * URL : /siteaudit/fixfile/<reportId>/<index>
     */
    public function fixfile() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $idx = (int)$this->getUrl(3);
        $path = $this->reportPath($rid);

        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $items = $report['checks']['brokenFileLinksAll'] ?? ($report['checks']['brokenFileLinks'] ?? []);
        $fixed = 0;
        $repl = 0;
        $actionId = $this->newId('a_');

        foreach ($items as $i => $it) {
            if (!empty($it['fixed'])) continue;
            $rel = (string)($it['suggested'] ?? '');
            if ($rel === '') continue;

            $oldUrl = (string)($it['url'] ?? '');
            $newUrl = $this->buildPublicFileUrl($oldUrl, $rel);

            $res = $this->replaceInAnyContent((string)($it['locale'] ?? 'default'), (string)($it['from'] ?? ''), (string)($it['fromSpace'] ?? ''), $oldUrl, $newUrl, $actionId);

            $items[$i]['fixed'] = (bool)($res['ok'] ?? false);
            $items[$i]['fixed_to'] = $newUrl;
            $items[$i]['fixed_at'] = date('c');
            $items[$i]['replacements'] = (int)($res['replacements'] ?? 0);

            if (($res['replacements'] ?? 0) > 0) {
                $fixed++;
                $repl += (int)$res['replacements'];
            }
        }

        if (isset($report['checks']['brokenFileLinksAll'])) {
            $report['checks']['brokenFileLinksAll'] = $items;
        } else {
            $report['checks']['brokenFileLinks'] = $items;
        }
        $this->writeJsonAtomic($path, $report);

        $this->logAction([
            'id' => $actionId,
            'type' => 'fix_links',
            'reportId' => $rid,
            'created_at' => date('c'),
            'summary' => ['links' => $fixed, 'replacements' => $repl],
        ]);

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
            'notification' => sprintf(helper::translate('Corrections appliquées : %s lien(s), %s remplacement(s)'), $fixed, $repl),
            'state'        => ($fixed > 0)
        ]);
    }


    /**
     * Choisir une correction (lien fichier manquant avec plusieurs options)
     * URL : /siteaudit/choosefix/<reportId>/<index>
     */
        /**
     * Alias : réparer tous les liens de fichiers corrigeables (bouton « Réparer tout »).
     */
    public function fixfileall() {
        $this->fixfile();
    }

public function choosefix() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $idx = (int)$this->getUrl(3);
        $path = $this->reportPath($rid);

        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $items = $report['checks']['brokenFileLinksAll'] ?? ($report['checks']['brokenFileLinks'] ?? []);
        if (!isset($items[$idx])) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
                'notification' => helper::translate('Entrée introuvable')
            ]);
            return;
        }

        $it = $items[$idx];
        $options = (array)($it['suggestions'] ?? []);
        if (empty($options)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
                'notification' => helper::translate('Aucune option disponible')
            ]);
            return;
        }

        if ($this->isPost()) {
            $rel = (string)$this->getInput('siteauditChoiceRel', helper::FILTER_STRING_LONG);
            if ($rel !== '') {
                $oldUrl = (string)($it['url'] ?? '');
                $newUrl = $this->buildPublicFileUrl($oldUrl, $rel);

                // Action log + backup
                $actionId = $this->newId('a_');
                $res = $this->replaceInAnyContent((string)($it['locale'] ?? 'default'), (string)($it['from'] ?? ''), (string)($it['fromSpace'] ?? ''), $oldUrl, $newUrl, $actionId);

                $report['checks']['brokenFileLinksAll'][$idx]['fixed'] = (bool)($res['ok'] ?? false);
                $report['checks']['brokenFileLinksAll'][$idx]['fixed_to'] = $newUrl;
                $report['checks']['brokenFileLinksAll'][$idx]['fixed_at'] = date('c');
                $report['checks']['brokenFileLinksAll'][$idx]['replacements'] = (int)($res['replacements'] ?? 0);

                // Si le rapport n’a pas brokenFileLinksAll (ancien), fallback
                if (!isset($report['checks']['brokenFileLinksAll'])) {
                    $report['checks']['brokenFileLinks'][$idx] = $report['checks']['brokenFileLinksAll'][$idx];
                }

                $this->writeJsonAtomic($path, $report);

                $this->logAction([
                    'id' => $actionId,
                    'type' => 'fix_links',
                    'reportId' => $rid,
                    'created_at' => date('c'),
                    'summary' => [
                        'links' => 1,
                        'replacements' => (int)($res['replacements'] ?? 0)
                    ],
                    'details' => [
                        'pageId' => (string)($it['from'] ?? ''),
                        'locale' => (string)($it['locale'] ?? 'default'),
                    ]
                ]);

                $msg = (($res['replacements'] ?? 0) > 0)
                    ? sprintf(helper::translate('Lien corrigé (%s remplacement(s))'), (int)$res['replacements'])
                    : helper::translate('Aucune occurrence trouvée — correction non appliquée');

                $this->addOutput([
                    'redirect' => helper::baseUrl() . $this->getUrl(0) . '/report/' . $rid,
                    'notification' => $msg,
                    'state' => (($res['replacements'] ?? 0) > 0)
                ]);
                return;
            }
        }

        self::$chooseFix = [
            'rid' => $rid,
            'idx' => $idx,
            'from' => $it['from'] ?? '',
            'url' => $it['url'] ?? '',
            'options' => $options
        ];

        $this->addOutput([
            'title' => helper::translate('Choisir une correction'),
            'view' => 'choose'
        ]);
    }

    /**
     * Centre d’actions (gros sites)
     * URL : /siteaudit/actions/<reportId>
     */
    public function actions() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        if ($rid === '') {
            $rid = (string)($this->getData(['module', $this->getUrl(0), 'lastReport', 'id']) ?? '');
        }

        $report = [];
        $path = $this->reportPath($rid);
        if ($rid !== '' && is_file($path)) {
            $report = json_decode(file_get_contents($path), true) ?: [];
        }

        $cfg = $this->getConfig();
        $maxBatch = max(5, (int)($cfg['maxBatch'] ?? 25));

        // Compteurs
        $brokenAll = $report['checks']['brokenFileLinksAll'] ?? ($report['checks']['brokenFileLinks'] ?? []);
        $brokenFixable = 0;
        $brokenAmb = 0;
        foreach ($brokenAll as $it) {
            if (!empty($it['fixed'])) continue;
            if (!empty($it['suggested'])) $brokenFixable++;
            elseif (!empty($it['suggestions'])) $brokenAmb++;
        }

        $unusedAll = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);
        $unusedTodo = 0;
        $unusedBytes = 0;
        foreach ($unusedAll as $it) {
            $isDeleted = !empty($it['deleted_at']);
            $isQ = !empty($it['quarantine']) || !empty($it['quarantined_at']);
            if ($isDeleted || $isQ) continue;
            $unusedTodo++;
            $unusedBytes += (int)($it['size'] ?? 0);
        }

        $fixCursor = (int)($this->getData(['module', $this->getUrl(0), 'cursors', $rid, 'fix']) ?? 0);
        $mediaCursor = (int)($this->getData(['module', $this->getUrl(0), 'cursors', $rid, 'media']) ?? 0);

        self::$actionsSummary = [
            'rid' => $rid,
            'maxBatch' => $maxBatch,
            'broken_fixable' => $brokenFixable,
            'broken_ambiguous' => $brokenAmb,
            'unused_todo' => $unusedTodo,
            'unused_bytes' => $unusedBytes,
            'fixCursor' => $fixCursor,
            'mediaCursor' => $mediaCursor,
            'trashRetentionDays' => (int)($cfg['trashRetentionDays'] ?? 14),
        ];

        self::$actionsList = $this->listActionsForReport($rid, 30);

        $this->addOutput([
            'title' => helper::translate('Centre d’actions'),
            'view'  => 'actions'
        ]);
    }

    /**
     * Réparer les liens “sûrs” (par lots).
     * URL : /siteaudit/fixfilesafe/<reportId>/<cursor>/<mediaCursor>
     */
    public function fixfilesafe() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $cursor = max(0, (int)($this->getUrl(3) ?? 0));

        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $cfg = $this->getConfig();
        $maxBatch = max(5, (int)($cfg['maxBatch'] ?? 25));

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['brokenFileLinksAll'] ?? ($report['checks']['brokenFileLinks'] ?? []);

        $fixedLinks = 0;
        $repl = 0;
        $actionId = $this->newId('a_');

        $i = $cursor;
        for (; $i < count($all) && $fixedLinks < $maxBatch; $i++) {
            $it = $all[$i];
            if (!empty($it['fixed'])) continue;
            $rel = (string)($it['suggested'] ?? '');
            if ($rel === '') continue;

            $oldUrl = (string)($it['url'] ?? '');
            $newUrl = $this->buildPublicFileUrl($oldUrl, $rel);

            $res = $this->replaceInAnyContent((string)($it['locale'] ?? 'default'), (string)($it['from'] ?? ''), (string)($it['fromSpace'] ?? ''), $oldUrl, $newUrl, $actionId);

            $all[$i]['fixed'] = (bool)($res['ok'] ?? false);
            $all[$i]['fixed_to'] = $newUrl;
            $all[$i]['fixed_at'] = date('c');
            $all[$i]['replacements'] = (int)($res['replacements'] ?? 0);

            if (($res['replacements'] ?? 0) > 0) {
                $fixedLinks++;
                $repl += (int)$res['replacements'];
            }
        }

        // Sauvegarde dans le rapport
        if (isset($report['checks']['brokenFileLinksAll'])) {
            $report['checks']['brokenFileLinksAll'] = $all;
        } else {
            $report['checks']['brokenFileLinks'] = $all;
        }
        $this->writeJsonAtomic($path, $report);

        // Cursor suivant
        $this->setData(['module', $this->getUrl(0), 'cursors', $rid, 'fix', $i]);

        // Log action
        $this->logAction([
            'id' => $actionId,
            'type' => 'fix_links',
            'reportId' => $rid,
            'created_at' => date('c'),
            'summary' => ['links' => $fixedLinks, 'replacements' => $repl],
        ]);

        $this->addOutput([
            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $rid,
            'notification' => sprintf(helper::translate('Lot terminé : %s lien(s), %s remplacement(s)'), $fixedLinks, $repl),
            'state' => ($fixedLinks > 0)
        ]);
    }

    /**
     * Mettre en quarantaine les médias “non utilisés” (par lots).
     * URL : /siteaudit/quarantinesafe/<reportId>/<cursor>/<fixCursor>
     */
    public function quarantinesafe() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $cursor = max(0, (int)($this->getUrl(3) ?? 0));

        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $cfg = $this->getConfig();
        $maxBatch = max(5, (int)($cfg['maxBatch'] ?? 25));

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

        $moved = 0;
        $bytes = 0;
        $actionId = $this->newId('a_');
        $details = [];

        $i = $cursor;
        for (; $i < count($all) && $moved < $maxBatch; $i++) {
            $it = $all[$i];
            if (!empty($it['deleted_at'])) continue;
            if (!empty($it['quarantine']) || !empty($it['quarantined_at'])) continue;

            $rel = (string)($it['rel'] ?? '');
            if ($rel === '') continue;

            $destRel = $this->moveSourceToQuarantineDetailed($rel);
            if ($destRel !== '') {
                $all[$i]['quarantine'] = ['at' => date('c'), 'dest_rel' => $destRel];
                $all[$i]['quarantined_at'] = $all[$i]['quarantine']['at'];
                $moved++;
                $bytes += (int)($it['size'] ?? 0);

                $details[] = [
                    'rel' => $rel,
                    'dest_rel' => $destRel,
                    'size' => (int)($it['size'] ?? 0),
                    'idx' => $i
                ];
            }
        }

        if (isset($report['checks']['unusedMediaAll'])) {
            $report['checks']['unusedMediaAll'] = $all;
        } else {
            $report['checks']['unusedMedia'] = $all;
        }
        $this->writeJsonAtomic($path, $report);

        $this->setData(['module', $this->getUrl(0), 'cursors', $rid, 'media', $i]);

        $this->logAction([
            'id' => $actionId,
            'type' => 'quarantine_media',
            'reportId' => $rid,
            'created_at' => date('c'),
            'summary' => ['files' => $moved, 'bytes' => $bytes],
            'details' => ['items' => $details]
        ]);

        $this->addOutput([
            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/actions/' . $rid,
            'notification' => sprintf(helper::translate('Quarantaine : %s fichier(s) — %s'), $moved, self::formatBytes($bytes)),
            'state' => ($moved > 0)
        ]);
    }

    /**
     * Crée / affiche une simulation (dry-run) avant exécution.
     * - Création : POST depuis /media (sélection)
     * - Consultation : /plan/p_<token>
     */
    public function plan() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $id = (string)$this->getUrl(2);

        // Consultation d’un plan existant
        if (str_starts_with($id, 'p_')) {
            $plan = $this->readPlan($id);
            if (!$plan) {
                $this->addOutput([
                    'redirect' => helper::baseUrl() . $this->getUrl(0),
                    'notification' => helper::translate('Plan introuvable')
                ]);
                return;
            }
            self::$plan = $plan;
            $this->addOutput(['title' => helper::translate('Simulation'), 'view' => 'plan']);
            return;
        }

        // Création depuis une sélection (POST)
        $rid = $id;
        $returnPage = max(1, (int)($this->getUrl(3) ?: 1));

        if (!$this->isPost()) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage
            ]);
            return;
        }

        $selected = $_POST['siteauditSelect'] ?? [];
        if (!is_array($selected)) $selected = [];
        $selected = array_values(array_unique(array_map('intval', $selected)));

        if (empty($selected)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Aucune sélection')
            ]);
            return;
        }

        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

        $items = [];
        $bytes = 0;
        foreach ($selected as $idx) {
            if (!isset($all[$idx])) continue;
            $it = $all[$idx];
            if (!empty($it['deleted_at'])) continue;
            if (!empty($it['quarantine']) || !empty($it['quarantined_at'])) continue;
            $rel = (string)($it['rel'] ?? '');
            if ($rel === '') continue;
            $bytes += (int)($it['size'] ?? 0);
            $items[] = ['idx' => $idx, 'rel' => $rel, 'size' => (int)($it['size'] ?? 0)];
        }

        if (empty($items)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Aucun fichier éligible dans la sélection')
            ]);
            return;
        }

        $cfg = $this->getConfig();
        $planToken = $this->newId('p_');

        $plan = [
            'token' => $planToken,
            'type' => 'quarantine_selected',
            'reportId' => $rid,
            'created_at' => date('c'),
            'return' => ['view' => 'media', 'page' => $returnPage],
            'items' => $items,
            'cursor' => 0,
            'done' => 0,
            'total' => count($items),
            'bytes' => $bytes,
            'maxBatch' => max(5, (int)($cfg['maxBatch'] ?? 25)),
            'trashRetentionDays' => (int)($cfg['trashRetentionDays'] ?? 14),
        ];

        $this->writePlan($planToken, $plan);

        self::$plan = $plan;
        $this->addOutput([
            'title' => helper::translate('Simulation'),
            'view' => 'plan'
        ]);
    }

    /**
     * Exécute un plan (par lots) — uniquement après simulation.
     * URL : /siteaudit/applyplan/p_<token>
     */
    public function applyplan() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $token = (string)$this->getUrl(2);
        $plan = $this->readPlan($token);
        if (!$plan) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Plan introuvable')
            ]);
            return;
        }

        $rid = (string)($plan['reportId'] ?? '');
        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

        $maxBatch = max(5, (int)($plan['maxBatch'] ?? 25));
        $cursor = (int)($plan['cursor'] ?? 0);
        $items = (array)($plan['items'] ?? []);

        $moved = 0;
        $bytes = 0;
        $actionId = $this->newId('a_');
        $details = [];

        for ($i = $cursor; $i < count($items) && $moved < $maxBatch; $i++) {
            $idx = (int)($items[$i]['idx'] ?? -1);
            if ($idx < 0 || !isset($all[$idx])) continue;

            if (!empty($all[$idx]['deleted_at'])) continue;
            if (!empty($all[$idx]['quarantine']) || !empty($all[$idx]['quarantined_at'])) continue;

            $rel = (string)($all[$idx]['rel'] ?? '');
            if ($rel === '') continue;

            $destRel = $this->moveSourceToQuarantineDetailed($rel);
            if ($destRel !== '') {
                $all[$idx]['quarantine'] = ['at' => date('c'), 'dest_rel' => $destRel];
                $all[$idx]['quarantined_at'] = $all[$idx]['quarantine']['at'];

                $moved++;
                $bytes += (int)($all[$idx]['size'] ?? 0);

                $details[] = [
                    'rel' => $rel,
                    'dest_rel' => $destRel,
                    'size' => (int)($all[$idx]['size'] ?? 0),
                    'idx' => $idx
                ];
            }
        }

        // Réinjecte dans le rapport
        if (isset($report['checks']['unusedMediaAll'])) {
            $report['checks']['unusedMediaAll'] = $all;
        } else {
            $report['checks']['unusedMedia'] = $all;
        }
        $this->writeJsonAtomic($path, $report);

        // MAJ plan
        $plan['cursor'] = min(count($items), $i);
        $plan['done'] = (int)($plan['done'] ?? 0) + $moved;
        $plan['last_batch'] = [
            'at' => date('c'),
            'files' => $moved,
            'bytes' => $bytes
        ];
        if ($plan['cursor'] >= count($items)) {
            $plan['completed_at'] = date('c');
        }
        $this->writePlan($token, $plan);

        $this->logAction([
            'id' => $actionId,
            'type' => 'quarantine_media',
            'reportId' => $rid,
            'created_at' => date('c'),
            'summary' => ['files' => $moved, 'bytes' => $bytes],
            'details' => ['plan' => $token, 'items' => $details]
        ]);

        $notif = ($moved > 0)
            ? sprintf(helper::translate('Lot appliqué : %s fichier(s) — %s'), $moved, self::formatBytes($bytes))
            : helper::translate('Aucun fichier déplacé (déjà traité ou introuvable)');

        $this->addOutput([
            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/plan/' . $token,
            'notification' => $notif,
            'state' => ($moved > 0)
        ]);
    }

    /**
     * Corbeille (quarantaine)
     * URL : /siteaudit/trash/<reportId>/<page>
     */
    public function trash() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $page = max(1, (int)($this->getUrl(3) ?: 1));

        // Création d’un plan (simulation) depuis une sélection (POST)
        if ($this->isPost() && $this->getUser('role') >= self::ROLE_ADMIN) {
            $selected = $_POST['siteauditSelect'] ?? [];
            if (!is_array($selected)) $selected = [];
            $selected = array_values(array_unique(array_map('intval', $selected)));

            if (!empty($selected)) {
                $path = $this->reportPath($rid);
                if ($rid !== '' && is_file($path)) {
                    $report = json_decode(file_get_contents($path), true) ?: [];
                    $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

                    $items = [];
                    $bytes = 0;
                    foreach ($selected as $idx) {
                        if (!isset($all[$idx])) continue;
                        $it = $all[$idx];
                        if (!empty($it['deleted_at'])) continue;
                        if (!empty($it['quarantine']) || !empty($it['quarantined_at'])) continue;
                        $rel = (string)($it['rel'] ?? '');
                        if ($rel === '') continue;
                        $bytes += (int)($it['size'] ?? 0);
                        $items[] = ['idx' => $idx, 'rel' => $rel, 'size' => (int)($it['size'] ?? 0)];
                    }

                    if (!empty($items)) {
                        $cfg = $this->getConfig();
                        $token = $this->newId('p_');
                        $plan = [
                            'token' => $token,
                            'type' => 'quarantine_selected',
                            'reportId' => $rid,
                            'created_at' => date('c'),
                            'return' => ['view' => 'media', 'page' => $page],
                            'items' => $items,
                            'cursor' => 0,
                            'done' => 0,
                            'total' => count($items),
                            'bytes' => $bytes,
                            'maxBatch' => max(5, (int)($cfg['maxBatch'] ?? 25)),
                            'trashRetentionDays' => (int)($cfg['trashRetentionDays'] ?? 14),
                        ];
                        $this->writePlan($token, $plan);

                        $this->addOutput([
                            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/plan/' . $token,
                            'notification' => helper::translate('Simulation créée'),
                            'state' => true
                        ]);
                        return;
                    }
                }
            }

            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $page,
                'notification' => helper::translate('Aucun fichier éligible dans la sélection')
            ]);
            return;
        }


        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            self::$trash = [];
            self::$trashPager = ['rid' => $rid, 'page' => 1, 'pages' => 1, 'total' => 0, 'perPage' => 50];
        } else {
            $report = json_decode(file_get_contents($path), true) ?: [];
            $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

            $cfg = $this->getConfig();
            $retention = max(0, (int)($cfg['trashRetentionDays'] ?? 14));

            $trash = [];
            foreach ($all as $idx => $it) {
                $q = $it['quarantine'] ?? null;
                $qAt = (string)($q['at'] ?? ($it['quarantined_at'] ?? ''));
                if ($qAt === '') continue;

                $rel = (string)($it['rel'] ?? '');
                $destRel = (string)($q['dest_rel'] ?? '');
                if ($destRel === '') {
                    $destRel = $this->quarantineDestRel($rel, $qAt);
                }

                $destAbs = $this->resolveSourceAbs($destRel);
                $exists = $destAbs ? is_file($destAbs) : false;

                $canDelete = true;
                if ($retention > 0) {
                    $t = strtotime($qAt);
                    $canDelete = ($t !== false) ? ((time() - $t) >= ($retention * 86400)) : false;
                }

                $trash[] = [
                    'idx' => $idx,
                    'rel' => $rel,
                    'dest_rel' => $destRel,
                    'ext' => (string)($it['ext'] ?? ''),
                    'size' => (int)($it['size'] ?? 0),
                    'quarantined_at' => $qAt,
                    'exists' => $exists,
                    'can_delete' => $canDelete
                ];
            }

            // tri desc par date
            usort($trash, fn($a, $b) => strcmp($b['quarantined_at'] ?? '', $a['quarantined_at'] ?? ''));

            $perPage = 50;
            $total = count($trash);
            $pages = max(1, (int)ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;

            self::$trash = array_slice($trash, $offset, $perPage);
            self::$trashPager = ['rid' => $rid, 'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage];
        }

        $this->addOutput([
            'title' => helper::translate('Corbeille'),
            'view'  => 'trash'
        ]);
    }

    /**
     * Restaurer un média depuis la quarantaine
     * URL : /siteaudit/restoremedia/<reportId>/<absoluteIndex>/<page>
     */
    public function restoremedia() {
        $this->trashAction('restore', __FUNCTION__);
    }

    /**
     * Supprimer définitivement depuis la corbeille (après rétention)
     * URL : /siteaudit/purgetrash/<reportId>/<absoluteIndex>/<page>
     */
    public function purgetrash() {
        $this->trashAction('purge', __FUNCTION__);
    }

    /**
     * Annuler une action (undo)
     * URL : /siteaudit/undo/<actionId>
     */
    public function undo() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $actionId = (string)$this->getUrl(2);
        $action = $this->readAction($actionId);
        if (!$action) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Action introuvable')
            ]);
            return;
        }
        if (!empty($action['undone_at'])) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/actions/' . ($action['reportId'] ?? ''),
                'notification' => helper::translate('Action déjà annulée')
            ]);
            return;
        }

        $ok = false;
        $type = (string)($action['type'] ?? '');
        if ($type === 'quarantine_media') {
            $ok = $this->undoQuarantine($action);
        } elseif ($type === 'fix_links') {
            $ok = $this->undoFixLinks($action);
        }

        if ($ok) {
            $action['undone_at'] = date('c');
            $this->writeAction($actionId, $action);
        }

        $this->addOutput([
            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/actions/' . ($action['reportId'] ?? ''),
            'notification' => $ok ? helper::translate('Action annulée') : helper::translate('Annulation impossible (fichiers manquants ou conflit)'),
            'state' => $ok
        ]);
    }


    /**
     * Gestion des médias “non utilisés” d’un rapport.
     * URL : /siteaudit/media/<reportId>/<page>
     */
    public function media() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        if ($rid === '') {
            $rid = (string)($this->getData(['module', $this->getUrl(0), 'lastReport', 'id']) ?? '');
        }
        $page = max(1, (int)($this->getUrl(3) ?: 1));

        // Création d’un plan (simulation) depuis une sélection (POST)
        if ($this->isPost() && $this->getUser('role') >= self::ROLE_ADMIN) {
            $selected = $_POST['siteauditSelect'] ?? [];
            if (!is_array($selected)) $selected = [];
            $selected = array_values(array_unique(array_map('intval', $selected)));

            if (!empty($selected)) {
                $path = $this->reportPath($rid);
                if ($rid !== '' && is_file($path)) {
                    $report = json_decode(file_get_contents($path), true) ?: [];
                    $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

                    $items = [];
                    $bytes = 0;
                    foreach ($selected as $idx) {
                        if (!isset($all[$idx])) continue;
                        $it = $all[$idx];
                        if (!empty($it['deleted_at'])) continue;
                        if (!empty($it['quarantine']) || !empty($it['quarantined_at'])) continue;
                        $rel = (string)($it['rel'] ?? '');
                        if ($rel === '') continue;
                        $bytes += (int)($it['size'] ?? 0);
                        $items[] = ['idx' => $idx, 'rel' => $rel, 'size' => (int)($it['size'] ?? 0)];
                    }

                    if (!empty($items)) {
                        $cfg = $this->getConfig();
                        $token = $this->newId('p_');
                        $plan = [
                            'token' => $token,
                            'type' => 'quarantine_selected',
                            'reportId' => $rid,
                            'created_at' => date('c'),
                            'return' => ['view' => 'media', 'page' => $page],
                            'items' => $items,
                            'cursor' => 0,
                            'done' => 0,
                            'total' => count($items),
                            'bytes' => $bytes,
                            'maxBatch' => max(5, (int)($cfg['maxBatch'] ?? 25)),
                            'trashRetentionDays' => (int)($cfg['trashRetentionDays'] ?? 14),
                        ];
                        $this->writePlan($token, $plan);

                        $this->addOutput([
                            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/plan/' . $token,
                            'notification' => helper::translate('Simulation créée'),
                            'state' => true
                        ]);
                        return;
                    }
                }
            }

            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $page,
                'notification' => helper::translate('Aucun fichier éligible dans la sélection')
            ]);
            return;
        }


        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            self::$media = [];
            self::$mediaPager = ['rid' => $rid, 'page' => 1, 'pages' => 1, 'total' => 0, 'perPage' => 50];
        } else {
            $report = json_decode(file_get_contents($path), true) ?: [];
            $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);

            // Économies estimées (fichiers encore à traiter)
            $todoCount = 0;
            $todoBytes = 0;
            foreach ($all as $it) {
                if (!empty($it['deleted_at'])) continue;
                if (!empty($it['quarantine']) || !empty($it['quarantined_at'])) continue;
                $todoCount++;
                $todoBytes += (int)($it['size'] ?? 0);
            }

            $perPage = 50;
            $total = count($all);
            $pages = max(1, (int)ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;
            self::$media = array_slice($all, $offset, $perPage);
            self::$mediaPager = ['rid' => $rid, 'page' => $page, 'pages' => $pages, 'total' => $total, 'perPage' => $perPage, 'offset' => $offset, 'todoCount' => $todoCount, 'todoBytes' => $todoBytes];
        }

        $this->addOutput([
            'title' => helper::translate('Médias non utilisés'),
            'view'  => 'media'
        ]);
    }

    /**
     * Met un média en quarantaine.
     * URL : /siteaudit/quarantine/<reportId>/<absoluteIndex>/<page>
     */
    public function quarantine() {
        $this->mediaAction('quarantine', __FUNCTION__);
    }

    /**
     * Supprime définitivement un média.
     * URL : /siteaudit/deletemedia/<reportId>/<absoluteIndex>/<page>
     */
    public function deletemedia() {
        $this->mediaAction('delete', __FUNCTION__);
    }

    /**
     * Export Markdown du rapport courant vers site/file/source/<quarantineFolder>/report-<id>.md
     */
    public function exportmd() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $id = $this->getUrl(2);
        $path = $this->reportPath($id);
        if (!$id || !is_file($path)) {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/reports',
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $md = $this->reportToMarkdown($report);

        $cfg = $this->getConfig();
        $folder = trim($cfg['quarantineFolder']);
        $targetDir = self::FILE_DIR . 'source/' . $folder;
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $file = 'report-' . $id . '.md';
        file_put_contents($targetDir . '/' . $file, $md, LOCK_EX);

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/report/' . $id,
            'notification' => sprintf(helper::translate('Export Markdown créé : %s'), $folder . '/' . $file),
            'state'        => true
        ]);
    }

    /**
     * Supprime un rapport
     */
    public function deleteReport() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $id = $this->getUrl(2);
        $path = $this->reportPath($id);

        if ($id && is_file($path)) {
            @unlink($path);
            $this->removeFromIndex($id);
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/reports',
                'notification' => helper::translate('Rapport supprimé'),
                'state'        => true
            ]);
            return;
        }

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/reports',
            'notification' => helper::translate('Rapport introuvable')
        ]);
    }

    /**
     * Vider /site/tmp (admin uniquement)
     */
    public function cleartmp() {
        if ($this->getUser('permission', __CLASS__, __FUNCTION__) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $tmp = 'site/tmp';
        $deleted = 0;
        if (is_dir($tmp)) {
            $deleted = $this->rmTreeContent($tmp, ['.htaccess', 'index.php']);
        }

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0),
            'notification' => sprintf(helper::translate('Dossier tmp nettoyé (%s éléments)'), $deleted),
            'state'        => true
        ]);
    }

    
    /* =========================
     * Campus — helpers data
     * ========================= */

    /**
     * Liste des espaces (cours) avec stats simples.
     * NOTE : lecture via getData/fetchDataFile pour respecter la config JsonDb (compression/chiffrement).
     */
    private function loadCampusSpaces(): array {
        $spaces = [];
        $courses = $this->getData(['course']);
        if (!is_array($courses)) $courses = [];

        foreach ($courses as $courseId => $course) {
            $courseId = (string)$courseId;
            if ($courseId === '' || !is_array($course)) continue;

            // Pages du cours
            $pages = $this->fetchDataFile('page', $courseId, 'page');
            if (!is_array($pages)) $pages = [];
            $pageCount = count($pages);

            // Taille des contenus HTML
            $contentSize = 0;
            $contentDir = rtrim(self::DATA_DIR, '/') . '/' . $courseId . '/content/';
            if (is_dir($contentDir)) {
                $rii = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($contentDir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($rii as $f) {
                    /** @var SplFileInfo $f */
                    if ($f->isFile() && strtolower($f->getExtension()) === 'html') {
                        $contentSize += (int)$f->getSize();
                    }
                }
            }

            // Inscriptions
            $enrol = $this->getData(['enrolment', $courseId]);
            if (!is_array($enrol)) $enrol = [];
            $enrolCount = count($enrol);

            $spaces[$courseId] = [
                'id' => $courseId,
                'title' => (string)($course['title'] ?? $courseId),
                'author' => (string)($course['author'] ?? ''),
                'enrolment' => (int)($course['enrolment'] ?? 0),
                'enrolmentCount' => $enrolCount,
                'pageCount' => $pageCount,
                'contentSize' => $contentSize,
                'created' => (string)($course['created'] ?? ''),
                'modified' => (string)($course['modified'] ?? ''),
            ];
        }

        return $spaces;
    }

    /**
     * Liste des inscriptions (tous cours) avec quelques colonnes utiles.
     */
    private function loadCampusEnrolments(): array {
        $rows = [];
        $courses = $this->getData(['course']);
        if (!is_array($courses)) $courses = [];

        $enrolAll = $this->getData(['enrolment']);
        if (!is_array($enrolAll)) $enrolAll = [];

        foreach ($enrolAll as $courseId => $courseEnrol) {
            $courseId = (string)$courseId;
            if (!is_array($courseEnrol) || $courseId === '') continue;

            $courseTitle = (string)(($courses[$courseId]['title'] ?? null) ?? ($courses[$courseId]['name'] ?? null) ?? $courseId);

            foreach ($courseEnrol as $userId => $data) {
                $userId = (string)$userId;
                if (!is_array($data) || $userId === '') continue;

                $user = $this->getData(['user', $userId]);
                if (!is_array($user)) continue;

                $rows[] = [
                    'courseId' => $courseId,
                    'courseTitle' => $courseTitle,
                    'userId' => $userId,
                    'userName' => trim(((string)($user['firstname'] ?? '')) . ' ' . ((string)($user['lastname'] ?? ''))),
                    'userEmail' => (string)($user['email'] ?? ''),
                    'progress' => (int)($data['progress'] ?? 0),
                    'lastPageView' => (string)($data['lastPageView'] ?? ''),
                    'datePageView' => (int)($data['datePageView'] ?? 0),
                    'group' => (string)($data['group'] ?? ''),
                ];
            }
        }

        // Tri : cours puis nom
        usort($rows, function ($a, $b) {
            $c = strcmp((string)($a['courseId'] ?? ''), (string)($b['courseId'] ?? ''));
            if ($c !== 0) return $c;
            return strcmp((string)($a['userName'] ?? ''), (string)($b['userName'] ?? ''));
        });

        return $rows;
    }

    /**
     * Pages d’un cours, sous forme de tableau associatif.
     */
    private function coursePages(string $courseId): array {
        $courseId = trim($courseId);
        if ($courseId === '') return [];
        $pages = $this->fetchDataFile('page', $courseId, 'page');
        return is_array($pages) ? $pages : [];
    }

    /**
     * Collecte optionnelle d'IDs de pages de cours.
     * Dans ZwiiCampus, les routes de cours sont /<courseId>/<pageId> ; on n'ajoute donc rien au graphe global.
     */
    private function collectCoursePageIds(): array {
        return [];
    }

    /**
     * Vérifie l’existence d’une page dans un cours.
     */
    private function coursePageExists(string $courseId, string $pageId): bool {
        $courseId = trim($courseId);
        $pageId = trim($pageId);
        if ($courseId === '' || $pageId === '') return false;
        $pages = $this->coursePages($courseId);
        return isset($pages[$pageId]);
    }

    /**
     * Charge les contenus HTML d’un cours pour l’analyse des liens.
     * Retourne des contextes homogènes avec les pages CMS, avec en plus spaceId.
     */
    private function loadSpaceContent(string $courseId): array {
        $items = [];
        $courseId = trim($courseId);
        if ($courseId === '') return $items;

        $pages = $this->coursePages($courseId);
        $contentDir = rtrim(self::DATA_DIR, '/') . '/' . $courseId . '/content/';

        foreach ($pages as $pageId => $page) {
            $pageId = (string)$pageId;
            if ($pageId === '') continue;

            $content = '';
            $path = $contentDir . $pageId . '.html';
            if (is_file($path)) {
                $content = (string)file_get_contents($path);
            } elseif (is_array($page) && isset($page['content']) && is_string($page['content'])) {
                $content = (string)$page['content'];
            }

            if ($content !== '') {
                $items[] = [
                    'locale' => 'default',
                    'spaceId' => $courseId,
                    'pageId' => $pageId,
                    'title'  => (string)($page['title'] ?? $pageId),
                    'content' => $content,
                ];
            }
        }

        return $items;
    }

    /**
     * Classifie un lien en prenant en compte les espaces ZwiiCampus.
     * - /<courseId> => espace
     * - /<courseId>/<pageId> => page d’espace
     * - lien relatif dans un espace : "pageId" => /<courseId>/pageId
     */
    private function classifyLinkForCampus(string $url, array $spaces, array $pageIds, ?string $fromSpace = null): array {
        $u = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($u === '' || $u === '#') return ['type' => 'ignore'];

        $lower = strtolower($u);
        foreach (['mailto:', 'tel:', 'javascript:', 'data:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) return ['type' => 'ignore'];
        }

        // Fichiers (images/docs)
        if (preg_match('#\bfile/(source|thumb)/#i', $u) || preg_match('#\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|rar|7z|mp4|mp3|webm|txt|md|epub)$#i', $u)) {
            $ref = $this->normalizeFileRef($u);
            return $ref ? ['type' => 'file', 'fileRef' => $ref] : ['type' => 'ignore'];
        }

        // Normaliser en chemin (supprime domaine + query/fragment)
        $path = preg_replace('#^https?://[^/]+#i', '', $u);
        $path = preg_replace('/[?#].*$/', '', $path);
        $path = ltrim($path, '/');

        // Lien relatif dans un espace (ex: "chapitre-2")
        $isAbs = str_starts_with($u, '/') || preg_match('#^https?://#i', $u);
        if (!$isAbs && $fromSpace) {
            $rel = ltrim($path, './');
            if ($rel !== '' && strpos($rel, '..') === false) {
                $seg = explode('/', $rel);
                $pageId = (string)($seg[0] ?? '');
                if ($pageId !== '') {
                    return ['type' => 'space_page', 'spaceId' => (string)$fromSpace, 'pageId' => $pageId];
                }
            }
        }

        $seg = array_values(array_filter(explode('/', $path), fn($x) => $x !== ''));
        $s1 = (string)($seg[0] ?? '');
        $s2 = (string)($seg[1] ?? '');

        if ($s1 !== '' && isset($spaces[$s1])) {
            if ($s2 !== '') return ['type' => 'space_page', 'spaceId' => $s1, 'pageId' => $s2];
            return ['type' => 'space', 'spaceId' => $s1];
        }

        // Fallback : pages du site d’accueil
        return $this->classifyLink($u, $pageIds);
    }


/* =========================
     * Audit — cœur
     * ========================= */

    
    /* =========================
     * Audit — cœur (Campus)
     * ========================= */

    private function buildReport(): array {
        $cfg = $this->getConfig();
        $maxItems = max(50, (int)($cfg['maxItems'] ?? 200));
        $maxBytes = max(1, (int)($cfg['maxFileMb'] ?? 2)) * 1024 * 1024;

        // Fichiers médias
        $sourceFiles = [];
        $filesIndex = ['exact' => [], 'canon' => [], 'meta' => []];
        if (!empty($cfg['scanFileLinks']) || !empty($cfg['scanUnusedMedia'])) {
            $sourceFiles = $this->listSourceFiles((string)($cfg['ignoreDirs'] ?? ''));
            $filesIndex = $this->indexSourceFiles($sourceFiles);
        }

        // Pages CMS standards (site d’accueil "home")
        $pagesByLocale = $this->loadPagesByLocale();
        $cmsPageIds = $this->collectPageIds($pagesByLocale);

        // Espaces Campus (cours)
        $spaces = [];
        $coursePageIds = [];
        if (!empty($cfg['scanSpaces'])) {
            $spaces = $this->loadCampusSpaces();
            $coursePageIds = $this->collectCoursePageIds();
        }

        // Ensemble des IDs de pages (CMS + espaces) pour reconnaître les liens "/slug"
        $allPageIds = $cmsPageIds + $coursePageIds;

        // Contenus HTML (pages CMS + espaces)
        $contents = $this->loadAllContents($pagesByLocale);

        if (!empty($cfg['scanSpaces'])) {
            foreach (array_keys($spaces) as $spaceId) {
                foreach ($this->loadSpaceContent($spaceId) as $ctx) {
                    $contents[] = $ctx;
                }
            }
        }

        // Textes JSON (utile pour détecter médias utilisés)
        $jsonTexts = $this->loadAllJsonTexts();

        // Index des liens
        $graph = [];
        $brokenPageLinks = [];
        $brokenSpaceLinks = [];
        $brokenFileLinks = [];

        if (!empty($cfg['scanLinks']) || !empty($cfg['scanFileLinks']) || !empty($cfg['scanOrphans'])) {
            foreach ($contents as $ctx) {
                $from = (string)($ctx['pageId'] ?? '');
                if ($from === '') continue;

                $fromLocale = (string)($ctx['locale'] ?? 'default');
                $fromSpace  = $ctx['spaceId'] ?? null;
                $html = (string)($ctx['content'] ?? '');

                $links = $this->extractLinks($html);

                foreach ($links as $url) {
                    $parsed = $this->classifyLinkForCampus($url, $spaces, $allPageIds, $fromSpace);

                    if ($parsed['type'] === 'ignore') {
                        continue;
                    }

                    if ($parsed['type'] === 'space' || $parsed['type'] === 'space_page') {
                        $targetSpace = (string)($parsed['spaceId'] ?? '');
                        $targetPage  = (string)($parsed['pageId'] ?? '');

                        // Espace inexistant
                        if ($targetSpace !== '' && !isset($spaces[$targetSpace])) {
                            $brokenSpaceLinks[] = [
                                'from' => $from,
                                'fromSpace' => $fromSpace,
                                'locale' => $fromLocale,
                                'url'  => $url,
                                'type' => $parsed['type'],
                                'spaceId' => $targetSpace,
                                'pageId' => $targetPage !== '' ? $targetPage : null
                            ];
                            continue;
                        }

                        // Page d’espace inexistante
                        if ($parsed['type'] === 'space_page' && $targetSpace !== '' && $targetPage !== '') {
                            if (!$this->coursePageExists($targetSpace, $targetPage)) {
                                $brokenSpaceLinks[] = [
                                    'from' => $from,
                                    'fromSpace' => $fromSpace,
                                    'locale' => $fromLocale,
                                    'url'  => $url,
                                    'type' => 'space_page_missing',
                                    'spaceId' => $targetSpace,
                                    'pageId' => $targetPage
                                ];
                            }
                        }
                        continue;
                    }

                    if ($parsed['type'] === 'page') {
                        $to = (string)($parsed['pageId'] ?? '');
                        if ($to !== '') {
                            $graph[$from] = $graph[$from] ?? [];
                            $graph[$from][$to] = true;
                        }
                        continue;
                    }

                    if ($parsed['type'] === 'broken_page' && !empty($cfg['scanLinks'])) {
                        $brokenPageLinks[] = [
                            'from' => $from,
                            'fromSpace' => $fromSpace,
                            'locale' => $fromLocale,
                            'url'  => $url,
                            'candidate' => (string)($parsed['candidate'] ?? '')
                        ];
                        continue;
                    }

                    if ($parsed['type'] === 'file' && !empty($cfg['scanFileLinks'])) {
                        $filePath = $this->resolveFilePath((string)$parsed['fileRef']);
                        if ($filePath && !is_file($filePath)) {
                            $suggested = '';
                            $options = [];
                            if (!empty($filesIndex['exact']) || !empty($filesIndex['canon'])) {
                                $s = $this->suggestMovedFileDetailed((string)$parsed['fileRef'], $filesIndex);
                                $suggested = (string)($s['suggested'] ?? '');
                                $options = (array)($s['options'] ?? []);
                            }
                            $brokenFileLinks[] = [
                                'from' => $from,
                                'fromSpace' => $fromSpace,
                                'locale' => $fromLocale,
                                'url'  => $url,
                                'file' => (string)$parsed['fileRef'],
                                'suggested' => $suggested,
                                'suggestions' => $options
                            ];
                        }
                        continue;
                    }
                }
            }
        }

        // Pages orphelines (site d’accueil uniquement)
        $orphans = [];
        $titleDuplicates = [];
        $hierarchyIssues = [];

        if (!empty($cfg['scanOrphans'])) {
            $homeId = $this->discoverHomePageId($pagesByLocale);
            $menuSeeds = $this->menuPageIds($pagesByLocale);
            $seeds = array_values(array_unique(array_filter(array_merge([$homeId], $menuSeeds))));

            $reachable = $this->reachablePages($seeds, $graph);

            foreach ($pagesByLocale as $locale => $pages) {
                foreach ($pages as $pageId => $page) {
                    $pos = (int)($page['position'] ?? 0);
                    $parent = (string)($page['parentPageId'] ?? '');
                    if ($parent !== '' && !isset($pages[$parent])) {
                        $hierarchyIssues[] = [
                            'pageId' => (string)$pageId,
                            'locale' => $locale,
                            'issue'  => 'Parent introuvable',
                            'parent' => $parent
                        ];
                    }
                    if ($pos === 0 && !isset($reachable[$pageId])) {
                        $orphans[] = [
                            'pageId' => (string)$pageId,
                            'locale' => $locale,
                            'title'  => (string)($page['title'] ?? $pageId),
                        ];
                    }
                }
            }

            foreach ($pagesByLocale as $locale => $pages) {
                $map = [];
                foreach ($pages as $pageId => $page) {
                    $t = trim((string)($page['title'] ?? ''));
                    if ($t === '') continue;
                    $key = mb_strtolower($t, 'UTF-8');
                    $map[$key] = $map[$key] ?? [];
                    $map[$key][] = (string)$pageId;
                }
                foreach ($map as $tKey => $ids) {
                    if (count($ids) > 1) {
                        $titleDuplicates[] = [
                            'locale' => $locale,
                            'title'  => $tKey,
                            'pages'  => $ids
                        ];
                    }
                }
            }
        }

        // Médias non utilisés
        $unusedMedia = [];
        $heavyFiles = [];
        $countsMedia = ['total' => 0, 'used' => 0, 'unused' => 0];
        $unusedBytes = 0;
        $heavyBytes = 0;
        $unusedMediaAll = [];

        if (!empty($cfg['scanUnusedMedia'])) {
            $extra = [];
            if (!empty($cfg['scanThemeFiles'])) {
                $extra = array_merge($extra, $this->loadTextFiles('site/theme', ['css','js','php','html','htm','json','md','xml','txt']));
            }
            if (!empty($cfg['scanModuleFiles'])) {
                $extra = array_merge($extra, $this->loadTextFiles('site/module', ['php','html','htm','json','md','xml','txt','css','js']));
            }

            $haystack = $this->buildHaystack($contents, $jsonTexts, $extra);

            $files = $sourceFiles;
            $countsMedia['total'] = count($files);

            foreach ($files as $file) {
                $rel = (string)$file['rel'];
                $size = (int)$file['size'];

                $used = (strpos($haystack, $rel) !== false) || (strpos($haystack, str_replace('/', '\\/', $rel)) !== false);
                if ($used) {
                    $countsMedia['used']++;
                } else {
                    $countsMedia['unused']++;
                    $unusedBytes += $size;
                    $unusedMedia[] = $file;
                }

                if ($size >= $maxBytes) {
                    $heavyBytes += $size;
                    $heavyFiles[] = $file;
                }
            }

            if (!empty($cfg['storeFullUnusedMedia'])) {
                $unusedMediaAll = $unusedMedia;
            }
        }

        // Statistiques Campus
        $spaceStats = [
            'total' => count($spaces),
            'empty' => 0,
            'withoutAuthor' => 0,
            'totalEnrolments' => 0,
        ];

        foreach ($spaces as $space) {
            if ((int)($space['pageCount'] ?? 0) === 0) $spaceStats['empty']++;
            if (empty($space['author'])) $spaceStats['withoutAuthor']++;
            $spaceStats['totalEnrolments'] += (int)($space['enrolmentCount'] ?? 0);
        }

        // Limites d'affichage
        $brokenFileLinksAll = $brokenFileLinks;
        $orphans = array_slice($orphans, 0, $maxItems);
        $brokenPageLinks = array_slice($brokenPageLinks, 0, $maxItems);
        $brokenSpaceLinks = array_slice($brokenSpaceLinks, 0, $maxItems);
        $brokenFileLinks = array_slice($brokenFileLinks, 0, $maxItems);
        $unusedMedia = array_slice($unusedMedia, 0, $maxItems);
        $heavyFiles = array_slice($heavyFiles, 0, $maxItems);
        $hierarchyIssues = array_slice($hierarchyIssues, 0, $maxItems);
        $titleDuplicates = array_slice($titleDuplicates, 0, $maxItems);

        $id = date('Ymd-His') . '-' . substr(sha1(random_bytes(16)), 0, 8);

        $summary = [
            'pages' => count($cmsPageIds),
            'orphans' => count($orphans),
            'brokenPageLinks' => count($brokenPageLinks),
            'brokenSpaceLinks' => count($brokenSpaceLinks),
            'brokenFileLinks' => count($brokenFileLinks),
            'fixableFileLinks' => count(array_filter($brokenFileLinksAll, fn($it) => empty($it['fixed']) && !empty($it['suggested']))),
            'ambiguousFileLinks' => count(array_filter($brokenFileLinksAll, fn($it) => empty($it['fixed']) && empty($it['suggested']) && !empty($it['suggestions']))),
            'mediaTotal' => $countsMedia['total'],
            'mediaUnused' => $countsMedia['unused'],
            'mediaUnusedBytes' => $unusedBytes,
            'heavyFiles' => count($heavyFiles),
            'heavyFilesBytes' => $heavyBytes,
            'spacesTotal' => $spaceStats['total'],
            'spacesEmpty' => $spaceStats['empty'],
            'spacesWithoutAuthor' => $spaceStats['withoutAuthor'],
            'totalEnrolments' => $spaceStats['totalEnrolments'],
        ];

        return [
            'meta' => [
                'id' => $id,
                'created_at' => date('c'),
                'php' => PHP_VERSION,
                'zwii' => defined('common::ZWII_VERSION') ? common::ZWII_VERSION : '',
                'type' => 'campus',
            ],
            'config' => $cfg,
            'summary' => $summary,
            'checks' => [
                'orphans'          => $orphans,
                'hierarchyIssues'  => $hierarchyIssues,
                'titleDuplicates'  => $titleDuplicates,
                'brokenPageLinks'  => $brokenPageLinks,
                'brokenSpaceLinks' => $brokenSpaceLinks,
                'brokenFileLinks'  => $brokenFileLinks,
                'brokenFileLinksAll' => $brokenFileLinksAll,
                'unusedMedia'      => $unusedMedia,
                'unusedMediaAll'   => $unusedMediaAll,
                'heavyFiles'       => $heavyFiles,
                'spaces'           => $spaces,
                'spaceStats'       => $spaceStats,
            ],
        ];
    }


private function getConfig(): array {
        return $this->getData(['module', $this->getUrl(0), 'config']);
    }

    private function initConfig(): void {
        $this->ensureDirs();

        if ($this->getData(['module', $this->getUrl(0), 'config']) === null) {
            $this->setData(['module', $this->getUrl(0), 'config', [
                'maxFileMb'        => 2,
                'maxItems'         => 200,

                'scanOrphans'      => true,
                'scanLinks'        => true,
                'scanFileLinks'    => true,
                'scanUnusedMedia'  => true,
                'scanThemeFiles'   => true,
                'scanModuleFiles'  => false,

                'storeFullUnusedMedia' => true,

                'ignoreDirs'       => 'thumb,tmp,cache,backup,.git',
                'quarantineFolder' => '_siteaudit',

                // Gros sites
                'maxBatch'           => 25,
                'trashRetentionDays' => 14,
                'allowHardDelete'    => false,
                'strictUniqueFix'    => true
            ]]);
        }
    }

    private function ensureDirs(): void {
        if (!is_dir(self::DATADIRECTORY)) {
            @mkdir(self::DATADIRECTORY, 0755, true);
        }
        foreach (['reports', 'actions', 'plans', 'backups'] as $d) {
            $p = self::DATADIRECTORY . $d;
            if (!is_dir($p)) {
                @mkdir($p, 0755, true);
            }
        }
    }

    private function reportPath(string $id): string {
        return self::DATADIRECTORY . 'reports/' . basename($id) . '.json';
    }

    private function writeJsonAtomic(string $path, array $data): void {
        $tmp = $path . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $path);
    }

    private function indexPath(): string {
        return self::DATADIRECTORY . 'reports/index.json';
    }

    private function indexReport(array $report): void {
        $index = $this->readIndex();
        $id = $report['meta']['id'];

        $index['items'][$id] = [
            'id' => $id,
            'created_at' => $report['meta']['created_at'] ?? '',
            'summary' => $report['summary'] ?? [],
        ];

        // tri desc par date
        uasort($index['items'], function ($a, $b) {
            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
        });

        $this->writeJsonAtomic($this->indexPath(), $index);
    }

    private function removeFromIndex(string $id): void {
        $index = $this->readIndex();
        if (isset($index['items'][$id])) {
            unset($index['items'][$id]);
            $this->writeJsonAtomic($this->indexPath(), $index);
        }
    }

    private function readIndex(): array {
        $path = $this->indexPath();
        if (!is_file($path)) {
            return ['items' => []];
        }
        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : ['items' => []];
    }

    private function listReports(int $limit = 50): array {
        $index = $this->readIndex();
        $items = array_values($index['items']);
        return array_slice($items, 0, $limit);
    }

    /* =========================
     * Analyse des sources
     * ========================= */

    
    /**
     * Charge les pages du site d’accueil.
     * ZwiiCampus stocke l’accueil dans /site/data/home/.
     * ZwiiCMS “classique” stocke dans /site/data/ ou /site/data/<lang>/.
     */
    private function loadPagesByLocale(): array {
        $result = [];
        $dataDir = rtrim(self::DATA_DIR, '/') . '/';

        // Mode Campus : dossier "home" présent
        $homeDir = $dataDir . 'home/';
        if (is_dir($homeDir)) {
            // Par défaut, home/page.json
            $pageJson = $homeDir . 'page.json';
            if (is_file($pageJson)) {
                $raw = json_decode(file_get_contents($pageJson), true);
                if (is_array($raw)) {
                    $pages = $raw['page'] ?? $raw;
                    if (is_array($pages)) {
                        $result['default'] = $pages;
                    }
                }
            }

            // Éventuels sous-dossiers de langue : home/<locale>/page.json
            $sub = glob($homeDir . '*/page.json') ?: [];
            foreach ($sub as $p) {
                $loc = basename(dirname($p));
                $raw = json_decode(file_get_contents($p), true);
                if (!is_array($raw)) continue;
                $pages = $raw['page'] ?? $raw;
                if (is_array($pages)) {
                    $result[$loc] = $pages;
                }
            }

            return $result;
        }

        // Mode ZwiiCMS : /site/data/<locale>/page.json ou /site/data/page.json
        $dirs = glob($dataDir . '*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $locale = basename($dir);
            $pageJson = $dir . '/page.json';
            if (!is_file($pageJson)) continue;

            $raw = json_decode(file_get_contents($pageJson), true);
            if (!is_array($raw)) continue;
            $pages = $raw['page'] ?? $raw;
            if (!is_array($pages)) continue;

            $result[$locale] = $pages;
        }

        if (empty($result)) {
            $pageJson = $dataDir . 'page.json';
            if (is_file($pageJson)) {
                $raw = json_decode(file_get_contents($pageJson), true);
                if (is_array($raw)) {
                    $pages = $raw['page'] ?? $raw;
                    if (is_array($pages)) {
                        $result['default'] = $pages;
                    }
                }
            }
        }

        return $result;
    }


private function collectPageIds(array $pagesByLocale): array {
        $ids = [];
        foreach ($pagesByLocale as $locale => $pages) {
            foreach ($pages as $pageId => $page) {
                $ids[$pageId] = true;
            }
        }
        return $ids;
    }

    private function discoverHomePageId(array $pagesByLocale): string {
        // Essaye config.json
        $cfgPath = rtrim(self::DATA_DIR, '/') . '/config.json';
        if (is_file($cfgPath)) {
            $cfg = json_decode(file_get_contents($cfgPath), true);
            if (is_array($cfg)) {
                $home = $cfg['config']['homePageId'] ?? $cfg['homePageId'] ?? '';
                if ($home) return (string)$home;
            }
        }
        // Fallback : première page position 1
        foreach ($pagesByLocale as $pages) {
            foreach ($pages as $pageId => $page) {
                if ((int)($page['position'] ?? 0) === 1) return (string)$pageId;
            }
        }
        // Fallback absolu
        foreach ($pagesByLocale as $pages) {
            foreach ($pages as $pageId => $page) return (string)$pageId;
        }
        return '';
    }

    private function menuPageIds(array $pagesByLocale): array {
        $menu = [];
        foreach ($pagesByLocale as $pages) {
            foreach ($pages as $pageId => $page) {
                if ((int)($page['position'] ?? 0) > 0) {
                    $menu[] = $pageId;
                }
            }
        }
        return array_values(array_unique($menu));
    }

    
    private function loadAllContents(array $pagesByLocale): array {
        $items = [];
        $dataDir = rtrim(self::DATA_DIR, '/') . '/';

        $homeDir = $dataDir . 'home/';
        $isCampus = is_dir($homeDir);

        foreach ($pagesByLocale as $locale => $pages) {
            if ($isCampus) {
                // home/content/ ou home/<locale>/content/
                $contentDir = ($locale === 'default')
                    ? ($homeDir . 'content/')
                    : ($homeDir . $locale . '/content/');
            } else {
                // ZwiiCMS : content/ ou <locale>/content/
                $contentDir = ($locale === 'default')
                    ? ($dataDir . 'content/')
                    : ($dataDir . $locale . '/content/');
            }

            foreach ($pages as $pageId => $page) {
                $content = '';
                $path = $contentDir . $pageId . '.html';
                if (is_file($path)) {
                    $content = (string)file_get_contents($path);
                } elseif (isset($page['content']) && is_string($page['content'])) {
                    $content = (string)$page['content'];
                }

                if ($content !== '') {
                    $items[] = [
                        'locale' => (string)$locale,
                        'pageId' => (string)$pageId,
                        'title'  => (string)($page['title'] ?? $pageId),
                        'content' => $content,
                    ];
                }
            }
        }
        return $items;
    }


private function loadAllJsonTexts(): array {
        $texts = [];
        $root = rtrim(self::DATA_DIR, '/') . '/';
        if (!is_dir($root)) return $texts;

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) continue;
            if (strtolower($file->getExtension()) !== 'json') continue;

            $p = str_replace('\\', '/', $file->getPathname());
            // Ignore nos propres rapports
            if (strpos($p, '/siteaudit/reports/') !== false) continue;

            $content = @file_get_contents($p);
            if ($content === false) continue;
            $texts[] = $content;
        }

        return $texts;
    }

    private function buildHaystack(array $contents, array $jsonTexts, array $extraTexts = []): string {
        $parts = [];
        foreach ($contents as $ctx) {
            $parts[] = $ctx['content'];
        }
        foreach ($jsonTexts as $txt) {
            $parts[] = $txt;
        }
        foreach ($extraTexts as $txt) {
            $parts[] = $txt;
        }
        // Limiter la taille pour éviter de saturer la mémoire
        $haystack = implode("\n", $parts);
        if (strlen($haystack) > 20_000_000) {
            $haystack = substr($haystack, 0, 20_000_000);
        }
        return $haystack;
    }

    private function listSourceFiles(string $ignoreDirsCsv): array {
        $ignore = array_filter(array_map('trim', explode(',', (string)$ignoreDirsCsv)));
        $sourceRoot = rtrim(self::FILE_DIR, '/') . '/source/';

        $files = [];
        if (!is_dir($sourceRoot)) return $files;

        $rii = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                function ($current, $key, $iterator) use ($ignore) {
                    /** @var SplFileInfo $current */
                    if ($current->isDir()) {
                        $base = $current->getBasename();
                        if (in_array($base, $ignore, true)) return false;
                    }
                    return true;
                }
            )
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) continue;
            $abs = str_replace('\\', '/', $file->getPathname());
            $rel = str_replace(str_replace('\\', '/', $sourceRoot), '', $abs);
            $files[] = [
                'rel'  => $rel,
                'ext'  => strtolower($file->getExtension()),
                'size' => (int)$file->getSize(),
            ];
        }
        return $files;
    }

    /**
     * Charge le contenu texte d’un répertoire (css/js/php/html/json/md…) pour détecter les références à des médias.
     * Limite volontairement la taille par fichier pour éviter de charger des gros binaires.
     */
    private function loadTextFiles(string $root, array $exts): array {
        $texts = [];
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if ($root === '' || !is_dir($root)) return $texts;

        $maxPerFile = 250_000; // 250 Ko
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $exts, true)) continue;
            $p = $file->getPathname();
            $size = (int)$file->getSize();
            if ($size <= 0) continue;
            $content = @file_get_contents($p, false, null, 0, min($maxPerFile, $size));
            if ($content === false || $content === '') continue;
            $texts[] = $content;
        }

        return $texts;
    }

    /**
     * Index des fichiers /file/source par nom de fichier (exact) et par nom “canonique”.
     */
    private function indexSourceFiles(array $files): array {
        $exact = [];
        $canon = [];
        $meta  = [];

        foreach ($files as $f) {
            $rel = (string)($f['rel'] ?? '');
            if ($rel === '') continue;

            $meta[$rel] = [
                'size' => (int)($f['size'] ?? 0),
                'ext'  => (string)($f['ext'] ?? ''),
            ];

            $base = basename($rel);

            // Exact
            $key = mb_strtolower($base, 'UTF-8');
            $exact[$key] = $exact[$key] ?? [];
            $exact[$key][] = $rel;

            // Canon
            $canonKey = mb_strtolower($this->canonicalizeFilename($base), 'UTF-8');
            $canon[$canonKey] = $canon[$canonKey] ?? [];
            $canon[$canonKey][] = $rel;
        }

        return ['exact' => $exact, 'canon' => $canon, 'meta' => $meta];
    }

    /**
     * Suggère un nouvel emplacement si le fichier a été déplacé.
     * Règle : si le nom (ou son canon) apparaît UNE seule fois, on propose.
     * Sinon, on fournit une liste de candidats (pour choix manuel).
     */
    private function suggestMovedFileDetailed(string $fileRef, array $filesIndex): array {
        $fileRef = str_replace('\\', '/', $fileRef);
        $base = basename($fileRef);

        $key = mb_strtolower($base, 'UTF-8');
        $hits = $filesIndex['exact'][$key] ?? [];

        $canonKey = mb_strtolower($this->canonicalizeFilename($base), 'UTF-8');
        $hits2 = $filesIndex['canon'][$canonKey] ?? [];

        // Déduplication en gardant l’ordre
        $cands = [];
        foreach (array_merge($hits, $hits2) as $rel) {
            if (!isset($cands[$rel])) $cands[$rel] = true;
        }
        $cands = array_keys($cands);

        $suggested = '';
        $options = [];

        // Mode strict : ne proposer automatiquement que si 1 résultat
        if (count($cands) === 1) {
            $suggested = (string)$cands[0];
        } elseif (count($cands) > 1) {
            // On limite les options affichées (UX)
            $max = 12;
            foreach (array_slice($cands, 0, $max) as $rel) {
                $meta = $filesIndex['meta'][$rel] ?? [];
                $options[] = [
                    'rel'  => $rel,
                    'size' => (int)($meta['size'] ?? 0),
                    'ext'  => (string)($meta['ext'] ?? ''),
                ];
            }
        }

        return ['suggested' => $suggested, 'options' => $options];
    }


    /**
     * Canonicalise un nom de fichier pour mieux matcher les images “redimensionnées” (ex : -1024x768, @2x).
     */
    private function canonicalizeFilename(string $filename): string {
        $filename = trim($filename);
        // Sépare extension
        $ext = '';
        $pos = strrpos($filename, '.');
        if ($pos !== false) {
            $ext = substr($filename, $pos);
            $filename = substr($filename, 0, $pos);
        }
        // Retire suffixes courants
        $filename = preg_replace('/-\d{2,5}x\d{2,5}$/', '', $filename);
        $filename = preg_replace('/@\dx$/', '', $filename);
        $filename = preg_replace('/-scaled$/', '', $filename);
        return $filename . $ext;
    }

    private function extractLinks(string $html): array {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $urls = [];
        if (preg_match_all('/\b(?:href|src)\s*=\s*([\"\'])(.*?)\1/i', $html, $m)) {
            foreach ($m[2] as $u) {
                $u = trim($u);
                if ($u !== '') $urls[] = $u;
            }
        }
        return $urls;
    }

    private function classifyLink(string $url, array $pageIds): array {
        $u = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Ignore schemes courants
        $lower = strtolower($u);
        foreach (['mailto:', 'tel:', 'javascript:', 'data:'] as $scheme) {
            if (str_starts_with($lower, $scheme)) {
                return ['type' => 'ignore'];
            }
        }

        // Fichier (file/source, file/thumb, ou extension)
        if (preg_match('#\bfile/(source|thumb)/#i', $u) || preg_match('#\.(jpg|jpeg|png|gif|webp|svg|pdf|zip|rar|7z|mp4|mp3|webm|txt|md|epub)$#i', $u)) {
            $ref = $this->normalizeFileRef($u);
            return $ref ? ['type' => 'file', 'fileRef' => $ref] : ['type' => 'ignore'];
        }

        // Normalisation URL interne (page)
        $candidate = $this->extractPageCandidate($u);
        if ($candidate === '') return ['type' => 'ignore'];

        if (isset($pageIds[$candidate])) {
            return ['type' => 'page', 'pageId' => $candidate];
        }

        // Une URL "qui ressemble" à une page, mais introuvable
        if (preg_match('/^[a-z0-9_-]{2,}$/i', $candidate)) {
            return ['type' => 'broken_page', 'candidate' => $candidate];
        }

        return ['type' => 'ignore'];
    }

    private function extractPageCandidate(string $url): string {
        $u = $url;

        // Retire schéma + host si présent
        $u = preg_replace('#^https?://[^/]+#i', '', $u);
        $u = ltrim($u, '/');

        // Retire index.php? si rewriting off
        $u = preg_replace('#^index\.php\??#i', '', $u);
        $u = ltrim($u, '?');

        // Retire ancres / query
        $u = preg_replace('/#.*/', '', $u);
        $u = preg_replace('/\?.*/', '', $u);

        // Premier segment
        $seg = explode('/', $u)[0] ?? '';
        $seg = trim($seg);

        // Exclure dossiers techniques
        if ($seg === '' || in_array($seg, ['module', 'core', 'site', 'file'], true)) return '';

        return $seg;
    }

    private function normalizeFileRef(string $url): string {
        $u = $url;
        $u = preg_replace('#^https?://[^/]+#i', '', $u);
        $u = ltrim($u, '/');

        // file/source/xxx => xxx
        if (preg_match('#^file/source/(.+)$#i', $u, $m)) {
            return $m[1];
        }
        if (preg_match('#^site/file/source/(.+)$#i', $u, $m)) {
            return $m[1];
        }

        // Si on a juste "gallery/..." on le garde tel quel
        $u = preg_replace('/#.*/', '', $u);
        $u = preg_replace('/\?.*/', '', $u);

        return $u;
    }

    private function resolveFilePath(string $fileRef): ?string {
        $fileRef = str_replace('\\', '/', $fileRef);
        $fileRef = ltrim($fileRef, '/');

        // Normalise pour empêcher ../
        if (strpos($fileRef, '..') !== false) return null;

        $sourceRoot = rtrim(self::FILE_DIR, '/') . '/source/';
        return $sourceRoot . $fileRef;
    }

    /* =========================
     * Graph / reachability
     * ========================= */

    private function reachablePages(array $seeds, array $graph): array {
        $seen = [];
        $q = [];

        foreach ($seeds as $s) {
            if (!$s) continue;
            $seen[$s] = true;
            $q[] = $s;
        }

        while (!empty($q)) {
            $cur = array_shift($q);
            if (!isset($graph[$cur])) continue;
            foreach ($graph[$cur] as $to => $_) {
                if (!isset($seen[$to])) {
                    $seen[$to] = true;
                    $q[] = $to;
                }
            }
        }

        return $seen;
    }

    /* =========================
     * Markdown
     * ========================= */

    private function reportToMarkdown(array $r): string {
        $id = $r['meta']['id'] ?? '';
        $created = $r['meta']['created_at'] ?? '';
        $sum = $r['summary'] ?? [];

        $lines = [];
        $lines[] = '# Rapport SiteAudit';
        $lines[] = '';
        $lines[] = '- **ID** : ' . $id;
        $lines[] = '- **Date** : ' . $created;
        $lines[] = '- **PHP** : ' . ($r['meta']['php'] ?? '');
        $lines[] = '- **Zwii** : ' . ($r['meta']['zwii'] ?? '');
        $lines[] = '';
        $lines[] = '## Résumé';
        foreach ($sum as $k => $v) {
            $lines[] = '- **' . $k . '** : ' . $v;
        }

        $lines[] = '';
        $lines[] = '## Détails';

        $checks = $r['checks'] ?? [];
        foreach (['orphans' => 'Pages orphelines', 'brokenPageLinks' => 'Liens internes cassés', 'brokenFileLinks' => 'Fichiers manquants', 'unusedMedia' => 'Médias non utilisés', 'heavyFiles' => 'Fichiers trop lourds', 'hierarchyIssues' => 'Problèmes de hiérarchie', 'titleDuplicates' => 'Titres en doublon'] as $key => $title) {
            $items = $checks[$key] ?? [];
            $lines[] = '### ' . $title;
            if (empty($items)) {
                $lines[] = '_Rien à signaler._';
                $lines[] = '';
                continue;
            }
            foreach ($items as $it) {
                $lines[] = '- ' . json_encode($it, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    /* =========================
     * Utils
     * ========================= */

    /**
     * Construit une URL “publique” vers file/source en essayant de respecter le style de l’ancienne URL.
     */
    private function buildPublicFileUrl(string $oldUrl, string $rel): string {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $newPath = 'file/source/' . $rel;

        $u = trim(html_entity_decode($oldUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('#^site/file/source/#i', $u)) {
            $newPath = 'site/file/source/' . $rel;
        }

        // URL absolue ? on garde scheme/host/port
        $pu = @parse_url($u);
        if (is_array($pu) && isset($pu['scheme']) && isset($pu['host'])) {
            $port = isset($pu['port']) ? ':' . $pu['port'] : '';
            return $pu['scheme'] . '://' . $pu['host'] . $port . '/' . ltrim($newPath, '/');
        }

        // Commence par / ? on garde la racine
        if (str_starts_with($u, '/')) {
            return '/' . ltrim($newPath, '/');
        }

        return $newPath;
    }

    private function contentFilePath(string $locale, string $pageId): ?string {
        $locale = $locale ?: 'default';
        $pageId = (string)$pageId;
        if ($pageId === '') return null;

        $dataDir = rtrim(self::DATA_DIR, '/') . '/';
        $homeDir = $dataDir . 'home/';
        if (is_dir($homeDir)) {
            // ZwiiCampus : home/content/ ou home/<locale>/content/
            $contentDir = ($locale === 'default')
                ? ($homeDir . 'content/')
                : ($homeDir . $locale . '/content/');
        } else {
            // ZwiiCMS : content/ ou <locale>/content/
            $contentDir = ($locale === 'default')
                ? ($dataDir . 'content/')
                : ($dataDir . $locale . '/content/');
        }

        $p = $contentDir . $pageId . '.html';
        return is_file($p) ? $p : null;
    }


    
    /**
     * Remplace une URL dans un contenu, qu’il s’agisse d’une page du site d’accueil (home) ou d’une page d’espace.
     * $spaceId vide => pages CMS ; sinon => pages d’espace (/site/data/<spaceId>/content/<pageId>.html).
     */
    private function replaceInAnyContent(string $locale, string $pageId, string $spaceId, string $oldUrl, string $newUrl, ?string $backupActionId = null): array {
        $spaceId = trim((string)$spaceId);
        if ($spaceId !== '') {
            return $this->replaceInCourseContent($spaceId, (string)$pageId, $oldUrl, $newUrl, $backupActionId);
        }
        return $this->replaceInPageContent($locale, (string)$pageId, $oldUrl, $newUrl, $backupActionId);
    }

    /**
     * Remplace une URL dans une page d’espace (cours).
     */
    private function replaceInCourseContent(string $courseId, string $pageId, string $oldUrl, string $newUrl, ?string $backupActionId = null): array {
        $courseId = trim($courseId);
        $pageId = trim($pageId);

        if ($courseId === '' || $pageId === '' || $oldUrl === '' || $newUrl === '') {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'none'];
        }

        $contentPath = rtrim(self::DATA_DIR, '/') . '/' . $courseId . '/content/' . $pageId . '.html';
        if (!is_file($contentPath)) {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'course_file'];
        }

        if ($backupActionId) {
            $this->backupTouchedFile($backupActionId, $contentPath);
        }

        $html = (string)file_get_contents($contentPath);
        $variants = array_values(array_unique(array_filter([
            $oldUrl,
            html_entity_decode($oldUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($oldUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ])));

        $count = 0;
        foreach ($variants as $v) {
            $count += substr_count($html, $v);
            $html = str_replace($v, $newUrl, $html);
        }

        if ($count > 0) {
            $tmp = $contentPath . '.tmp';
            file_put_contents($tmp, $html, LOCK_EX);
            @rename($tmp, $contentPath);
            if ($backupActionId) {
                $this->updateBackupAfter($backupActionId, $contentPath);
            }
            return ['ok' => true, 'replacements' => $count, 'mode' => 'course_file'];
        }

        return ['ok' => false, 'replacements' => 0, 'mode' => 'course_file'];
    }


/**
     * Remplace une URL dans le contenu d’une page (fichier content/*.html, sinon page.json legacy).
     */
    private function replaceInPageContent(string $locale, string $pageId, string $oldUrl, string $newUrl, ?string $backupActionId = null): array {
        $locale = $locale ?: 'default';
        $pageId = (string)$pageId;
        if ($pageId === '' || $oldUrl === '' || $newUrl === '') {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'none'];
        }

        $oldUrl = (string)$oldUrl;
        $newUrl = (string)$newUrl;

        // 1) content/<pageId>.html
        $contentPath = $this->contentFilePath($locale, $pageId);
        if ($contentPath) {
            if ($backupActionId) {
                $this->backupTouchedFile($backupActionId, $contentPath);
            }
            $html = (string)file_get_contents($contentPath);
            $variants = array_values(array_unique(array_filter([
                $oldUrl,
                html_entity_decode($oldUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                htmlspecialchars($oldUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ])));

            $count = 0;
            foreach ($variants as $v) {
                $count += substr_count($html, $v);
                $html = str_replace($v, $newUrl, $html);
            }

            if ($count > 0) {
                $tmp = $contentPath . '.tmp';
                file_put_contents($tmp, $html, LOCK_EX);
                @rename($tmp, $contentPath);
                if ($backupActionId) {
                    $this->updateBackupAfter($backupActionId, $contentPath);
                }
                return ['ok' => true, 'replacements' => $count, 'mode' => 'file'];
            }

            return ['ok' => false, 'replacements' => 0, 'mode' => 'file'];
        }

        // 2) legacy : contenu stocké dans page.json
        $dataDir = rtrim(self::DATA_DIR, '/') . '/';
        $homeDir = $dataDir . 'home/';
        if (is_dir($homeDir)) {
            $pageJson = ($locale === 'default') ? ($homeDir . 'page.json') : ($homeDir . $locale . '/page.json');
        } else {
            $pageJson = ($locale === 'default') ? ($dataDir . 'page.json') : ($dataDir . $locale . '/page.json');
        }
        if (!is_file($pageJson)) {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'none'];
        }

        if ($backupActionId) {
            $this->backupTouchedFile($backupActionId, $pageJson);
        }

        $raw = json_decode(file_get_contents($pageJson), true);
        if (!is_array($raw)) {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'json'];
        }

        $rootKey = array_key_exists('page', $raw) ? 'page' : null;
        $pages = $rootKey ? ($raw['page'] ?? []) : $raw;
        if (!is_array($pages) || !isset($pages[$pageId]) || !is_array($pages[$pageId])) {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'json'];
        }

        $content = $pages[$pageId]['content'] ?? '';
        if (!is_string($content) || $content === '') {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'json'];
        }

        $count = substr_count($content, $oldUrl);
        if ($count <= 0) {
            return ['ok' => false, 'replacements' => 0, 'mode' => 'json'];
        }

        $content = str_replace($oldUrl, $newUrl, $content);
        $pages[$pageId]['content'] = $content;
        if ($rootKey) {
            $raw['page'] = $pages;
        } else {
            $raw = $pages;
        }

        $tmp = $pageJson . '.tmp';
        file_put_contents($tmp, json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
        @rename($tmp, $pageJson);
        if ($backupActionId) {
            $this->updateBackupAfter($backupActionId, $pageJson);
        }

        return ['ok' => true, 'replacements' => $count, 'mode' => 'json'];
    }


    /* =========================
     * Plans & actions (safe / gros sites)
     * ========================= */

    private function newId(string $prefix): string {
        return $prefix . substr(sha1(random_bytes(16)), 0, 12);
    }

    private function actionsDir(): string {
        return self::DATADIRECTORY . 'actions/';
    }

    private function plansDir(): string {
        return self::DATADIRECTORY . 'plans/';
    }

    private function backupsDir(): string {
        return self::DATADIRECTORY . 'backups/';
    }

    private function actionPath(string $id): string {
        return $this->actionsDir() . basename($id) . '.json';
    }

    private function planPath(string $token): string {
        return $this->plansDir() . basename($token) . '.json';
    }

    private function logAction(array $action): void {
        $id = (string)($action['id'] ?? '');
        if ($id === '') {
            $id = $this->newId('a_');
            $action['id'] = $id;
        }
        $this->writeAction($id, $action);
    }

    private function writeAction(string $id, array $action): void {
        $this->writeJsonAtomic($this->actionPath($id), $action);
    }

    private function readAction(string $id): ?array {
        $p = $this->actionPath($id);
        if (!is_file($p)) return null;
        $a = json_decode(file_get_contents($p), true);
        return is_array($a) ? $a : null;
    }

    private function listActionsForReport(string $rid, int $limit = 30): array {
        if ($rid === '') return [];
        $items = [];
        foreach (glob($this->actionsDir() . '*.json') ?: [] as $p) {
            $a = json_decode(file_get_contents($p), true);
            if (!is_array($a)) continue;
            if (($a['reportId'] ?? '') !== $rid) continue;
            $items[] = $a;
        }
        usort($items, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($items, 0, $limit);
    }

    private function writePlan(string $token, array $plan): void {
        $this->writeJsonAtomic($this->planPath($token), $plan);
    }

    private function readPlan(string $token): ?array {
        $p = $this->planPath($token);
        if (!is_file($p)) return null;
        $plan = json_decode(file_get_contents($p), true);
        return is_array($plan) ? $plan : null;
    }

    /**
     * Retourne le chemin relatif (depuis /site/file/source/) de destination en quarantaine.
     */
    private function quarantineDestRel(string $rel, string $quarantinedAt): string {
        $cfg = $this->getConfig();
        $qFolder = trim((string)($cfg['quarantineFolder'] ?? '_siteaudit'));
        if ($qFolder === '') $qFolder = '_siteaudit';

        $date = date('Y-m-d');
        $t = strtotime($quarantinedAt);
        if ($t !== false) {
            $date = date('Y-m-d', $t);
        }

        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        return $qFolder . '/_trash/' . $date . '/' . $rel;
    }

    /**
     * Déplace un fichier de /site/file/source/<rel> vers la quarantaine.
     * Retourne dest_rel (relatif à /site/file/source/) si OK, sinon ''.
     */
    private function moveSourceToQuarantineDetailed(string $rel): string {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $srcAbs = $this->resolveSourceAbs($rel);
        if (!$srcAbs || !is_file($srcAbs)) return '';

        $destRel = $this->quarantineDestRel($rel, date('c'));
        $destAbs = $this->resolveSourceAbs($destRel);
        if (!$destAbs) return '';

        $destDir = dirname($destAbs);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        return @rename($srcAbs, $destAbs) ? $destRel : '';
    }

    private function trashAction(string $mode, string $actionName): void {
        if ($this->getUser('permission', __CLASS__, $actionName) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $idx = (int)$this->getUrl(3);
        $returnPage = max(1, (int)($this->getUrl(4) ?: 1));

        $path = $this->reportPath($rid);
        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);
        if (!isset($all[$idx])) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Média introuvable')
            ]);
            return;
        }

        $it = $all[$idx];
        $rel = (string)($it['rel'] ?? '');
        $q = $it['quarantine'] ?? null;
        $qAt = (string)($q['at'] ?? ($it['quarantined_at'] ?? ''));
        $destRel = (string)($q['dest_rel'] ?? '');
        if ($destRel === '' && $qAt !== '') {
            $destRel = $this->quarantineDestRel($rel, $qAt);
        }

        $destAbs = $this->resolveSourceAbs($destRel);
        $srcAbs = $this->resolveSourceAbs($rel);

        if (!$destAbs || !is_file($destAbs)) {
            $this->addOutput([
                'redirect' => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Fichier en quarantaine introuvable')
            ]);
            return;
        }

        $ok = false;
        $actionId = $this->newId('a_');

        if ($mode === 'restore') {
            if ($srcAbs && !is_file($srcAbs)) {
                $srcDir = dirname($srcAbs);
                if (!is_dir($srcDir)) @mkdir($srcDir, 0755, true);
                $ok = @rename($destAbs, $srcAbs);
            }
            if ($ok) {
                unset($all[$idx]['quarantine']);
                unset($all[$idx]['quarantined_at']);
                $all[$idx]['restored_at'] = date('c');

                $this->logAction([
                    'id' => $actionId,
                    'type' => 'restore_media',
                    'reportId' => $rid,
                    'created_at' => date('c'),
                    'summary' => ['files' => 1, 'bytes' => (int)($it['size'] ?? 0)],
                    'details' => ['idx' => $idx, 'rel' => $rel, 'from' => $destRel]
                ]);
            }
        } elseif ($mode === 'purge') {
            $cfg = $this->getConfig();
            $retention = max(0, (int)($cfg['trashRetentionDays'] ?? 14));
            $canDelete = true;
            if ($retention > 0) {
                $t = strtotime($qAt);
                $canDelete = ($t !== false) ? ((time() - $t) >= ($retention * 86400)) : false;
            }
            if (!$canDelete) {
                $this->addOutput([
                    'redirect' => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . $returnPage,
                    'notification' => helper::translate('Rétention non écoulée — suppression refusée')
                ]);
                return;
            }

            $ok = @unlink($destAbs);
            if ($ok) {
                unset($all[$idx]['quarantine']);
                unset($all[$idx]['quarantined_at']);
                $all[$idx]['deleted_at'] = date('c');

                $this->logAction([
                    'id' => $actionId,
                    'type' => 'purge_trash',
                    'reportId' => $rid,
                    'created_at' => date('c'),
                    'summary' => ['files' => 1, 'bytes' => (int)($it['size'] ?? 0)],
                    'details' => ['idx' => $idx, 'rel' => $rel]
                ]);
            }
        }

        if (isset($report['checks']['unusedMediaAll'])) {
            $report['checks']['unusedMediaAll'] = $all;
        } else {
            $report['checks']['unusedMedia'] = $all;
        }
        $this->writeJsonAtomic($path, $report);

        $msg = ($mode === 'restore')
            ? ($ok ? helper::translate('Média restauré') : helper::translate('Restauration impossible'))
            : ($ok ? helper::translate('Suppression définitive effectuée') : helper::translate('Suppression impossible'));

        $this->addOutput([
            'redirect' => helper::baseUrl() . $this->getUrl(0) . '/trash/' . $rid . '/' . $returnPage,
            'notification' => $msg,
            'state' => $ok
        ]);
    }

    private function undoQuarantine(array $action): bool {
        $details = $action['details']['items'] ?? [];
        if (!is_array($details) || empty($details)) return false;

        $rid = (string)($action['reportId'] ?? '');
        $report = null;
        $all = null;
        $reportPath = '';
        if ($rid !== '') {
            $reportPath = $this->reportPath($rid);
            if (is_file($reportPath)) {
                $report = json_decode(file_get_contents($reportPath), true) ?: [];
                $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? null);
                if (!is_array($all)) $all = null;
            }
        }

        $restored = 0;
        $changedReport = false;

        foreach ($details as $it) {
            $rel = (string)($it['rel'] ?? '');
            $destRel = (string)($it['dest_rel'] ?? '');
            $idx = isset($it['idx']) ? (int)$it['idx'] : null;
            if ($rel === '' || $destRel === '') continue;

            $srcAbs = $this->resolveSourceAbs($rel);
            $destAbs = $this->resolveSourceAbs($destRel);
            if (!$srcAbs || !$destAbs) continue;
            if (!is_file($destAbs)) continue;
            if (is_file($srcAbs)) continue;

            $srcDir = dirname($srcAbs);
            if (!is_dir($srcDir)) @mkdir($srcDir, 0755, true);
            if (@rename($destAbs, $srcAbs)) {
                $restored++;

                // Mise à jour du rapport (si dispo)
                if (is_array($all)) {
                    if ($idx !== null && isset($all[$idx]) && (string)($all[$idx]['rel'] ?? '') === $rel) {
                        unset($all[$idx]['quarantine']);
                        unset($all[$idx]['quarantined_at']);
                        $all[$idx]['restored_at'] = date('c');
                        $changedReport = true;
                    } else {
                        // fallback : recherche par rel
                        foreach ($all as $k => $row) {
                            if ((string)($row['rel'] ?? '') === $rel) {
                                unset($all[$k]['quarantine']);
                                unset($all[$k]['quarantined_at']);
                                $all[$k]['restored_at'] = date('c');
                                $changedReport = true;
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ($restored > 0 && $changedReport && is_array($report) && is_array($all)) {
            if (isset($report['checks']['unusedMediaAll'])) {
                $report['checks']['unusedMediaAll'] = $all;
            } else {
                $report['checks']['unusedMedia'] = $all;
            }
            $this->writeJsonAtomic($reportPath, $report);
        }

        return ($restored > 0);
    }

    private function backupManifestPath(string $actionId): string {
        $dir = $this->backupsDir() . basename($actionId) . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir . 'manifest.json';
    }

    private function readBackupManifest(string $actionId): array {
        $p = $this->backupManifestPath($actionId);
        if (!is_file($p)) return ['files' => []];
        $m = json_decode(file_get_contents($p), true);
        return is_array($m) ? $m : ['files' => []];
    }

    private function writeBackupManifest(string $actionId, array $manifest): void {
        $this->writeJsonAtomic($this->backupManifestPath($actionId), $manifest);
    }

    private function backupTouchedFile(string $actionId, string $origPath): void {
        if ($actionId === '' || !is_file($origPath)) return;

        $manifest = $this->readBackupManifest($actionId);
        $files = $manifest['files'] ?? [];
        if (!is_array($files)) $files = [];

        // Déjà sauvegardé ?
        foreach ($files as $f) {
            if (($f['orig'] ?? '') === $origPath) return;
        }

        $key = substr(sha1($origPath), 0, 16);
        $dir = $this->backupsDir() . basename($actionId) . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $bak = $dir . $key . '.bak';

        @copy($origPath, $bak);

        $files[] = [
            'orig' => $origPath,
            'bak' => $bak,
            'before_sha1' => sha1_file($origPath) ?: '',
            'before_mtime' => (int)@filemtime($origPath)
        ];
        $manifest['files'] = $files;
        $this->writeBackupManifest($actionId, $manifest);
    }

    private function updateBackupAfter(string $actionId, string $origPath): void {
        $manifest = $this->readBackupManifest($actionId);
        $files = $manifest['files'] ?? [];
        if (!is_array($files)) return;

        $after = sha1_file($origPath) ?: '';

        foreach ($files as $i => $f) {
            if (($f['orig'] ?? '') === $origPath) {
                $files[$i]['after_sha1'] = $after;
            }
        }
        $manifest['files'] = $files;
        $this->writeBackupManifest($actionId, $manifest);
    }

    private function undoFixLinks(array $action): bool {
        $actionId = (string)($action['id'] ?? '');
        if ($actionId === '') return false;

        $manifest = $this->readBackupManifest($actionId);
        $files = $manifest['files'] ?? [];
        if (!is_array($files) || empty($files)) return false;

        // Contrôle conflits : le fichier doit être dans l’état “après correction”
        foreach ($files as $f) {
            $orig = (string)($f['orig'] ?? '');
            $after = (string)($f['after_sha1'] ?? '');
            if ($orig === '' || !is_file($orig)) return false;
            if ($after !== '' && (sha1_file($orig) ?: '') !== $after) {
                return false;
            }
        }

        $restored = 0;
        foreach ($files as $f) {
            $orig = (string)($f['orig'] ?? '');
            $bak = (string)($f['bak'] ?? '');
            if ($orig === '' || $bak === '' || !is_file($bak)) continue;
            if (@copy($bak, $orig)) {
                $restored++;
            }
        }
        return ($restored > 0);
    }

    private function mediaAction(string $mode, string $actionName): void {
        if ($this->getUser('permission', __CLASS__, $actionName) !== true) {
            $this->addOutput(['access' => false]);
            return;
        }

        $rid = (string)$this->getUrl(2);
        $absoluteIndex = (int)$this->getUrl(3);
        $returnPage = max(1, (int)($this->getUrl(4) ?: 1));
        $path = $this->reportPath($rid);

        if ($rid === '' || !is_file($path)) {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0),
                'notification' => helper::translate('Rapport introuvable')
            ]);
            return;
        }

        $report = json_decode(file_get_contents($path), true) ?: [];
        $all = $report['checks']['unusedMediaAll'] ?? ($report['checks']['unusedMedia'] ?? []);
        if (!isset($all[$absoluteIndex])) {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Média introuvable')
            ]);
            return;
        }

        $rel = (string)($all[$absoluteIndex]['rel'] ?? '');
        if ($rel === '') {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Chemin média invalide')
            ]);
            return;
        }

        $ok = false;
        $cfg = $this->getConfig();

        // Sécurité : suppression directe optionnelle
        if ($mode === 'delete' && empty($cfg['allowHardDelete'])) {
            $this->addOutput([
                'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
                'notification' => helper::translate('Suppression directe désactivée — utilise la corbeille (quarantaine)'),
            ]);
            return;
        }

        $actionId = $this->newId('a_');

        if ($mode === 'quarantine') {
            $destRel = $this->moveSourceToQuarantineDetailed($rel);
            $ok = ($destRel !== '');
            if ($ok) {
                $all[$absoluteIndex]['quarantine'] = ['at' => date('c'), 'dest_rel' => $destRel];
                $all[$absoluteIndex]['quarantined_at'] = $all[$absoluteIndex]['quarantine']['at'];

                $this->logAction([
                    'id' => $actionId,
                    'type' => 'quarantine_media',
                    'reportId' => $rid,
                    'created_at' => date('c'),
                    'summary' => ['files' => 1, 'bytes' => (int)($all[$absoluteIndex]['size'] ?? 0)],
                    'details' => ['items' => [[ 'idx' => $absoluteIndex, 'rel' => $rel, 'dest_rel' => $destRel, 'size' => (int)($all[$absoluteIndex]['size'] ?? 0) ]]]
                ]);
            }
        } elseif ($mode === 'delete') {
            $ok = $this->deleteSourceFile($rel);
            if ($ok) {
                $all[$absoluteIndex]['deleted_at'] = date('c');
                $this->logAction([
                    'id' => $actionId,
                    'type' => 'delete_media',
                    'reportId' => $rid,
                    'created_at' => date('c'),
                    'summary' => ['files' => 1, 'bytes' => (int)($all[$absoluteIndex]['size'] ?? 0)],
                    'details' => ['idx' => $absoluteIndex, 'rel' => $rel]
                ]);
            }
        }

        // Réinjecte dans le rapport
        if (isset($report['checks']['unusedMediaAll'])) {
            $report['checks']['unusedMediaAll'] = $all;
        } else {
            $report['checks']['unusedMedia'] = $all;
        }
        $this->writeJsonAtomic($path, $report);

        $msg = ($mode === 'delete')
            ? ($ok ? helper::translate('Média supprimé') : helper::translate('Suppression impossible'))
            : ($ok ? helper::translate('Média mis en quarantaine') : helper::translate('Quarantaine impossible'));

        $this->addOutput([
            'redirect'     => helper::baseUrl() . $this->getUrl(0) . '/media/' . $rid . '/' . $returnPage,
            'notification' => $msg,
            'state'        => $ok
        ]);
    }

    private function resolveSourceAbs(string $rel): ?string {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || strpos($rel, '..') !== false) return null;
        $sourceRoot = rtrim(self::FILE_DIR, '/') . '/source/';
        return $sourceRoot . $rel;
    }

    private function moveSourceToQuarantine(string $rel): bool {
        $cfg = $this->getConfig();
        $qFolder = trim((string)($cfg['quarantineFolder'] ?? '_siteaudit'));
        if ($qFolder === '') $qFolder = '_siteaudit';

        $src = $this->resolveSourceAbs($rel);
        if (!$src || !is_file($src)) return false;

        // Destination : /site/file/source/<qFolder>/_trash/YYYY-MM-DD/<rel>
        $date = date('Y-m-d');
        $destRoot = rtrim(self::FILE_DIR, '/') . '/source/' . $qFolder . '/_trash/' . $date . '/';
        $dest = $destRoot . ltrim($rel, '/');

        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }

        // Atomic move si possible
        return @rename($src, $dest);
    }

    private function deleteSourceFile(string $rel): bool {
        $src = $this->resolveSourceAbs($rel);
        if (!$src || !is_file($src)) return false;
        // Sécurité minimale
        $base = basename($src);
        if (in_array($base, ['.htaccess', 'index.php'], true)) return false;
        return @unlink($src);
    }

    private function rmTreeContent(string $dir, array $keepFiles = []): int {
        $deleted = 0;
        $dir = rtrim($dir, '/\\');

        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            $path = $file->getPathname();
            $base = $file->getBasename();

            if (in_array($base, $keepFiles, true)) {
                continue;
            }

            if ($file->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
                $deleted++;
            }
        }
        return $deleted;
    }
}

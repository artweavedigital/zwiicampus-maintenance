# SiteAudit Campus — Audit & Maintenance pour ZwiiCampus

**SiteAudit Campus** est une version du module d’audit pensée pour **ZwiiCampus** : elle analyse **les pages CMS standards** (site d’accueil) *et* **les espaces de formation** (cours), puis propose des actions sûres pour nettoyer et corriger.

— Détecter, comprendre, corriger… sans casser ton site.

---

## Fonctionnalités

### ✅ Audit CMS (site standard)
- Pages orphelines (hors menu, non atteignables)
- Liens internes cassés (pages)
- Liens vers fichiers cassés (images, PDF, ZIP, etc.)
- Titres en doublon
- Problèmes de hiérarchie (parent manquant)
- Médias non utilisés (dans `site/file/source/`)
- Fichiers trop lourds (seuil configurable)
- Export d’un rapport **Markdown**

### ✅ Audit Campus (ZwiiCampus)
- Inventaire des **espaces de formation**
  - espaces vides (0 page),
  - espaces sans auteur,
  - volume de contenu (taille),
  - nombre d’inscriptions.
- Analyse des **liens vers espaces**
  - `/courseId`
  - `/courseId/pageId`
- Analyse des **contenus des espaces** (pages + fichiers liés)
- Liste des **inscriptions** (apprenants / groupes / progression si dispo)

### ✅ Maintenance (safe)
- Réparation des **liens de fichiers cassés** quand une suggestion est trouvée
- Quarantaine des médias non utilisés (déplaçables, réversibles)
- Mode **simulation (dry-run)** avant exécution
- Centre d’actions : lots, curseurs, historique
- Annulation (**undo**) des actions (dans les limites décrites plus bas)

---

## Prérequis
- **ZwiiCampus 3.x**
- **PHP 8.1+** (8.3 OK)
- Droits : rôle **Éditeur** pour auditer, **Admin** pour réparer / quarantainer / purger

---

## Installation (import ZIP)
1. **Administration → Modules → Importer**
2. Sélectionne l’archive du module
3. Dans **Pages**, crée (ou choisis) une page dédiée et assigne le module **SiteAudit Campus**

### Structure attendue
Le module doit être installé comme ceci :
- `/module/siteaudit/siteaudit.php`
- `/module/siteaudit/enum.json`
- `/module/siteaudit/view/...`

> Si tu vois `/module/siteaudit/siteaudit/siteaudit.php`, ton ZIP contenait un dossier en trop : réimporte une archive « plate ».

---

## Démarrage rapide
1. Ouvre la page du module en étant connecté.
2. Clique **Lancer l’audit**.
3. Consulte le **rapport** (résumé + détails).
4. Utilise le **Centre d’actions** pour appliquer des corrections par lots (admin).

---

## Vues du module

### Tableau de bord
- Derniers rapports
- Accès rapide :
  - **Espaces**
  - **Inscriptions**
  - **Rapports**
  - **Centre d’actions**

### Espaces (Campus)
Inventaire des cours avec indicateurs :
- pages, taille, inscriptions
- auteur manquant
- espace vide

### Inscriptions (Campus)
Liste paginée (50 par page) :
- cours, utilisateur, groupe
- progression / dernière consultation (selon les données disponibles)

### Rapports
- Liste complète
- Ouverture d’un rapport
- Export Markdown

---

## Configuration

Accès : **Configuration du module** (éditeur)

Paramètres principaux :
- **Taille max fichier** (Mo) — seuil “fichier trop lourd”
- **Max éléments** — limite d’affichage par section du rapport

Scans :
- Pages orphelines
- Liens internes
- Liens vers fichiers
- Médias non utilisés
- Inclure fichiers de thème (CSS/JS/PHP…)
- Inclure fichiers de modules (optionnel)
- **Espaces Campus**
- **Inscriptions Campus**

Maintenance / sécurité :
- Dossiers ignorés (dans `site/file/source/`)  
  ex. `thumb,tmp,cache,backup,.git`
- Dossier de quarantaine (par défaut `_siteaudit`)
- Taille des lots (par défaut 25)
- Rétention corbeille (jours)
- Suppression définitive autorisée (désactivée par défaut)
- Unicité stricte des corrections (évite remplacements ambigus)

---

## Réparer les liens cassés (images, PDF, ZIP…)

### Ce qui est réparé automatiquement
Uniquement les **liens vers fichiers** quand le module trouve une **suggestion sûre** :
- même nom de fichier retrouvé ailleurs,
- ou nom “canonisé” (ex. `image-800x600.jpg` → `image.jpg`, `@2x`, `-scaled`, etc.).

Exemples de liens détectés :
- `file/source/mon-dossier/image.jpg`
- `site/file/source/mon-dossier/document.pdf`
- URLs absolues du site pointant vers `file/source/...`

### Réparation « 1 clic »
Dans un rapport :
- section **Fichiers manquants**
- si une entrée affiche une suggestion, le bouton **Réparer** applique le remplacement.

### Plusieurs options possibles
Quand plusieurs fichiers correspondent, tu peux choisir :
- bouton **Choisir une correction**
- sélection d’un chemin proposé
- application sur la page concernée

### Réparation par lots (admin)
Dans le **Centre d’actions** :
- **Réparer les liens sûrs (par lots)**  
  applique jusqu’à *N* corrections (N = taille de lot).

> Important : la réparation vise le bon stockage ZwiiCampus : pages du site d’accueil dans `site/data/home/...`, espaces dans `site/data/<courseId>/...`.

---

## Médias non utilisés — quarantaine, simulation, restauration

### Détection
Le module construit une “botte de foin” (haystack) depuis :
- contenus des pages CMS
- contenus des espaces Campus
- textes JSON
- (optionnel) fichiers du thème / modules

Puis il marque comme “utilisé” un média si son chemin relatif est retrouvé.

### Simulation (dry-run)
Avant de déplacer quoi que ce soit :
- sélectionne des médias
- clique **Simuler**
- vérifie la liste, le poids total, la taille de lot
- clique **Appliquer** pour exécuter par lots

### Quarantaine
Déplace le fichier depuis :
- `site/file/source/...`
vers :
- `site/file/source/_siteaudit/...` (par défaut)

### Corbeille
- liste des fichiers quarantainés
- **Restaurer**
- **Purger** (si autorisé + rétention atteinte)

---

## Centre d’actions (admin)
Un cockpit pour exécuter proprement :
- corrections de liens en lots
- quarantaines en lots
- curseurs (reprendre où tu en étais)
- historique des actions
- **Undo** (annulation) quand applicable

---

## Stockage des rapports
Les données du module sont stockées en flat-file :

- `site/data/siteaudit/reports/` — rapports JSON
- `site/data/siteaudit/reports/index.json` — index
- `site/data/siteaudit/actions/` — journal d’actions
- `site/data/siteaudit/plans/` — simulations
- `site/data/siteaudit/backups/` — sauvegardes (selon actions)

---

## Limites connues (choix assumés)
- Les **liens de pages internes cassés** sont détectés, mais **pas auto-réparés** (trop risqué sans validation).  
  Une réparation “assistée” est possible en évolution : proposer des pages candidates + confirmation.
- Un média peut être marqué “non utilisé” si :
  - il est injecté via JS dynamique,
  - il est référencé via CSS minifié/concaténé non scanné,
  - il est chargé depuis une URL générée.

Dans ce cas : utilise **la simulation** et vérifie avant quarantaine.

---

## Dépannage

### « Fatal error: Call to undefined method …loadCampusSpaces() »
Tu as un mélange de fichiers (ancienne version partielle).  
Solution :
1. Supprime `/module/siteaudit/`
2. Réimporte l’archive du module
3. Recharge la page

### Le module renvoie à l’accueil / accès refusé
- Vérifie que tu es connecté
- Vérifie le rôle (éditeur minimum)
- Vérifie que le module est placé sur une page (pas sur l’accueil système si Campus le restreint)

### Les réparations ne changent rien
Ca arrive si l’URL à remplacer n’existe pas telle quelle dans le contenu (encodage, variantes, URL absolue).  
Dans ce cas, relève l’URL exacte dans le HTML et relance un audit.

---

## Bonnes pratiques
- Lance un audit après une grosse migration (médias déplacés, refonte thème, import).
- Utilise **Simulation** avant quarantaine.
- Garde une fenêtre « Corbeille » pendant quelques jours après nettoyage.
- Évite la suppression définitive tant que tu n’as pas validé le site en navigation réelle.

---

## Crédit / licence
Module conçu pour l’écosystème **ZwiiCampus**, stockage flat-file, approche “safe-first”.

---


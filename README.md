# QCM Chill (`qtype_mcq_chill`)

Type de question Moodle « QCM Chill » : un questionnaire à choix multiples à
réponses multiples, volontairement dépouillé, pour rédiger vite et noter juste.

- Saisie rapide de l'énoncé et des réponses (une ligne de texte par réponse,
  affichée aux étudiants exactement comme elle a été saisie).
- Une case à cocher pour désigner chaque bonne réponse.
- Un sélecteur de points négatifs (« Aucun », -5 %, -10 %, -20 %, -25 %,
  -33,33 %, -50 %, -75 %, -100 %) appliqués à chaque mauvaise case cochée.
- Une option « tout ou rien » : la question ne rapporte des points que si
  toutes les bonnes réponses sont cochées et aucune mauvaise ; sinon le barème
  négatif s'applique.
- Une option « mélanger les réponses », activée par défaut. Elle ne figure pas
  dans la spécification initiale mais reste indispensable aux réponses à ordre
  fixe (« Aucune des réponses ci-dessus », listes ordonnées).

## Barème

Soit *C* le nombre de bonnes réponses de la question, *b* le nombre de bonnes
cases cochées, *m* le nombre de mauvaises cases cochées et *p* la pénalité
choisie (par exemple 0,5 pour « -50 % »). La fraction de la note obtenue est :

| Mode          | Réponse entièrement juste | Autre réponse            |
|---------------|---------------------------|--------------------------|
| Crédit partiel| 1                         | *b* / *C* − *m* × *p*    |
| Tout ou rien  | 1                         | 0 − *m* × *p*            |

Le résultat est borné entre -1 (moins la note de la question) et 1. Une
question peut donc retirer des points au total du test ; le carnet de notes,
lui, n'enregistre jamais une note inférieure à la note minimale du test.
Voir la section « Compatibilité » pour les comportements qui bornent la note
à zéro.

Exemple : question sur 2 points, deux bonnes réponses, pénalité -50 %.
Cocher une bonne réponse vaut 1 point ; cocher les deux bonnes et une
mauvaise vaut 1 point ; cocher deux mauvaises vaut -2 points.

Attention : avec « Aucun » et le crédit partiel, cocher toutes les réponses
rapporte la note maximale ; choisissez des points négatifs ou le mode « tout
ou rien » pour l'éviter.

Dans le rapport « Réponses » (analyse des réponses), chaque bonne case est
comptée pour sa part de crédit partiel (1 / *C*) et chaque mauvaise case pour
la pénalité, que le mode « tout ou rien » soit activé ou non.

## Compatibilité

- Moodle 4.0 à 5.2 (PHP 8.0 à 8.4), y compris l'arborescence `public/`
  introduite par Moodle 5.1.
- Les réponses des étudiants sont saisies, affichées et stockées par le
  moteur de questions du cœur : tous les comportements sont utilisables, avec
  les nuances suivantes.
  - Les notes négatives ne sont possibles qu'avec « Rétroaction a posteriori »
    et « Rétroaction immédiate » : les comportements « Interactif » et
    « Adaptatif » du cœur bornent la note d'une question à zéro, si bien que
    les points négatifs y réduisent seulement le crédit partiel et n'ont pas
    d'effet en mode « tout ou rien ».
  - Avec les variantes « avec degré de certitude », le barème de certitude du
    cœur remplace les points négatifs du plugin : toute réponse notée zéro ou
    moins reçoit 0, -2 ou -6 fois la note selon la certitude déclarée, et le
    crédit partiel est multiplié par 1, 2 ou 3.
- Import et export au format Moodle XML : les réponses sont des textes bruts ;
  une réponse importée au format HTML (par exemple depuis un QCM du cœur dont
  le type a été changé) est réduite à son texte et ses fichiers incorporés sont
  ignorés. Comme pour les types du cœur, une question sans deux réponses ou
  sans bonne réponse est signalée et l'import s'arrête après elle ; des points
  négatifs hors de l'intervalle [-1, 0] sont ramenés à la valeur admise la
  plus proche. Les autres formats (GIFT, Aiken, XHTML…) ne sont pas pris en
  charge : l'export GIFT d'une question QCM Chill ne produit qu'une ligne de
  commentaire, comme pour tout type de question tiers.
- Sauvegarde et restauration de cours, API de respect de la vie privée
  (les derniers réglages utilisés deviennent les valeurs par défaut de la
  question suivante, sous forme de préférences utilisateur déclarées).
- Non pris en charge : l'application mobile Moodle (aucun module mobile n'est
  fourni), les rétroactions par réponse et les indices (volontairement absents
  du formulaire pour rester « chill » ; ceux d'un fichier XML importé sont
  ignorés).

## Installation

1. Copier ce dossier dans `question/type/` sous le nom `mcq_chill`
   (`moodle/question/type/mcq_chill`, ou `moodle/public/question/type/mcq_chill`
   à partir de Moodle 5.1).
2. Se connecter en administrateur et terminer l'installation depuis
   *Administration du site > Notifications*.

La mise à niveau depuis la version 0.2 reconstruit la table
`qtype_mcq_chill_options` (ajout de la clé primaire `id`, conversion des
pourcentages en fractions) et retire les lignes que l'ancienne version laissait
dans `qtype_multichoice_options`.

## Utilisation

1. Dans une banque de questions ou un test, créer une question de type
   « QCM Chill ».
2. Saisir l'énoncé, les réponses, cocher les bonnes réponses.
3. Choisir les points négatifs par mauvaise case cochée et, si besoin, activer
   « Tout ou rien ».

Le texte d'une réponse est du texte brut : le HTML n'y est pas interprété,
mais les filtres du site (notation mathématique, multilangue) s'appliquent.

## Développement

- Code conforme au standard `moodle` de
  [moodle-cs](https://github.com/moodlehq/moodle-cs) (`phpcs --standard=moodle .`).
- Tests PHPUnit dans `tests/` : notation (`question_test.php`), type de
  question, formulaire, import/export XML (`questiontype_test.php`), parcours
  de tentative avec plusieurs comportements (`walkthrough_test.php`),
  sauvegarde et restauration (`backup_restore_test.php`), mise à niveau depuis
  la version 0.2 (`upgrade_test.php`), vie privée
  (`tests/privacy/provider_test.php`).

  ```bash
  php admin/tool/phpunit/cli/init.php
  vendor/bin/phpunit --testsuite qtype_mcq_chill_testsuite
  ```

- Intégration continue GitHub Actions (`.github/workflows/moodle-ci.yml`)
  avec [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) sur
  Moodle 4.0, 4.1, 4.5, 5.0, 5.1 et 5.2, sous PostgreSQL et MariaDB.
- La traduction française est livrée dans `lang/fr` ; lors d'une publication
  dans la base de plugins Moodle, elle a vocation à rejoindre AMOS.

## Licence

Les fichiers PHP portent l'en-tête GNU GPL v3 ou ultérieure, licence requise
pour un plugin Moodle (le code étend des classes du cœur, lui-même sous GPL).
Le fichier `LICENSE` du dépôt indique encore la licence MIT : il appartient à
l'auteur de l'aligner sur la GPL v3+ avant toute publication.

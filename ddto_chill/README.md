# Précaution

Ce plugin est le produit d'un test de performance en vibe coding des modèles Frontier IA en effort maximal. Il n'a pas vocation à être déployé sur des instances de production avant audit complet du code.

# Texte à trous Chill (`qtype_ddto_chill`)

Type de question Moodle « Texte à trous Chill » (drag-and-drop into text) :
saisir une phrase, cocher les mots qui deviennent des trous, ajouter des
distracteurs, noter comme le QCM Chill (points négatifs, tout-ou-rien).

Ce dossier est un **second plugin autonome**, livré dans le même dépôt que
`qtype_mcq_chill`. Il n'hérite pas de `qtype_ddwtos` du cœur (pas de points
négatifs, stockage figé). À déployer séparément : copier ce dossier sous
`question/type/ddto_chill` (le dossier **doit** s'appeler `ddto_chill`).

## Saisie (v1)

- Un textarea pour la phrase en texte brut. L'auteur ne tape **pas** les
  marqueurs `[[1]]` : une liste de mots détectés, chacun avec une case
  « en faire un trou », les génère à l'enregistrement.
- Compromis pragmatique : pas de sélection en direct dans le texte (v2).
- Liste de distracteurs avec ajout / suppression / réordonnancement sans
  rechargement (même composant AMD que le QCM Chill).
- Sélecteur de points négatifs (mêmes valeurs que le QCM), case « tout ou
  rien », case « mélanger ».

L'énoncé est stocké avec des placeholders `[[1]]`, `[[2]]`, … dans
`question.questiontext`. Le trou *n* attend le choix associé (le n-ième
enregistrement de `qtype_ddto_chill_choices`). v1 : un seul groupe / pool
pour tous les trous.

## Barème

Même formule que le QCM Chill, en comptant les trous correctement remplis
(*b*) et mal remplis (*m*) à la place des cases cochées. *C* est le nombre
de trous. Un trou laissé vide ne rapporte rien et n'est pas pénalisé.

| Mode          | Réponse entièrement juste | Autre réponse            |
|---------------|---------------------------|--------------------------|
| Crédit partiel| 1                         | *b* / *C* − *m* × *p*    |
| Tout ou rien  | 1                         | 0 − *m* × *p*            |

Résultat borné entre -1 et 1.

## Rendu étudiant

Glisser-déposer des puces dans les trous. **Repli v1** : chaque trou
contient toujours un `<select>` natif (clavier, lecteur d'écran, mobile).
En dessous de 768px la banque de puces est masquée : seuls les menus
déroulants restent. Documenté comme tel ; une accessibilité plus poussée
(annonces live, groupes multiples) est reportée en v2.

## Installation

Le dossier **doit** s'appeler `ddto_chill`.

1. Copier ce dossier dans `question/type/ddto_chill`
   (`moodle/question/type/ddto_chill`, ou
   `moodle/public/question/type/ddto_chill` à partir de Moodle 5.1).
2. Terminer depuis *Administration du site > Notifications*.
3. Purger les caches (y compris les caches JS) après une mise à jour du
   formulaire d'édition.

## Compatibilité

- Moodle 4.0 à 5.2 (PHP 8.0 à 8.4).
- Import / export Moodle XML : choix en texte brut ; rejet si 0 trou ou
  0 choix. Les autres formats (GIFT, Aiken, …) ne sont pas pris en charge.
- Sauvegarde et restauration de cours, préférences utilisateur (derniers
  réglages de notation).
- Non pris en charge : application mobile, rétroactions par choix, indices,
  groupes de trous multiples.

## Développement

- Code calqué sur `qtype_mcq_chill` (docblocs, licence GPL, `#[\Override]`).
- Tests PHPUnit dans `tests/` : notation (`question_test.php`), type de
  question et import/export XML (`questiontype_test.php`), vie privée.

  ```bash
  php admin/tool/phpunit/cli/init.php
  vendor/bin/phpunit --testsuite qtype_ddto_chill_testsuite
  ```

Les modules AMD (`amd/src/*.js`) doivent être reconstruits avec le
`grunt amd` du cœur Moodle (`npx grunt amd` depuis `ddto_chill/amd/` dans
une arborescence Moodle). Ne pas réécrire à la main les `.min.js`.

# Historique des versions

## 1.0.0-rc1 (2026-09-03)

Réécriture complète du plugin à la suite d'un audit (conformité à la
spécification, API Moodle, sécurité, style de code, compatibilité).

- Le type de question est désormais autonome (`question_type`) : les choix
  sont stockés dans `question_answers`, les réglages dans
  `qtype_mcq_chill_options` (table reconstruite avec une clé primaire `id`).
- Ajout de la classe de définition `question.php`, sans laquelle aucune
  tentative ne pouvait être créée, et d'un rendu (`renderer.php`) instanciable.
- Notation conforme à la spécification : part égale de la note pour chaque
  bonne case cochée, points négatifs (de -100 % à 0) pour chaque mauvaise case
  cochée, mode « tout ou rien » ; note bornée entre -100 % et 100 %.
- Formulaire d'édition minimal : texte des réponses, case « bonne réponse »,
  sélecteur de points négatifs, « tout ou rien », mélange des réponses.
- Sauvegarde et restauration de cours, import/export XML, respect de la vie
  privée (préférences utilisateur), icône, chaînes anglaises et françaises.
- Script de mise à niveau depuis la version 0.2 (conversion des pourcentages
  en fractions, nettoyage des lignes héritées de `qtype_multichoice`).
- Tests PHPUnit (notation, type de question, formulaire, parcours de
  tentative, sauvegarde/restauration, vie privée) et intégration continue
  GitHub Actions pour Moodle 4.0 à 5.2.

## 0.2

Version initiale expérimentale, héritant de `qtype_multichoice`.

# Historique des versions

## 1.0.0-rc3 (2026-09-18)

Passe d'ergonomie sur le formulaire d'édition, à la suite d'un audit UX
(guidage à la première utilisation, accessibilité, cohérence visuelle),
sans modifier le module JS existant ni relancer de build Moodle grunt :

- Ajout d'une consigne visible avant la liste des choix (nombre minimal de
  choix, texte brut sans HTML, lignes vides ignorées), pour un enseignant
  qui découvre le formulaire sans devoir ouvrir l'aide contextuelle.
- Ajout d'un texte d'exemple (`placeholder`) dans le champ de saisie de
  chaque choix.
- Libellés dédiés au plugin pour les boutons Monter / Descendre injectés
  par le module de réordonnancement (au lieu des chaînes cœur génériques),
  et libellé plus explicite pour le bouton Supprimer ("Supprimer cette
  réponse").
- Nouveau fichier `styles.css` : cibles tactiles d'au moins 44x44 px pour
  la poignée de glisser-déposer et les boutons Ajouter / Supprimer /
  Monter / Descendre, anneau de focus visible au clavier
  (`:focus-visible`), retour visuel net pendant un glisser-déposer ou un
  déplacement au clavier, et repli des contrôles sur une ligne dédiée en
  dessous de 768 px.
- Numéro de version incrémenté (`2026091801`) pour que l'upload ZIP
  déclenche une mise à jour si le plugin était déjà installé.

## 1.0.0-rc2 (2026-09-18)

- Formulaire d'édition : ajout / suppression / réordonnancement des réponses
  sans rechargement de page (glisser-déposer + boutons Monter / Descendre).
- Numéro de version Moodle incrémenté (`2026091800`) pour que l'upload ZIP
  déclenche bien une mise à jour si le plugin était déjà installé.
- CI GitHub Actions désactivée en automatique (lancement manuel uniquement).

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
- Formulaire d'édition minimal : texte des réponses (texte brut, affiché tel
  quel), case « bonne réponse », sélecteur de points négatifs, « tout ou
  rien », mélange des réponses.
- Sauvegarde et restauration de cours, import/export XML (format et fichiers
  des réponses conservés, avertissement en cas de données incomplètes ou hors
  bornes), respect de la vie privée (préférences utilisateur), icône, chaînes
  anglaises et françaises.
- Script de mise à niveau depuis la version 0.2 (conversion des pourcentages
  en fractions, nettoyage des lignes héritées de `qtype_multichoice`).
- Tests PHPUnit (notation, type de question, formulaire, parcours de
  tentative, sauvegarde/restauration, mise à niveau, vie privée).

## 0.2

Version initiale expérimentale, héritant de `qtype_multichoice`.

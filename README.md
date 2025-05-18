# Qtype MCQ Chill

Ce plugin Moodle ajoute un nouveau type de question "QCM Chill" avec une interface intuitive :
- Saisie rapide de la question et des réponses.
- Cases à cocher pour sélectionner les bonnes réponses.
- Sélecteur de points négatifs (de -100% à 0) pour chaque mauvaise case cochée.
- Option "tout ou rien" : la question ne rapporte des points que si toutes les bonnes réponses sont cochées et aucune mauvaise, sinon application du barème négatif.

## Installation
1. Copier ce dossier dans `moodle/question/type/` en le renommant `mcq_chill`
   (le chemin final doit être `moodle/question/type/mcq_chill`).
2. Aller dans l'administration de Moodle pour terminer l'installation.

## Utilisation
- Créez une nouvelle question de type "QCM Chill" dans un test.
- Saisissez l'énoncé, les réponses, cochez les bonnes, ajustez les points négatifs et l'option tout ou rien selon vos besoins.

## Développement
- Respecte le coding style Moodle.
- Compatible Moodle 4.x.

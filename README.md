# BAN Address Autocomplete — PrestaShop

Module PrestaShop qui ajoute une aide de saisie d'adresse basée sur l'**API Adresse** (Base Adresse Nationale) du gouvernement français.

Contrairement aux solutions basées sur Google Places, ce module :
- ne nécessite **aucune clé API** et **aucun compte** à créer
- est **gratuit**, sans quota payant
- n'envoie aucune donnée client à un tiers commercial (données ouvertes, hébergées par l'État français)
- pèse quelques Ko en JS vanilla, sans dépendance (pas de jQuery requis)

## Fonctionnalités

- Suggestions d'adresses en temps réel pendant la saisie
- Remplissage automatique du code postal et de la ville au clic sur une suggestion
- Compatible avec les formulaires chargés dynamiquement (tunnel de commande PrestaShop en AJAX), via un `MutationObserver`
- Personnalisable entièrement depuis le back-office :
  - activation/désactivation
  - nombre de caractères avant déclenchement
  - nombre de suggestions affichées
  - couleurs (fond, texte, survol, bordure) en **hexadécimal ou en variable CSS** de votre thème (`--color-beige`)
  - arrondi des bordures
  - ID HTML des champs du formulaire (adapté à n'importe quel thème custom)

## Prérequis

- PrestaShop 1.7 ou 8.x
- Adresses en France (l'API Adresse ne couvre que le territoire français)

## Installation

1. Téléchargez ou clonez ce dépôt
2. Compressez le dossier `banaddressautocomplete` en `.zip`
3. Dans le back-office : **Modules → Gestionnaire de modules → Téléverser un module**
4. Sélectionnez le `.zip` et installez

## Configuration

Rendez-vous dans **Modules → BAN Address Autocomplete → Configurer**.

### Général
- Activer/désactiver le module
- Nombre de caractères avant le déclenchement des suggestions
- Nombre de suggestions affichées

### Apparence
Chaque couleur accepte deux formats :
- un code hexadécimal, ex. `#f5f0e6`
- le nom d'une variable CSS déjà définie sur votre thème, ex. `--color-beige`

### Champs du formulaire
Chaque thème PrestaShop peut nommer différemment les champs du formulaire d'adresse. Pour trouver le bon ID :

1. Ouvrez la page contenant le formulaire d'adresse sur votre boutique
2. Clic droit sur le champ concerné → **Inspecter**
3. Repérez l'attribut `id` de la balise `<input>` dans le code affiché
4. Copiez sa valeur (sans le `#`) dans le champ correspondant du BO

Exemple : si vous voyez `id="field-address1"`, entrez `field-address1`.

## Comment ça marche

Le script écoute la saisie sur le champ adresse configuré et interroge `https://api-adresse.data.gouv.fr/search/` avec un debounce de 250ms. Au clic sur une suggestion, il remplit automatiquement les champs code postal et ville, puis déclenche un événement `change` pour rester compatible avec la validation du thème.

## Licence

À vous de choisir la licence adaptée à votre usage (MIT, GPL, etc.) en ajoutant un fichier `LICENSE` au dépôt.

## Auteur

LittleSpyrit

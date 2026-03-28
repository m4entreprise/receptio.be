# Plan de solidification Receptio

## Objectif

Passer du MVP Twilio validé à une V1 exploitable, fiable et vendable.

Le MVP a déjà validé les points les plus risqués :

- réception d'appels Twilio sur un vrai numéro
- exécution des webhooks voix côté Laravel
- routage initial vers le bon tenant via le numéro appelé
- enregistrement des appels et messages dans le dashboard
- protection des webhooks par signature Twilio

La suite consiste à transformer ce socle en produit robuste.

---

## Décisions d'architecture déjà actées

### Téléphonie

- modèle retenu : **Option A**
- un **compte Twilio global** pour toute l'application
- les secrets Twilio restent en **variables d'environnement**
- les numéros téléphoniques sont stockés en **base de données**
- le tenant est résolu à partir du numéro `To`

### Multi-tenant

- un `tenant` possède sa configuration agent
- un `tenant` possède un ou plusieurs `phone_numbers`
- un appel entrant doit toujours être rattaché au tenant correspondant au numéro appelé

### Produit

- priorité à la **fiabilité opérationnelle** avant d'ajouter plus d'IA
- priorité au **backoffice utile** avant les intégrations secondaires

---

## Principes de travail

- ne pas élargir le périmètre trop tôt
- solidifier d'abord le flux voix existant
- rendre chaque étape testable de bout en bout
- éviter les comportements implicites entre tenants
- rendre le dashboard actionnable, pas seulement décoratif
- documenter la recette et les critères d'acceptation à chaque incrément

---

## Vue d'ensemble de la roadmap

### Phase 1

**Stabiliser la téléphonie et les statuts d'appel**

### Phase 2

**Rendre le backoffice exploitable par une équipe**

### Phase 3

**Durcir réellement le multi-tenant**

### Phase 4

**Ajouter le traitement métier des messages et des rappels**

### Phase 5

**Ajouter transcription, résumé et automatisation**

### Phase 6

**Préparer le go-live commercial**

---

# Phase 1 — Stabiliser la téléphonie

## But

Faire en sorte que chaque appel ait un cycle de vie clair, traçable et fiable.

## Pourquoi cette phase est prioritaire

Aujourd'hui, le flux principal fonctionne, mais il faut rendre les états d'appel plus précis pour éviter un produit qui fonctionne uniquement en démonstration.

## Étape 1.1 — Normaliser les statuts d'appel

### Objectif

Définir une liste courte, cohérente et durable des statuts possibles.

### Statuts recommandés

- `received`
- `in_progress`
- `menu_offered`
- `transferring`
- `transferred`
- `voicemail_prompted`
- `voicemail_received`
- `completed`
- `failed`
- `after_hours`
- `no_answer`
- `busy`

### Travail à faire

- harmoniser les statuts utilisés dans `TwilioVoiceWebhookController`
- décider quand chaque statut est posé
- harmoniser les libellés du dashboard
- éviter les statuts ambigus ou redondants

### Livrable

- dictionnaire des statuts
- mapping UI cohérent
- historique d'appel plus lisible

### Critères d'acceptation

- chaque appel finit avec un statut compréhensible
- aucun statut ne dépend d'un comportement implicite

---

## Étape 1.2 — Ajouter les status callbacks Twilio

### Objectif

Recevoir les événements réels de Twilio pendant et après l'appel.

### Pourquoi

Le webhook entrant dit qu'un appel a commencé, mais pas toujours comment il s'est terminé.

### Travail à faire

- ajouter un endpoint de type `/webhooks/twilio/voice/status`
- configurer Twilio pour envoyer les changements d'état
- stocker dans `calls` les états réels Twilio
- enregistrer les informations utiles :
  - durée
  - fin d'appel
  - cause d'échec
  - answer / busy / no-answer / completed

### Fichiers probablement concernés

- `routes/web.php`
- `TwilioVoiceWebhookController` ou nouveau controller dédié
- `Call` model
- dashboard appels

### Livrable

- statut final fiable par appel
- meilleure compréhension des échecs de transfert

### Critères d'acceptation

- un appel terminé met bien à jour `ended_at`
- un appel non abouti a un statut identifiable
- le dashboard reflète l'état réel Twilio

---

## Étape 1.3 — Gérer les échecs de transfert

### Objectif

Éviter qu'un appel soit perdu si le numéro humain ne répond pas.

### Travail à faire

- définir le comportement si le `Dial` échoue
- ajouter un fallback vers messagerie
- enregistrer la cause de l'échec dans les métadonnées

### Cas à couvrir

- numéro occupé
- pas de réponse
- numéro invalide
- erreur Twilio

### Critères d'acceptation

- un transfert échoué ne casse pas l'expérience
- le caller peut toujours laisser un message
- le dashboard montre que le transfert a échoué avant fallback

---

## Étape 1.4 — Rendre les webhooks plus observables

### Objectif

Pouvoir comprendre rapidement ce qui s'est passé sans lire la base à la main.

### Travail à faire

- journaliser les étapes critiques
- journaliser les rejets de signature
- afficher les derniers événements Twilio dans le dashboard ou une vue diagnostic

### Critères d'acceptation

- un incident webhook peut être diagnostiqué rapidement
- un 403 de signature est identifiable
- un 500 applicatif est retraçable

---

# Phase 2 — Rendre le backoffice exploitable

## But

Transformer le dashboard en outil opérationnel.

## Étape 2.1 — Fiche appel détaillée

### Objectif

Afficher les détails utiles d'un appel individuel.

### Données à montrer

- date et heure
- statut
- numéro appelant
- numéro appelé
- durée
- résumé
- tenant
- CallSid
- recording URL si disponible

### Critères d'acceptation

- un opérateur peut comprendre un appel sans aller dans Twilio

---

## Étape 2.2 — Inbox messages exploitable

### Objectif

Permettre à une équipe de traiter les messages vocaux.

### Travail à faire

- ajouter des statuts métier sur `call_messages`
  - `new`
  - `in_progress`
  - `called_back`
  - `closed`
- permettre de marquer un message traité
- afficher qui l'a traité et quand

### Critères d'acceptation

- un message peut être suivi de bout en bout
- la boîte de réception n'est plus seulement informative

---

## Étape 2.3 — Lecture des enregistrements

### Objectif

Faciliter la consultation sans quitter Receptio.

### Travail à faire

- intégrer un lecteur audio simple dans la page messages ou appel
- afficher la durée d'enregistrement
- gérer les cas sans URL audio

### Critères d'acceptation

- un utilisateur peut écouter un message depuis le dashboard

---

## Étape 2.4 — Filtres et recherche

### Travail à faire

- filtrer par statut
- filtrer par date
- filtrer par tenant si vue admin globale plus tard
- rechercher par numéro appelant

### Critères d'acceptation

- retrouver un appel ou un message prend moins de quelques secondes

---

# Phase 3 — Durcir le multi-tenant

## But

Faire en sorte que le produit soit vraiment SaaS et sûr.

## Étape 3.1 — Supprimer les fallbacks globaux dangereux

### Objectif

Éviter qu'un appel tombe sur le mauvais tenant si le numéro n'est pas trouvé.

### Travail à faire

- auditer les fallback du type `Tenant::first()`
- les remplacer par des comportements explicites
- si le numéro n'est pas reconnu :
  - réponse générique contrôlée
  - log d'incident
  - pas de rattachement arbitraire à un autre tenant

### Critères d'acceptation

- aucun appel inconnu n'est attribué au mauvais tenant

---

## Étape 3.2 — Vérifier l'isolation UI et données

### Travail à faire

- vérifier toutes les requêtes dashboard
- s'assurer qu'un utilisateur ne voit que les données de son tenant
- préparer des tests automatiques de cloisonnement

### Critères d'acceptation

- un utilisateur du tenant A ne voit jamais les appels/messages du tenant B

---

## Étape 3.3 — Gérer plusieurs numéros par tenant

### Objectif

Permettre à un tenant d'avoir plusieurs lignes.

### Travail à faire

- préciser le concept de numéro principal
- gérer plusieurs `phone_numbers`
- éventuellement permettre un routage par service ou label plus tard

### Critères d'acceptation

- plusieurs numéros peuvent être reliés au même tenant sans comportement ambigu

---

# Phase 4 — Ajouter le traitement métier

## But

Faire de Receptio un outil de gestion de demandes, pas seulement un répondeur.

## Étape 4.1 — Workflow de rappel

### Travail à faire

- ajouter une action `rappeler plus tard`
- ajouter un champ `callback_due_at`
- permettre d'assigner un message à un utilisateur

### Critères d'acceptation

- chaque message peut avoir un état opérationnel clair

---

## Étape 4.2 — Notifications plus propres

### Travail à faire

- remplacer l'email brut par un template plus lisible
- inclure lien dashboard
- inclure lien enregistrement
- inclure tenant, heure, appelant

### Critères d'acceptation

- l'email est suffisant pour déclencher une action métier

---

## Étape 4.3 — Journal d'activité

### Travail à faire

- lister les événements importants :
  - appel reçu
  - transfert tenté
  - transfert échoué
  - message reçu
  - email envoyé
- fournir une chronologie claire

### Critères d'acceptation

- une équipe peut reconstituer l'historique d'un incident

---

# Phase 5 — Intelligence et automatisation

## But

Ajouter de la valeur sans fragiliser le socle.

## Étape 5.1 — Transcription des messages vocaux

### Travail à faire

- récupérer le média audio
- déclencher une transcription asynchrone
- stocker la transcription dans `call_messages` ou `calls`

### Critères d'acceptation

- un message vocal peut être lu sous forme de texte

---

## Étape 5.2 — Résumé automatique

### Travail à faire

- générer un résumé court du message
- identifier l'intention principale
- détecter l'urgence potentielle

### Critères d'acceptation

- le résumé aide réellement l'équipe à prioriser

---

## Étape 5.3 — FAQ et réponses assistées

### Travail à faire

- structurer `faq_content`
- préparer une base de réponses métier
- envisager un futur agent plus conversationnel

### Critères d'acceptation

- l'automatisation n'ajoute pas d'ambiguïté métier

---

# Phase 6 — Préparer le go-live commercial

## But

Passer d'un produit qui fonctionne à un produit que l'on peut vendre sereinement.

## Étape 6.1 — Onboarding tenant

### Travail à faire

- rendre la configuration initiale plus guidée
- vérifier :
  - numéro principal
  - email de notification
  - message d'accueil
  - horaires
  - numéro de transfert

### Critères d'acceptation

- un nouveau tenant peut être activé sans intervention manuelle forte

---

## Étape 6.2 — Checklist de readiness

### Travail à faire

- score de configuration plus strict
- contrôles bloquants avant activation
- affichage des points manquants

### Critères d'acceptation

- un tenant incomplet ne peut pas être considéré opérationnel par erreur

---

## Étape 6.3 — Supervision production

### Travail à faire

- logs d'erreurs centralisés
- alertes minimales sur échec webhook
- métriques clés :
  - appels reçus
  - transferts réussis
  - messages laissés
  - erreurs webhook

### Critères d'acceptation

- un incident prod est détecté rapidement

---

# Ordre d'implémentation recommandé

## Sprint 1

- normalisation des statuts d'appel
- status callbacks Twilio
- fallback sur échec de transfert

## Sprint 2

- fiche appel détaillée
- inbox messages avec statuts métier
- lecteur d'enregistrement

## Sprint 3

- suppression des fallbacks multi-tenant dangereux
- audit d'isolation des données
- support multi-numéros par tenant plus propre

## Sprint 4

- workflow de rappel
- notifications email améliorées
- journal d'activité

## Sprint 5

- transcription
- résumé automatique
- premières automatisations IA

---

# Recommandation immédiate

Le prochain chantier à implémenter en premier est :

## Status callbacks Twilio

### Pourquoi commencer par là

- faible coût de mise en œuvre
- forte valeur opérationnelle
- améliore immédiatement la fiabilité des appels
- prépare tout le reste du dashboard

### Ce que cela débloque ensuite

- statuts d'appel fiables
- durées réelles
- échecs de transfert visibles
- meilleure boîte de traitement

---

# Critères de succès de la solidification

Le produit peut être considéré comme solide quand :

- chaque appel a un cycle de vie clair
- un appel n'est jamais attribué au mauvais tenant
- un message vocal est traçable, écoutable et traitable
- un transfert échoué reste récupérable
- le dashboard sert à opérer, pas seulement à observer
- les incidents webhook sont compréhensibles
- les futures briques IA se branchent sur une base stable

---

# Ce qu'il ne faut pas faire tout de suite

- connecter trop d'outils externes
- ouvrir l'onboarding self-serve avancé trop tôt
- partir sur un agent vocal IA temps réel avant d'avoir fiabilisé la téléphonie
- ajouter de la complexité multi-compte Twilio avant d'avoir stabilisé l'Option A

---

# Décision recommandée pour la suite immédiate

## Étape suivante concrète

Implémenter :

- endpoint Twilio status callback
- mise à jour des `Call`
- affichage du statut final et de la durée réelle dans le dashboard

## Une fois cela terminé

Enchaîner sur :

- boîte de traitement des messages
- détail appel
- durcissement multi-tenant

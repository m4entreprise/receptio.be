# Plan de solidification Receptio

## Objectif

Passer du MVP Twilio valide a une V1 exploitable, fiable et vendable.

Le MVP a deja valide les points les plus risques :

- reception d'appels Twilio sur un vrai numero
- execution des webhooks voix cote Laravel
- routage initial vers le bon tenant via le numero appele
- enregistrement des appels et messages dans le dashboard
- protection des webhooks par signature Twilio

La suite consiste a transformer ce socle en produit robuste.

---

## Decisions d'architecture deja acte

### Telephonie

- modele retenu : **Option A**
- un **compte Twilio global** pour toute l'application
- les secrets Twilio restent en **variables d'environnement**
- les numeros telephoniques sont stockes en **base de donnees**
- le tenant est resolu a partir du numero `To`

### Multi-tenant

- un `tenant` possede sa configuration agent
- un `tenant` possede un ou plusieurs `phone_numbers`
- un appel entrant doit toujours etre rattache au tenant correspondant au numero appele

### Produit

- priorite a la **fiabilite operationnelle** avant d'ajouter plus d'IA
- priorite au **backoffice utile** avant les integrations secondaires

---

## Principes de travail

- ne pas elargir le perimetre trop tot
- solidifier d'abord le flux voix existant
- rendre chaque etape testable de bout en bout
- eviter les comportements implicites entre tenants
- rendre le dashboard actionnable, pas seulement decoratif
- documenter la recette et les criteres d'acceptation a chaque increment

---

## Vue d'ensemble de la roadmap

### Phase 1

**Stabiliser la telephonie et les statuts d'appel**

### Phase 2

**Rendre le backoffice exploitable par une equipe**

### Phase 3

**Durcir reellement le multi-tenant**

### Phase 4

**Ajouter le traitement metier des messages et des rappels**

### Phase 5

**Ajouter transcription, resume et automatisation**

### Phase 6

**Preparer le go-live commercial**

---

## Etat global actuel

### Ce qui est fait cote application

- phase 1 implementee cote Laravel et dashboard
- phase 2 implementee cote Laravel et dashboard
- phase 3 validee cote code et tests
- phase 4 engagee cote code avec workflow de rappel, notifications email enrichies et journal d'activite

### Ce qui reste a valider ou terminer

- configurer Twilio dans la console pour les status callbacks
- appliquer les migrations recentes en environnement local/staging/prod
- valider la recette Twilio reelle en environnement public

### Point de vigilance

- la base de code avance plus vite que la recette terrain Twilio
- les briques critiques sont maintenant couvertes par des tests executables, mais la validation production reste a faire

---

# Phase 1 - Stabiliser la telephonie

## But

Faire en sorte que chaque appel ait un cycle de vie clair, tracable et fiable.

## Etat de phase

- **terminee cote application**
- **partiellement terminee cote exploitation**

## Etape 1.1 - Normaliser les statuts d'appel

### Objectif

Definir une liste courte, coherente et durable des statuts possibles.

### Statuts recommandes

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

### Etat d'avancement

- **implemente cote application**
- mapping backend/UI coherent en place
- historique d'appel rendu plus lisible dans le dashboard

## Etape 1.2 - Ajouter les status callbacks Twilio

### Objectif

Recevoir les evenements reels de Twilio pendant et apres l'appel.

### Etat d'avancement

- **implemente cote application Laravel**
- route disponible : `/webhooks/twilio/voice/status`
- mise a jour des `Call` en fonction de `CallStatus` et `DialCallStatus`
- stockage de la duree reelle et des derniers evenements Twilio dans `metadata`
- affichage du statut final et de la duree reelle dans le dashboard

### Reste a faire cote Twilio

- ouvrir la configuration du numero Twilio actif dans la console
- conserver le webhook voix principal sur `POST /webhooks/twilio/voice/incoming`
- configurer les **Status Callbacks** du numero pour pointer vers `POST /webhooks/twilio/voice/status`
- demander les evenements utiles :
  - `initiated`
  - `ringing`
  - `answered`
  - `completed`
- verifier que l'URL publique utilisee par Twilio est bien celle exposee en production
- realiser un appel de test reel et verifier dans le dashboard :
  - statut final
  - duree
  - `ended_at`
  - cause d'echec si transfert non abouti

## Etape 1.3 - Gerer les echecs de transfert

### Objectif

Eviter qu'un appel soit perdu si le numero humain ne repond pas.

### Etat d'avancement

- **implemente cote application Laravel**
- fallback automatique vers messagerie sur `busy`, `no-answer`, `failed` et `canceled`
- stockage de `transfer_failure_status`, `transfer_failed_at` et `fallback_target` dans `metadata`
- restitution de la cause d'echec dans la liste des appels

## Etape 1.4 - Rendre les webhooks plus observables

### Objectif

Pouvoir comprendre rapidement ce qui s'est passe sans lire la base a la main.

### Etat d'avancement

- **observabilite principale implementee**
- journalisation structuree des etapes critiques sur `incoming`, `menu`, `status`, `recording` et `ping`
- journalisation des rejets de signature Twilio et du token manquant dans le middleware
- exposition des derniers evenements `status` Twilio dans le dashboard

---

# Phase 2 - Rendre le backoffice exploitable

## But

Transformer le dashboard en outil operationnel.

## Etat de phase

- **terminee cote application**
- **a valider completement cote migrations et tests**

## Etape 2.1 - Fiche appel detaillee

### Objectif

Afficher les details utiles d'un appel individuel.

### Etat d'avancement

- **implemente cote application**
- page detail appel disponible
- donnees affichees : date, statut, numeros, duree, resume, tenant, `CallSid`, evenements Twilio, message associe, URL audio si presente

## Etape 2.2 - Inbox messages exploitable

### Objectif

Permettre a une equipe de traiter les messages vocaux.

### Etat d'avancement

- **implemente cote application**
- statuts metier ajoutes sur `call_messages`
  - `new`
  - `in_progress`
  - `called_back`
  - `closed`
- actions de traitement disponibles dans le dashboard
- affichage de qui a traite et quand

## Etape 2.3 - Lecture des enregistrements

### Objectif

Faciliter la consultation sans quitter Receptio.

### Etat d'avancement

- **implemente cote application**
- lecteur audio simple integre dans les pages appel et messages
- affichage de la duree d'enregistrement
- gestion des cas sans URL audio

## Etape 2.4 - Filtres et recherche

### Objectif

Retrouver un appel ou un message rapidement.

### Etat d'avancement

- **implemente cote application**
- filtres par statut
- filtres par date
- recherche par numero / texte / `CallSid`
- pagination simple en place

### Reste a valider

- verification UX sur jeux de donnees reels
- tests automatiques executables localement une fois l'environnement PHP corrige

---

# Phase 3 - Durcir le multi-tenant

## But

Faire en sorte que le produit soit vraiment SaaS et sur.

## Etat de phase

- **en cours**
- **socle implemente**
- **validation incomplete**

## Etape 3.1 - Supprimer les fallbacks globaux dangereux

### Objectif

Eviter qu'un appel tombe sur le mauvais tenant si le numero n'est pas trouve.

### Etat d'avancement

- **largement implemente cote code**
- suppression des fallbacks du type `Tenant::first()` dans les webhooks Twilio critiques et dans le backoffice principal
- ajout d'une resolution explicite du tenant et du numero via un resolver partage
- si le numero n'est pas reconnu :
  - reponse generique controlee
  - log d'incident
  - pas de rattachement arbitraire a un autre tenant

### Reste a verifier

- relecture complete du code pour traquer tout fallback implicite residuel
- verification apres migration/deploiement sur environnement reel

## Etape 3.2 - Verifier l'isolation UI et donnees

### Etat d'avancement

- **partiellement implemente**
- les requetes du backoffice sont maintenant bornees au tenant de l'utilisateur
- des tests de cloisonnement ont ete ajoutes pour :
  - acces a la fiche appel
  - mise a jour d'un message
  - affichage de l'inbox
  - absence de fallback pour un utilisateur sans tenant

### Bloquant actuel

- la suite de tests Laravel ne peut pas etre executee localement tant que le driver SQLite/PDO manque dans l'environnement PHP

### Critere de sortie reel

- un utilisateur du tenant A ne voit jamais les appels/messages du tenant B
- la preuve doit exister en tests executables, pas seulement dans le code lu

## Etape 3.3 - Gerer plusieurs numeros par tenant

### Objectif

Permettre a un tenant d'avoir plusieurs lignes.

### Etat d'avancement

- **partiellement implemente**
- le schema et le routage supportent deja plusieurs `phone_numbers`
- un concept de numero principal a ete ajoute avec `is_primary`
- la configuration agent promeut explicitement un numero principal du tenant
- le dashboard numerotation affiche le numero principal

### Ce qui manque encore

- vraie UI de gestion de plusieurs lignes
- edition de labels / activation / desactivation
- support futur de routage par service ou par libelle

### Critere de sortie reel

- plusieurs numeros peuvent etre relies au meme tenant sans comportement ambigu
- le numero principal n'est jamais choisi implicitement par hasard

---

# Phase 4 - Ajouter le traitement metier

## But

Faire de Receptio un outil de gestion de demandes, pas seulement un repondeur.

## Etat de phase

- **en cours**
- **socle implemente et verifie**

## Etape 4.1 - Workflow de rappel

### Etat d'avancement

- **implemente cote application**
- action `rappeler plus tard` disponible dans l'inbox
- champ `callback_due_at` ajoute sur `call_messages`
- assignation d'un message a un utilisateur du tenant disponible
- statuts et rappels journalises dans l'activite

## Etape 4.2 - Notifications plus propres

### Etat d'avancement

- **implemente cote application**
- l'email brut Twilio est remplace par un vrai template HTML
- liens vers inbox et fiche appel inclus
- tenant, appelant, heure et enregistrement inclus

## Etape 4.3 - Journal d'activite

### Etat d'avancement

- **implemente cote application**
- evenements traces :
  - appel recu
  - transfert tente
  - transfert echoue
  - message recu
  - email envoye
  - assignation
  - rappel planifie
  - changement de statut
- chronologie visible dans la vue d'ensemble et sur la fiche appel

---

# Phase 5 - Intelligence et automatisation

## But

Ajouter de la valeur sans fragiliser le socle.

## Etat de phase

- **non commencee**

## Etape 5.1 - Transcription des messages vocaux

### Travail a faire

- recuperer le media audio
- declencher une transcription asynchrone
- stocker la transcription dans `call_messages` ou `calls`

## Etape 5.2 - Resume automatique

### Travail a faire

- generer un resume court du message
- identifier l'intention principale
- detecter l'urgence potentielle

## Etape 5.3 - FAQ et reponses assistees

### Travail a faire

- structurer `faq_content`
- preparer une base de reponses metier
- envisager un futur agent plus conversationnel

---

# Phase 6 - Preparer le go-live commercial

## But

Passer d'un produit qui fonctionne a un produit que l'on peut vendre sereinement.

## Etat de phase

- **non commencee**

## Etape 6.1 - Onboarding tenant

### Travail a faire

- rendre la configuration initiale plus guidee
- verifier :
  - numero principal
  - email de notification
  - message d'accueil
  - horaires
  - numero de transfert

## Etape 6.2 - Checklist de readiness

### Travail a faire

- score de configuration plus strict
- controles bloquants avant activation
- affichage des points manquants

## Etape 6.3 - Supervision production

### Travail a faire

- logs d'erreurs centralises
- alertes minimales sur echec webhook
- metriques cles :
  - appels recus
  - transferts reussis
  - messages laisses
  - erreurs webhook

---

## Ordre d'implementation recommande mis a jour

### Sprint 1

- normalisation des statuts d'appel
- status callbacks Twilio
- fallback sur echec de transfert

### Sprint 2

- fiche appel detaillee
- inbox messages avec statuts metier
- lecteur d'enregistrement
- filtres et recherche

### Sprint 3

- suppression des fallbacks multi-tenant dangereux
- debut de cloisonnement des requetes
- notion de numero principal

### Sprint 4

- terminer la phase 3 avec validation executable
- workflow de rappel
- notifications email ameliorees
- journal d'activite

### Sprint 5

- transcription
- resume automatique
- premieres automatisations IA

---

## Ou on en est maintenant

### Fait

- phase 1 : solide cote application
- phase 2 : solide cote application
- phase 3 : validee cote code et tests
- phase 4 : engagee et verifiee cote code

### Pas encore termine

- configuration Twilio dans la console
- application des migrations phase 4
- verification terrain Twilio des nouveaux flux

### Prochaine etape recommandee

Finaliser la mise en production de la phase 4 :

- appliquer les migrations recentes
- verifier les cas reels Twilio avec numeros connus et inconnus
- confirmer le rendu des emails en environnement reel

Une fois cela valide, enchainer sur :

- transcription
- resume automatique
- premieres automatisations IA

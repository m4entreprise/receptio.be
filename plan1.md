# Plan de réalisation — Receptio

Ce plan propose une trajectoire pragmatique pour lancer Receptio depuis zéro, valider le besoin rapidement, livrer un MVP exploitable, puis industrialiser la plateforme SaaS multi-tenant.

## 1. Objectif du plan

Construire Receptio en 3 temps :

- Valider le besoin et les cas d’usage réels auprès de PME pilotes
- Mettre en production un MVP simple, robuste et conforme
- Faire évoluer la solution vers une plateforme SaaS multi-tenant avec dashboard, analytics et intégrations

## 2. Hypothèses retenues

- Projet encore au stade idée / cadrage
- Démarrage greenfield
- Priorité au marché francophone belge
- Laravel retenu comme backend principal
- Téléphonie via Twilio par défaut, Telnyx gardé comme alternative
- MVP centré sur l’accueil, la qualification, la prise de message et le transfert humain

## 3. Priorités produit recommandées

### Phase A — Validation marché (2 à 3 semaines)

But : éviter de surconstruire avant d’avoir la preuve d’intérêt réel.

- Définir 3 ICP prioritaires
  - cabinets médicaux / paramédicaux
  - artisans / services locaux
  - PME B2B avec appels entrants fréquents
- Mener 10 à 15 entretiens clients
- Documenter les top motifs d’appels par segment
- Identifier les objections fortes
  - peur de parler à une IA
  - sensibilité RGPD
  - besoin de transfert humain rapide
  - qualité audio / compréhension d’accent
- Produire une offre pilote simple
  - 1 numéro
  - 1 agent
  - 1 langue FR
  - prise de message + transfert + FAQ

**Livrables**

- personas cibles
- top 20 intents d’appel
- FAQ type par segment
- grille tarifaire pilote
- script commercial / démo

### Phase B — MVP opérationnel (4 à 6 semaines)

But : traiter proprement les appels les plus fréquents avec faible complexité.

**Fonctions incluses**

- accueil vocal personnalisé par client
- détection du motif principal
- FAQ configurable
- prise de message structurée
- envoi email au responsable
- transfert vers humain
- gestion heures ouvrées / hors horaires
- log minimal des appels
- consentement / annonce automatisée

**Fonctions volontairement exclues au MVP**

- prise de rendez-vous temps réel
- CRM sync avancée
- dashboard complet self-service
- analytics poussées
- multi-langue complet
- voice cloning

## 4. Architecture cible recommandée

### 4.1 Architecture MVP

- **Entrée téléphonique** : Twilio number + webhooks
- **Backend** : Laravel API + queues + workers
- **Base de données** : MySQL
- **Cache / jobs** : Redis
- **STT** : Deepgram
- **LLM** : GPT-4o mini ou Claude Haiku selon coût / latence
- **TTS** : ElevenLabs ou OpenAI TTS
- **Emails** : SMTP transactionnel ou Mailgun
- **Observabilité** : logs structurés + monitoring erreurs + traces de latence
- **Hébergement** : VPS via Ploi

### 4.2 Principe de conception

Séparer dès le départ :

- orchestration d’appel
- moteur conversationnel
- actions métier
- configuration tenant
- observabilité / audit

Cela évitera de bloquer la montée en gamme vers le multi-tenant et les intégrations.

## 5. Roadmap technique recommandée

### Lot 0 — Fondations

- initialiser le projet Laravel
- mettre en place environnement local / staging / prod
- configurer secrets et variables d’environnement
- brancher MySQL, Redis, queues, mail
- créer la structure multi-tenant logique
- définir schéma de données initial

**Modèle de données initial**

- tenants
- phone_numbers
- agent_configs
- business_hours
- faqs
- calls
- call_messages
- call_actions
- contacts
- users

### Lot 1 — Téléphonie entrante

- provisionner numéros Twilio
- créer endpoint webhook entrant
- générer réponse d’accueil
- router selon tenant / numéro appelé
- tracer chaque appel avec identifiant unique

**Critère de sortie**

- un appel réel arrive, est identifié, et reçoit un accueil contextualisé

### Lot 2 — Conversation de base

- brancher STT
- envoyer le texte au moteur conversationnel
- injecter le contexte tenant
  - nom société
  - ton
  - FAQ
  - horaires
  - règles de transfert
- générer réponse courte et exploitable oralement
- synthétiser la réponse en audio

**Critère de sortie**

- l’appelant peut exprimer une demande simple et obtenir une réponse correcte

### Lot 3 — Actions métier MVP

- prise de message structurée
  - nom
  - téléphone
  - motif
  - urgence éventuelle
- transfert humain conditionnel
- comportement hors horaires
- envoi email récapitulatif
- journalisation des actions

**Critère de sortie**

- les demandes non résolues sont transmises proprement à un humain

### Lot 4 — Dashboard admin minimal

Créer un dashboard très simple, même limité au début.

- connexion sécurisée
- configuration agent
- édition FAQ
- horaires d’ouverture
- numéro de transfert
- liste des appels récents
- transcript et statut

**Critère de sortie**

- un client pilote peut modifier sa configuration sans intervention dev sur les points essentiels

### Lot 5 — Qualité, conformité, résilience

- message d’information RGPD
- stratégie de conservation / suppression
- chiffrement des transcripts sensibles
- retry jobs et alerting
- fallback si STT ou LLM indisponible
- réponse de secours si incompréhension répétée
- tests charge / latence

**Critère de sortie**

- le système reste acceptable même en cas d’échec partiel d’un provider tiers

## 6. V1 après MVP

À lancer seulement après validation avec 2 à 5 pilotes actifs.

- prise de rendez-vous Google Calendar / Calendly
- dashboard enrichi
- historique complet d’appels
- notifications SMS / email avancées
- détection d’urgence par règles + LLM
- premiers analytics

## 7. V2 / industrialisation SaaS

- vrai multi-tenant complet
- rôles / permissions
- billing / quotas / plans
- CRM integrations
- multi-numéro
- multi-langue FR / NL / EN
- API publique
- webhooks sortants
- analytics avancées
- white-label / partenaires

## 8. Risques majeurs et réponses recommandées

### Latence

- limiter la longueur des réponses vocales
- précharger le contexte utile
- préférer prompts courts et orientés action
- mettre des timeouts stricts par provider
- mesurer la latence par étape : STT, LLM, TTS, webhook total

### RGPD

- informer clairement l’appelant
- documenter base légale et conservation
- héberger en UE si possible
- prévoir suppression sur demande
- éviter de stocker plus que nécessaire

### Dépendance fournisseurs

- abstraction provider pour STT / TTS / LLM
- fallback provider à moyen terme
- alertes downtime
- journal d’erreurs orienté exploitation

### Acceptation utilisateur

- toujours proposer transfert humain
- ton conversationnel simple
- éviter les réponses trop longues ou “robotisées”
- commencer par cas d’usage à faible risque

## 9. Organisation conseillée du projet

### Piste équipe minimale

- 1 lead product / founder
- 1 développeur full-stack Laravel
- 1 support IA / voice orchestration selon besoin
- 1 designer léger ou template admin propre

### Rituels utiles

- revue hebdo pilotes
- suivi des appels ratés
- suivi des intents non compris
- backlog alimenté par verbatims réels

## 10. KPIs à suivre dès le début

- taux d’appels pris
- taux de transfert humain
- taux de résolution sans humain
- taux d’échec STT / incompréhension
- délai moyen de réponse
- conversion appel -> lead / RDV
- satisfaction client pilote

## 11. Plan 90 jours recommandé

### Jours 1 à 15

- entretiens clients
- segmentation
- architecture cible
- setup infra de base
- scripts d’appel et intents initiaux

### Jours 16 à 45

- téléphonie entrante
- moteur conversationnel MVP
- prise de message
- transfert
- notifications email
- premiers tests réels

### Jours 46 à 75

- dashboard minimal
- conformité
- monitoring
- amélioration prompts
- onboarding de 2 à 5 pilotes

### Jours 76 à 90

- stabilisation prod
- packaging offre Starter / Business
- cas clients pilotes
- priorisation V1 selon usage réel

## 12. Recommandations franches

- Commencer avec **un seul segment cible** pour éviter un agent trop générique
- Garder le MVP **très étroit** : accueil, qualification, message, transfert
- Ne pas lancer trop tôt la prise de RDV si la fiabilité conversationnelle n’est pas prouvée
- Prévoir dès maintenant une **abstraction providers** pour ne pas être prisonnier de Twilio / Deepgram / un seul LLM
- Faire du **dashboard minimal** plutôt qu’un gros back-office trop tôt
- Instrumenter la latence et les échecs dès le premier appel de test

## 13. Questions ouvertes à trancher rapidement

- Quel segment cible en premier ?
- MVP mono-tenant piloté manuellement ou multi-tenant léger dès le départ ?
- Souhaites-tu un agent temps réel streaming dès le MVP, ou une version plus simple webhook / tours de parole ?
- Veux-tu viser d’abord la Belgique FR uniquement, ou Belgique FR + NL dès la conception UX ?
- Souhaites-tu vendre en direct aux PME ou via partenaires / agences ?

## 14. Prochaine étape recommandée

Produire maintenant un **plan d’exécution opérationnel sprint par sprint** avec backlog priorisé, architecture initiale Laravel, modèle de données v1, et ordre exact de développement du MVP.

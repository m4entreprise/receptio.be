# Plan IA conversationnelle Receptio

## Vision

Faire de ReceptioAI un **receptionniste conversationnel** capable de comprendre une demande exprimee librement, de repondre aux questions frequentes, de qualifier le besoin, d'orienter, de transferer ou de prendre un message structure sans passer par un menu DTMF.

---

## Etat du depot au 2026-03-28

### Ce qui est deja implemente

- webhook Twilio entrant avec bascule `ConversationRelay` pendant les horaires d'ouverture
- endpoints internes Laravel proteges pour `bootstrap`, `turns`, `resolution`, `transfer` et `fallback`
- table `call_turns` et enrichissements conversationnels sur `calls` et `agent_configs`
- backoffice conversationnel avec transcript, resolution, motifs d'escalade et evenements de session
- sidecar Node minimal `ConversationRelay` avec state machine heuristique, persistance des tours et handoff Twilio
- exploitation initiale de `conversation_prompt` via directives simples d'escalade et de messagerie
- politiques tenant plus riches dans `conversation_prompt` avec regles structurees et messages dedies d'escalade ou de clarification
- grounding FAQ reel dans le sidecar avec support de FAQ en blocs question/reponse et candidats pertinents injectes au moteur LLM
- fallback vocal guide quand le sidecar demande une prise de message contextualisee
- fallback vocal guide aussi apres echec reel d'un transfert humain `Dial`
- fallback vocal post-transfert plus contextualise a partir du resume de transfert quand aucun message explicite n'est fourni
- moteur de decision OpenAI optionnel dans le sidecar avec fallback heuristique si non configure ou en erreur
- traces sidecar plus fiables avec distinction explicite entre decisions heuristiques, OpenAI et fallback heuristique
- timeout OpenAI configurable et metadonnees de decision persistables pour diagnostiquer latence, timeout et fallback
- statistiques conversationnelles de resolution exposees dans le dashboard backoffice
- analytics conversationnelles fines exposees dans le dashboard backoffice sur les tours, clarifications, usage OpenAI et escalades
- analytics de fiabilite LLM exposees dans le dashboard backoffice sur tentatives OpenAI, succes, timeouts, latence et fallbacks
- tests Laravel pour runtime conversationnel et backoffice
- tests Node locaux pour la politique conversationnelle et la session sidecar

### Ce qui reste ouvert

- grounding metier plus fin via `conversation_prompt` au-dela de directives simples
- evaluation et enrichissement des politiques d'escalade par tenant sur cas metier reels
- evaluation de la qualite LLM sur cas metier reels et durcissement complementaire de l'integration OpenAI
- analytics conversationnelles historiques et plus approfondies au-dela du dashboard de synthese
- tests de bout en bout sur appel Twilio reel

---

## Positionnement V1

### Cible produit

- **receptionniste hybride**
- **conversation tour par tour**
- **escalade prudente**
- **francais par defaut**

### Ce que la V1 doit faire

- accueillir un appelant sans menu "tapez 1"
- comprendre une demande simple
- repondre aux questions frequentes
- demander une clarification si necessaire
- transferer vers un humain si le contexte l'impose
- prendre un message structure si le transfert n'est pas possible
- produire un resume exploitable pour l'equipe

### Ce que la V1 ne vise pas encore

- barge-in complet
- full duplex speech-to-speech natif
- orchestration metier profonde type agenda, CRM ou ticketing

---

## Decisions d'architecture

### Repartition des responsabilites

#### Laravel reste la source de verite

- tenants
- `agent_configs`
- FAQ
- appels et messages
- journal d'activite
- workflows post-appel

#### Nouveau sidecar Node temps reel

- endpoint WebSocket `wss://.../conversationrelay`
- gestion de session conversationnelle
- etat des tours
- appels LLM
- decision reponse / clarification / transfert / fallback
- envoi des reponses texte a Twilio pour restitution vocale

### Choix telephonie

- **Twilio ConversationRelay** en V1
- ne pas partir sur `Gather speech`
- ne pas partir sur `Media Streams` bruts pour ce premier jalon
- reserver `Media Streams` et l'`OpenAI Realtime API` full speech-to-speech a une phase ulterieure

### Raison du choix

- ConversationRelay colle mieux a une V1 tour par tour
- l'implementation est plus simple a fiabiliser qu'un pipeline audio brut
- on garde une trajectoire vers plus de naturel plus tard, sans alourdir la V1

---

## Flux d'appel V1

1. le webhook entrant Laravel recoit l'appel et resout le tenant via le numero `To`
2. si hors horaires : on garde le flux actuel after-hours + message vocal
3. si horaires d'ouverture et `conversation_enabled = true` : Laravel retourne un TwiML qui ouvre la session ConversationRelay
4. le sidecar Node recupere le contexte d'appel et de tenant via Laravel
5. le caller parle librement
6. Twilio transmet le transcript du tour au sidecar
7. le sidecar decide entre :
   - repondre
   - clarifier
   - transferer
   - basculer vers prise de message
8. Laravel persiste les tours, le resume, la resolution et les evenements metier
9. si le sidecar ou le flux temps reel echoue : fallback immediat et controle vers le flux voicemail actuel

---

## Donnees et interfaces a ajouter

### Endpoints internes Laravel

Tous ces endpoints sont reserves au sidecar et proteges par un token serveur a serveur.

- `GET /internal/realtime/calls/{callSid}/bootstrap`
- `POST /internal/realtime/calls/{callSid}/turns`
- `POST /internal/realtime/calls/{callSid}/resolution`
- `POST /internal/realtime/calls/{callSid}/transfer`
- `POST /internal/realtime/calls/{callSid}/fallback`

### Table `call_turns`

- `id`
- `call_id`
- `speaker` : `caller`, `assistant`, `system`
- `text`
- `confidence` nullable
- `sequence`
- `meta` JSON nullable
- timestamps

### Enrichissements sur `calls`

- `channel` : `menu`, `voicemail`, `conversation_ai`
- `conversation_status`
- `resolution_type` : `answered`, `transferred`, `voicemail`, `failed`, `after_hours`
- `conversation_summary`
- `escalation_reason` nullable

### Enrichissements sur `agent_configs`

- `conversation_enabled` bool
- `conversation_prompt` text nullable
- `max_clarification_turns` int default `2`

### Donnees deja reutilisees

- `faq_content`
- `welcome_message`
- `after_hours_message`
- `transfer_phone_number`

---

## Regles de decision V1

### Reponses directes autorisees

L'IA peut repondre directement uniquement sur :

- FAQ
- informations simples
- orientation
- informations d'accueil

### Clarification

- au maximum `2` clarifications
- au-dela, l'IA doit escalader ou basculer vers un fallback

### Escalade prudente

Transfert ou sortie du mode conversationnel si :

- demande explicite d'un humain
- sujet sensible
- sujet hors perimetre
- incomprehension repetee
- politique metier du tenant

### Si transfert indisponible

- bascule vers prise de message guidee
- resume final exploitable pour l'equipe

### Invariants de sortie

Chaque appel conversationnel doit laisser :

- transcript exploitable
- resolution
- resume court
- raison d'escalade si applicable

---

## Roadmap

## Phase 1 - Runtime conversationnel V1

- [x] integrer Twilio ConversationRelay
- [x] ajouter le sidecar Node
- [x] bootstrap call/tenant
- [x] persistance des tours
- [x] gestion tour par tour
- [x] transfert et fallback

## Phase 2 - Grounding metier

 - [x] brancher reellement `faq_content`
- [~] ajouter prompts par tenant
- [~] regler les politiques d'escalade
- [x] guider la prise de message quand le transfert echoue
- [~] brancher un moteur LLM optionnel avec fallback heuristique

## Phase 3 - Backoffice conversationnel

- [x] vue transcript par appel
- [x] affichage de la resolution
- [x] motifs de transfert
- [x] incidents de conversation
- [x] statistiques de resolution

## Phase 4 - Optimisation produit

- suggestions de reponse plus fines
- tuning des prompts
- analytics conversationnelles
- evaluation d'un mode plus temps reel / plus interruptible

---

## Tests et criteres d'acceptation

### Cas V1 obligatoires

- un appelant demande une information simple de la FAQ et recoit une reponse correcte sans menu DTMF
- un appelant demande un humain et est transfere proprement
- un appelant peu comprehensible declenche au plus 2 clarifications puis une escalade ou un fallback
- hors horaires, le runtime conversationnel n'est pas utilise
- si le sidecar est indisponible, le systeme repasse vers le flux voicemail actuel
- un tenant A n'accede jamais aux prompts, configs ou transcripts du tenant B

### Verifications backoffice

- chaque appel conversationnel expose transcript, resolution, resume et raison d'escalade
- le journal d'activite montre les etapes conversationnelles majeures

### Verifications techniques

- tests d'integration Laravel pour bootstrap, persistance, resolution et fallback
- tests Node pour la state machine conversationnelle
- tests de bout en bout avec Twilio sur appel reel
- mesure explicite de latence tour par tour apres pause caller

---

## Hypotheses retenues

- le plan de solidification est termine
- la priorite produit devient la reception fluide
- la V1 conversationnelle est en francais
- la V1 reste tour par tour
- l'escalade prudente est la politique par defaut
- un sidecar Node est accepte dans l'architecture
- Twilio ConversationRelay est le point de depart recommande
- `Media Streams` et `OpenAI Realtime` full speech-to-speech restent des options de phase ulterieure

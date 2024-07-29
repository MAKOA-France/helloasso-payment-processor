# helloasso-payment-processor

Moved here: https://lab.civicrm.org/ryarnyah/helloasso-payment-processor/

L’extension Helloasso-payment-processor permet d'utilisé la passerelle de paiment HelloAsso avec civicrm

This is an [extension for CiviCRM](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/), licensed under [AGPL-3.0](LICENSE.txt).

## Getting Started
### Prérequis
- avoir un compte HelloAsso (https://www.helloasso.com/)
- et un compte sandBox HelloAsso (https://www.helloasso-sandbox.com).
Le sandbox vous permet de faire des tests.
- connaître ces 4 valeurs à chercher dans le back-office :
https://admin.helloasso.com
Mon compte > Intégration et API
- client id
- client secret
- Organization name : à trouver dans l’URL https://admin.helloasso.com/nom-de-l-organisation/integrations
- URL du Site : 
  - https://api.helloasso.com/v5 (live)
  - https://api.helloasso-sandbox.com/v5 (test)

![le logo de Framasoft](https://framasoft.org/nav/img/logo.png)

remarque : corriger dans l’extension les noms de ces deux champs : 
Organization name > nom de l’organisation
URL du site > URL du point d’appel HelloAsso

### Installation & Paramétrage
#### 1. Installer l'extension 
source de l’extension : gitlab ou shop officiel CiviCRM
Elle crée un type de passerelle de paiement HelloAsso (comparable dans le principe à Paypal, Stripe ou le SEPA)
#### 2. (Optionnel) Créer votre moyen de paiement “HelloAsso”.
Dans Administrer > CiviContribute > moyen de paiement 
Cela aidera votre comptable à retrouver les contributions provenant de HelloAsso.
#### 3. Créer votre passerelle de paiement 
Administrer > CiviContribute > Passerelle de paiement
- Cliquer sur "Ajouter une passerelle de paiement"
- Sélectionner “Type de passerelle de paiement” = “HelloAsso”

_Si vous ne le voyez pas c’est que le type passerelle HelloAsso n’est pas actif. Dans ce cas, il faut l’activer dans la table civicrm_payment_processor_type.is_active =1. Ou alors faire un cv flush ou drush cvapi sytem.flush afin que le managed soit correctement pris en compte)_

- Titre pour admin  : Mettre ce que vous voulez mais obligatoire
- Titre public : Mettre ce que vous voulez mais obligatoire
- Description : Mettre ce que vous voulez / optionnel
- Compte comptable : Le compte comptable que vous désirez, par défaut ‘Payment processor account’ pour déclarer que le paiement se fait via une passerelle
Mode de paiement : Mettre ce que vous voulez ou ce que vous avez déclaré à l’étape 2. 
- Passerelle par défaut : choix libre
- Type(s) de cartes de crédit acceptées : optionnel pas besoin de cocher les type de carte bancaire
- Renseignez vos paramètres de votre compte HelloAsso ainsi que ceux de votre compte de test de paiement (votre SandBox HelloAsso). Vous les trouvé sur vos compte HElloAsso dans la partie MonCompte > Intégrations et APi

Enregistrer. La passerelle est déclarée.

voici un visuel d’un exemple de configuration
IMAGE 
- Enregistrer votre passerelle de paiement.

Vous visualisez votre passerelle comme suit
IMAGE

Votre passerelle de paiement de production (ici d’[id_production] = 4) 
Votre passerelle de test  : Son [id_test] est ID de production - 1, donc ici se serait [id_test]= 3.( car la passerelle est créée avant celui de production et depuis les version de civicrm > 5.65 on ne voit plus l’id de test)
Vision de la version 5.65
IMAGE

#### Notifications URLCallBack
Important il faut paramétrer votre  Dans votre compte HelloAsso il faut dans “Mon Compte > Intégrations et API”, renseigner “Mon URL de callback” dans la partie Notifications qui sera : 
**En DRUPAL**
Pour votre production : https://[host]/civicrm/payment/ipn/[id_production]
Pour votre test (sandBox) : https://[host]/civicrm/payment/ipn/[id_test]
**EN WORDPRESS**
Pour votre production : https://[host]/wp-admin/admin.php?page=CiviCRM&q=civicrm/payment/ipn/[id_production]
Pour votre test (sandBox) : https://[host]/wp-admin/admin.php?page=CiviCRM&q=civicrm/payment/ipn/[id_test]
avec [host] : domaine de votre site exemple “monsite.com”

Exemple d’une sandbox chez HelloAsso
IMAGE

#### Visualisation des identifiants HelloAsso
Une fois qu’un payment via la passerelle HelloAsso est fait, vous pouvez visualiser les différents id de retour d’HelloAsso
- Le **checkoutIntentId** est visible au niveau de la contribution dans le champs **Id. de transaction**. 
L’**identifiant de transaction de paiement HelloAsso** est visible au niveau de la contribution dans la partie **“Information sur le paiement”** dans la colonne **“Id. de transaction”** et aussi dans **“Id. de transaction”** au niveau de la contribution après le checkoutIntentId 
- Le **numéro de commande HelloAsso** : Se trouve dans une l'entité HelloAssoMetadata champs helloasso_ref_cmd_id
Pour le visualisez vous pouvez soit : 
- Via l’Api 4 avec l’identifiant de contribution comme lien. Saisir https://[HOST]/civicrm/api4#/explorer/HelloAssoMetadata/get?select=%5B%22contribution_id%22,%22helloasso_ref_cmd_id%22%5D
Remplacer HOST par votre domaine
IMAGE
- Via un SearchKit à faire comme suit sur l’entité Contributions avec une liaison sur l’entité “Contribution Hello Asso Metadatas”
  - Ajouter un Where ou qui prends les test = 1 et test =0 sinon vous ne verrez jamais vos contributions de test (avec la sandBox)
		Cela donne quelque chose comme ça (ici on mis le point optionnel 2 de moyen de paiement “HelloAsso” en plus)
IMAGE

### Remarques
##### Lors des tests sur la sandBox
Utilisation des CB de test (https://docs.sips.worldline-solutions.com/fr/cartes-de-test.html.)
Ne pas utiliser les carte mastercard (cela ne fonctionne pas)

### Erreurs rencontrées
#### Could not get OAuth token for Payment Processor : 
Cela veut dire que votre clientId et/ou votre ClientSecret n’est pas valide
#### Type de passerelle de paiement n’est pas visible dans la page de création d’une nouvelle passerelle
Si vous ne le voyez pas c’est que le type passerelle HelloAsso n’est pas actif. Dans ce cas, il faut l’activer dans la table civicrm_payment_processor_type.is_active =1. Ou alors faire un cv flush ou drush cvapi sytem.flush afin que le managed soit correctement pris en compte)

### Support
Veuillez publier des rapports de bogues dans le suivi des issues de ce projet sur le Gitlab de CiviCRM :
https://lab.civicrm.org/extensions/helloasso/issues
Bien que nous fassions de notre mieux pour fournir un support bénévole pour cette extension, merci d’envisager de contribuer financièrement au soutien ou au développement de cette extension si vous le pouvez.

Support commercial disponible auprès de Makoa :
www.makoa.fr
contact@makoa.fr

Makoa est une société basée en France, à Paris. Notre mission est de simplifier et enrichir le service des associations à leurs adhérents et donateurs. Nous fournissons des services sur mesure (intégration, développement, assistance, hébergement) autour de CiviCRM depuis 1995.





## Known Issues

(* FIXME *)

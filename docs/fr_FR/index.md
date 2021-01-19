# Documentation plugin "Gigaset Elements"

Plugin permettant de se connecter au système "Gigaset Elements" pour récupérer les statuts et activer/désactiver l'alarme

Configuration du plugin
=======================

Après téléchargement du plugin, il faut l'activer, puis entrer les informations de connexion Gigaset
- user / mot de passe
- temps de latence de l'API Gigaset. 

>**IMPORTANT**
>
> l'API n'étant pas tres réactive (API REST publique), une latence de 5 secondes est recommandée.

Configuration des équipements
=============================

La création des équipements se fait de façon manuelle (pas encore de synchro automatique, a venir...)

Plusieurs types d'équipements sont disponibles

> **base** : la passerelle Gigaset, qui permettra de faire apparaitre le badge d'alarme
La base peut etre affichée avec un badge qui reprend le design mobile Gigaset, ou bien avec le widget standard Jeedom pour la compatibilité. 
L'équipement fonctionne de façon similaire au plugin "mode", mais remonte en plus des statuts sur le systeme Gigaset (picto vert/rouge, et message d'état).

> **camera** : permet d'afficher les flux sur les smart cameras (nécessite le plugin camera pour fonctionner)
 -- a venir --
 
> **capteur** : représente les capteurs "classiques" type porte/fenetre/mouvement
 -- a venir --
 
>**IMPORTANT**
>
>Attention lors du renommage d'un mode il faut absoluement revoir les scénarios/équipement qui utiliser l'ancien nom pour les passer sur le nouveau

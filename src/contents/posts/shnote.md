---
title: "shnote : prendre des notes depuis son terminal"
slug: "shnote-notes-depuis-terminal"
image: /images/creations/shnote.jpg
published: 2025-10-13
description: "shnote c'est ma solution en bash pour prendre des notes depuis son terminal"
tags: ["code", "creation", "bash", "script"]
category: Créations
licenseName: "Unlicensed"
author: shantee
sourceLink: "https:/zfx.fr"
draft: false
---

J’ai écrit **shnote** parce que j’avais besoin d’une façon **simple et rapide** de noter des idées directement dans le terminal.  
Pas de dépendances exotiques : c’est un **script Bash** léger, local, que l’on peut versionner et grepper facilement.

➡️ Dépôt : <https://github.com/shantee/shnote>

## Installation

```bash
git clone https://github.com/shantee/shnote.git
cd shnote
./install
```
Utilisation express

Ajouter une note :
```bash
shnote "Ma première note"
```
Lister toutes les notes :
```bash
shnote --list
```
Compter les notes :
```bash
shnote --count
```
Aide :
```bash
shnote --help
```
(Ces commandes sont décrites dans le README du dépôt.)

C'est du bash pur, ultra-léger.

    Notes locales et simples à chercher (grep, rg, etc.).

Astuce

J’utilise un alias pour aller encore plus vite :

alias n=shnote

## Exemple 
```bash
 n "Idée de billet pour le blog"
```

Si tu l’essaies, une étoile sur GitHub fait toujours plaisir 😉


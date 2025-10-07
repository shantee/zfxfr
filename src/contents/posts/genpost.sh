#!/usr/bin/env bash

# Génère 20 articles Markdown: post1.md ... post20.md
# Modifie la date ci-dessous si besoin.
PUBLISHED="2025-09-19"

for i in $(seq 1 20); do
  cat > "post${i}.md" <<EOF
---
title: "teste numéro #${i}"
slug: "post-${i}"
published: ${PUBLISHED}
description: "juste un test #${i}"
tags: ["astro", "yukina"]
draft: false
---

Un petit test d'article en  **Markdown**.
EOF
done

echo "Créés: post1.md ... post20.md"

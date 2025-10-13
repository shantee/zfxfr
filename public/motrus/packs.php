<?php
/**
 * packs.php — Listes de mots côté serveur (non exposées).
 * Ajoute/édite ici tes packs. Retourne toujours un tableau de listes (>= 2 mots).
 */

function packs_get(string $key): array {
  $defaut = [
    ["flûte","ocarina"],
    ["chaussette","slip","caleçon"],
    ["pantalon","pantacourt","short"],
    ["veste","doudoune"],
    ["jupe","robe"],
    ["banc","canapé", "chaise", "fauteuil"],
    ["savon","gel"],
    ["shampoing","après-shampoing"],
    ["brosse","peigne"],
    ["proche","famille","oncle","sœur","frêre"],
    ["couvert","assiette","cuillère","fourchette"],
    ["pluie","eau","humide"],
    ["proche","famille","oncle","sœur","frêre"],
      ["sable","dune","désert"],
        ["addition","multiplication","soustraction","division"],
          ["nature","écologie","environnement"],
  ["vin","bière","champagne"],
  ["caisse", "caissière"],
    ["mars","vénus","saturne"],
    ["pomme de terre","poireau"]


  ];

  $adultes = [
    // mets tes listes NSFW/politiques ici si tu le souhaites
    ["martini","mojito","spritz","margarita","cosmopolitan","caipirinha"],
    ["débat","meeting","sondage","scrutin","programme","coalition"],
  ];

  $packs = [
    'defaut'  => $defaut,
    'adultes' => $adultes,
  ];

  return $packs[$key] ?? $packs['defaut'];
}

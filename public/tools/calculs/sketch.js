// ===============================
// p5.js – Générateur d'exercices
// + interface pour choisir les tables
// + code factorisé (plus de copier/coller)
// + bouton régénérer + sauvegarde PNG
// ===============================

let operations = [];
let selectedTables = [4, 6, 7, 8];

// --- UI ---
let tablesInput, applyBtn, regenBtn, saveBtn, infoDiv;

function setup() {
  createCanvas(1200, 1750);
  setupUI();
  regeneratePage(); // première génération
}

function draw() {
  // rien: on redessine seulement quand on régénère
}

// -------------------------------
// UI
// -------------------------------
function setupUI() {
  tablesInput = createInput(selectedTables.join(","));
  tablesInput.position(12, 12);
  tablesInput.size(220);

  applyBtn = createButton("Appliquer");
  applyBtn.position(12 + 220 + 8, 12);
  applyBtn.mousePressed(() => {
    const parsed = parseTablesList(tablesInput.value());
    if (parsed.length > 0) {
      selectedTables = parsed;
      regeneratePage();
    } else {
      infoDiv.html("⚠️ Aucune table valide. Ex: 8,12,11,9");
    }
  });

  regenBtn = createButton("Régénérer");
  regenBtn.position(12 + 220 + 8 + 85, 12);
  regenBtn.mousePressed(regeneratePage);

  saveBtn = createButton("Sauver PNG");
  saveBtn.position(12 + 220 + 8 + 85 + 90, 12);
  saveBtn.mousePressed(() => {
    // nom de fichier pratique
    saveCanvas(`exercices_${selectedTables.join("-")}`, "png");
  });

  infoDiv = createDiv("");
  infoDiv.position(12, 40);

  // Enter dans le champ = appliquer
  tablesInput.elt.addEventListener("keydown", (e) => {
    if (e.key === "Enter") applyBtn.elt.click();
  });
}

function parseTablesList(str) {
  // accepte "8, 12 ; 11 9" etc.
  const parts = str.split(/[^0-9-]+/g).filter(Boolean);
  const nums = parts
    .map((s) => parseInt(s, 10))
    .filter((n) => Number.isFinite(n) && n >= 0 && n <= 99);

  // unique + tri
  const uniq = [...new Set(nums)].sort((a, b) => a - b);
  return uniq;
}

// -------------------------------
// Génération / rendu
// -------------------------------
function regeneratePage() {
  operations = [];

  // 1) Construire la liste d'opérations
  //    - multiplications: tables × (3..10)
  //    - additions: tables + (3..10)
  //    Tu peux facilement changer les bornes ici.
  const start = 3;
  const end = 10;

  addOperations(selectedTables, "x", start, end);
  addOperations(selectedTables, "+", start, end);

  // 2) Mélanger
  operations = shuffle(operations);

  // 3) Rendu
  renderPage();
}

function addOperations(tables, op, start, end) {
  for (const t of tables) {
    for (let i = start; i <= end; i++) {
      const result = (op === "x") ? (t * i) : (t + i);
      operations.push({
        label: `${t} ${op} ${i} =`,
        bin: result.toString(2),
        result
      });
    }
  }
}

function renderPage() {
  background(255);

  // Header
  fill(0);
  textStyle(NORMAL);
  textFont("Helvetica");
  textSize(32);
  text(`Tables : ${selectedTables.join(", ")}`, width / 4, 100);

  // UI info
  infoDiv.html(`Tables utilisées : <b>${selectedTables.join(", ")}</b> — ${operations.length} opérations`);

  // Layout colonnes
  const itemsPerColumn = 20;
  const lineSpacing = 70;
  const startX = 45;
  const startY = 240;
  const columnOffset = 400;

  let totalSum = 0;

  for (let i = 0; i < operations.length; i++) {
    const colIndex = floor(i / itemsPerColumn);
    const rowIndex = i % itemsPerColumn;

    const x = startX + colIndex * columnOffset;
    const y = startY + rowIndex * lineSpacing;

    // Opération (gros)
    textSize(32);
    fill(0);
    textStyle(NORMAL);
    text(operations[i].label, x, y);

    // binaire (gris, italique)
    push();
    fill(210);
    textSize(20);
    textFont("Helvetica");
    textStyle(ITALIC);
    text(operations[i].bin, x + 230, y - 10);
    pop();

    // ligne pointillée
    textSize(10);
    fill(155);
    text("  ....................................................................", x + 120, y);

    totalSum += operations[i].result;
  }

  // (Option) somme totale : je la laisse “cachée” comme toi.
  // Si tu veux l’afficher, décommente :
  /*
  textSize(18);
  fill(60);
  text(`Somme de tous les résultats = ${totalSum}`, 40, height - 60);
  */
}

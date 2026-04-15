<?php
header("Content-Type: application/json");

ini_set('display_errors', 1);
error_reporting(E_ALL);

set_error_handler(function($errno, $errstr) {
  echo json_encode(["ok" => false, "erro" => $errstr]);
  exit;
});

set_exception_handler(function($e) {
  echo json_encode(["ok" => false, "erro" => $e->getMessage()]);
  exit;
});

$db = new SQLite3("materias.sqlite");

$db->exec("PRAGMA journal_mode = WAL;");
$db->exec("PRAGMA busy_timeout = 5000;");

// ---------------- TABELAS ----------------
$db->exec("PRAGMA foreign_keys = ON");

$db->exec("CREATE TABLE IF NOT EXISTS materias (
id INTEGER PRIMARY KEY AUTOINCREMENT,
publishdate TEXT,
title TEXT,
content TEXT,
linhafina TEXT,
url TEXT,
image TEXT,
imgrights TEXT,
chapeu TEXT,
editoria TEXT,
path TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS autores (
id INTEGER PRIMARY KEY AUTOINCREMENT,
NomeAutor TEXT UNIQUE
)");

$db->exec("CREATE TABLE IF NOT EXISTS materia_autores (
materia_id INTEGER,
autor_id INTEGER
)");

$db->exec("CREATE TABLE IF NOT EXISTS editorias (
id INTEGER PRIMARY KEY AUTOINCREMENT,
NomeEditoria TEXT UNIQUE
)");

// seed editorias
$editorias = ["Política","Economia","Cotidiano","Esportes","Cultura","Ciência","Educação"];
foreach ($editorias as $e) {
  $stmt = $db->prepare("INSERT OR IGNORE INTO editorias (NomeEditoria) VALUES (?)");
  $stmt->bindValue(1, $e);
  $stmt->execute();
  $stmt->close(); // 🔥 importante
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents("php://input"), true) ?? [];

// ---------------- LISTAR MATERIAS ----------------
if ($path === "materias" && $method === "GET") {

  $res = $db->query("SELECT * FROM materias ORDER BY id DESC");
  $data = [];

  while ($m = $res->fetchArray(SQLITE3_ASSOC)) {

    $aut = $db->query("
      SELECT NomeAutor FROM autores a
      JOIN materia_autores ma ON ma.autor_id = a.id
      WHERE ma.materia_id = ".$m['id']
    );

    $lista = [];
    while ($a = $aut->fetchArray(SQLITE3_ASSOC)) {
      $lista[] = $a['NomeAutor'];
    }

    $m['autores'] = implode(", ", $lista);
    $data[] = $m;
  }

  echo json_encode($data);
  exit;
}

// ---------------- LISTAR EDITORIAS ----------------
if ($path === "editorias" && $method === "GET") {

  $res = $db->query("SELECT * FROM editorias ORDER BY NomeEditoria");
  $data = [];

  while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $data[] = $r;
  }

  echo json_encode($data);
  exit;
}

// ---------------- LISTAR AUTORES ----------------
if ($path === "autores" && $method === "GET") {

  $res = $db->query("SELECT * FROM autores ORDER BY NomeAutor");
  $data = [];

  while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $data[] = $r;
  }

  echo json_encode($data);
  exit;
}

// ---------------- CRIAR AUTOR ----------------
if ($path === "autores" && $method === "POST") {

  $stmt = $db->prepare("INSERT INTO autores (NomeAutor) VALUES (?)");
  $stmt->bindValue(1, $input['NomeAutor']);
  $stmt->execute();
 $stmt->close(); // 🔥 importante

  echo json_encode(["ok"=>true,"id"=>$db->lastInsertRowID()]);
  exit;
}

// ---------------- CRIAR MATERIA ----------------
if ($path === "materias" && $method === "POST") {

  $stmt = $db->prepare("INSERT INTO materias
  (publishdate, content, title, linhafina, url, image, imgrights, chapeu, editoria, path)
  VALUES (datetime('now'),?,?,?,?,?,?,?,?,?)");

  $stmt->bindValue(1, $input['content']);
  $stmt->bindValue(2, $input['title']);
  $stmt->bindValue(3, $input['linhafina']);
  $stmt->bindValue(4, $input['url']);
  $stmt->bindValue(5, $input['image']);
  $stmt->bindValue(6, $input['imgrights'] ?? 'Reprodução');
  $stmt->bindValue(7, $input['chapeu']);
  $stmt->bindValue(8, $input['editoria']);
  $stmt->bindValue(9, $input['path']);

  $stmt->execute();
 $stmt->close(); // 🔥 importante

  $materiaId = $db->lastInsertRowID();
  $authors = $input['authors'] ?? [];

  $db->exec("BEGIN");

  foreach ($authors as $a) {

    if (is_numeric($a)) {
      $autorId = $a;

    } else {

      $busca = $db->prepare("SELECT id FROM autores WHERE NomeAutor=?");
      $busca->bindValue(1, $a);
      $resBusca = $busca->execute();
      $row = $resBusca->fetchArray(SQLITE3_ASSOC);
      $busca->close(); // 🔥 ESSENCIAL

      if ($row) {
        $autorId = $row['id'];

      } else {
        $ins = $db->prepare("INSERT INTO autores (NomeAutor) VALUES (?)");
        $ins->bindValue(1, $a);
        $ins->execute();
        $autorId = $db->lastInsertRowID();
        $ins->close(); // 🔥 ESSENCIAL
      }
    }

    $rel = $db->prepare("INSERT INTO materia_autores VALUES (?,?)");
    $rel->bindValue(1, $materiaId);
    $rel->bindValue(2, $autorId);
    $rel->execute();
    $rel->close(); // 🔥 ESSENCIAL
  }

  $db->exec("COMMIT");

  echo json_encode(["ok"=>true]);
  exit;
}

// ---------------- EDITAR ----------------
if ($path === "materias" && $method === "PUT") {

  $stmt = $db->prepare("UPDATE materias SET
    title=?, content=?, editoria=?, chapeu=?, publishdate=?
    WHERE id=?");

  $stmt->bindValue(1, $input['title']);
  $stmt->bindValue(2, $input['content']);
  $stmt->bindValue(3, $input['editoria']);
  $stmt->bindValue(4, $input['chapeu']);
  $stmt->bindValue(5, $input['publishdate']);
  $stmt->bindValue(6, $input['id']);

  $stmt->execute();
 $stmt->close(); // 🔥 importante

  echo json_encode(["ok"=>true]);
  exit;
}

// ---------------- DELETE ----------------
if ($path === "delete" && $method === "POST") {

  $id = $input['id'];

  $stmt = $db->prepare("DELETE FROM materia_autores WHERE materia_id=?");
  $stmt->bindValue(1, $id);
  $stmt->execute();
  $stmt->close();

  $stmt = $db->prepare("DELETE FROM materias WHERE id=?");
  $stmt->bindValue(1, $id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(["ok"=>true]);
  exit;
}

// ---------------- EXCLUIR TABELA ----------------
if ($path === "tabela") {

  $db->exec("DELETE FROM materias");
  $db->exec("DELETE FROM materia_autores");

  echo json_encode(["ok"=>true]);
  exit;
}

// ---------------- EXPORT JSON ----------------
if ($path === "export_json") {

  // 1. matérias
  $resMaterias = $db->query("SELECT * FROM materias ORDER BY id DESC");
  $materias = [];

  while ($m = $resMaterias->fetchArray(SQLITE3_ASSOC)) {
    $materias[] = $m;
  }

  // 2. relações (igual Express)
  $resRel = $db->query("
    SELECT ma.materia_id, a.NomeAutor
    FROM materia_autores ma
    JOIN autores a ON a.id = ma.autor_id
  ");

  $mapa = [];

  while ($r = $resRel->fetchArray(SQLITE3_ASSOC)) {
    if (!isset($mapa[$r["materia_id"]])) {
      $mapa[$r["materia_id"]] = [];
    }
    $mapa[$r["materia_id"]][] = $r["NomeAutor"];
  }

  // 3. montar resultado (map estilo JS)
  $resultado = [];

  foreach ($materias as $m) {

    $autores = isset($mapa[$m["id"]])
      ? implode(", ", $mapa[$m["id"]])
      : "";

    // 🔥 DATA ISO PADRÃO (igual seu modelo)
    $dataISO = date("c");

    $item = [
      "id" => (int)$m["id"],
      "date" => $dataISO,
      "name" => null,
      "type" => "reportagem",
      "publishdate" => $dataISO,

      "title" => $m["title"],
      "content" => $m["content"],
      "linhafina" => $m["linhafina"],

      "abstract" => null,
      "path" => $m["path"] ?? "",

      "url" => $m["url"],
      "image" => $m["image"],
      "chapeu" => $m["chapeu"],

      "category" => "geral",
      "editoria" => strtolower($m["editoria"] ?? ""),

      "tema" => null,
      "destaque" => null,

      "imgrights" => $m["imgrights"] ?? "Reprodução",
      "imgdescript" => null,

      "location" => "São Paulo",

      "cortexto" => "black",
      "corfundo" => "standard",
      "fontetexto" => null,
      "entrelinhas" => "standard",
      "margin" => "standard",
      "padding" => "standard",
      "textalign" => null,
      "paragrafo" => 0,

      "comentarios" => null,
      "usrviews" => null,
      "maislidas" => null,
      "importante" => null,

      "author" => $autores
    ];

    $resultado[] = $item;
  }

  // 4. salvar arquivo
  file_put_contents(
    "conteudo.json",
    json_encode(
      ["conteudo" => $resultado],
      JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    )
  );

  echo json_encode(["ok" => true]);
  exit;
}

// ---------------- EXPORT CSV ----------------
if ($path === "export_csv") {

  $res = $db->query("SELECT * FROM materias ORDER BY id DESC");

  $csv = "id,title,autores,editoria,url,publishdate\n";

  while ($m = $res->fetchArray(SQLITE3_ASSOC)) {

    $aut = $db->query("
      SELECT NomeAutor FROM autores a
      JOIN materia_autores ma ON ma.autor_id = a.id
      WHERE ma.materia_id = ".$m['id']
    );

    $lista = [];
    while ($a = $aut->fetchArray(SQLITE3_ASSOC)) {
      $lista[] = $a['NomeAutor'];
    }

    $autores = implode(", ", $lista);

    $csv .= $m['id'].",\"".$m['title']."\",\"".$autores."\",\"".$m['editoria']."\",\"".$m['url']."\",\"".$m['publishdate']."\"\n";
  }

  file_put_contents("materias.csv", $csv);

  echo json_encode(["ok"=>true]);
  exit;
}

echo json_encode(["ok"=>false]);
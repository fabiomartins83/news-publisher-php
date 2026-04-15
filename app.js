function msg(t) {
  alert(t);
}

// ---------------- CARREGAR ----------------
async function carregar() {
  const res = await fetch("api.php?path=materias");
  const data = await res.json();

  document.getElementById("lista").innerHTML =
    data.map(m => `
      <div class="card">
        <b>${m.title}</b> - ${m.editoria || ""}<br>
        <small>${m.autores || ""}</small><br>
        ${(m.content || "").slice(0,120)}...

        <br><br>
        <button onclick="editar(${m.id})">Editar</button>
        <button onclick="deletar(${m.id})">Excluir</button>
      </div>
    `).join("");

  document.getElementById("materia_id").value = data.length + 1;
}

// ---------------- CRIAR ----------------
async function criar() {

  if (!title.value.trim() || !content.value.trim()) {
    msg("Preencha título e conteúdo");
    return;
  }

  const autores = author.value
    .split(/[;,]/)
    .map(a => a.trim())
    .filter(Boolean);

  try {

    const res = await fetch("api.php?path=materias", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({
        content: content.value,
        title: title.value,
        linhafina: linhafina.value,
        authors: autores,
        url: url.value,
        image: image.value,
        imgrights: imgrights.value,
        chapeu: chapeu.value,
        editoria: editoria.value,
        path: path.value
      })
    });

    const text = await res.text();

    let r;
    try {
      r = JSON.parse(text);
    } catch (e) {
      console.error("Resposta inválida:", text);
      msg("Erro no servidor (resposta inválida)");
      return;
    }

    if (r.ok) {
      limparFormulario();
      await carregar();
      msg("Matéria salva");
    } else {
      console.error(r);
      msg("Erro: " + (r.erro || "falha ao salvar"));
    }

  } catch (err) {
    console.error(err);
    msg("Erro de conexão");
  }
  focarConteudo();
}

// ---------------- EDITAR ----------------
let editId = null;

function editar(id) {
  editId = id;

  fetch("api.php?path=materias")
    .then(r => r.json())
    .then(data => {

      const m = data.find(x => x.id == id);

      edit_title.value = m.title;
      edit_content.value = m.content;
      edit_publishdate.value = m.publishdate;

      document.getElementById("modal").style.display = "flex";
    });
}

function fecharModal() {
  document.getElementById("modal").style.display = "none";
  focarConteudo();
}

async function salvarEdicao() {

  await fetch("api.php?path=materias", {
    method: "PUT",
    headers: {"Content-Type":"application/json"},
    body: JSON.stringify({
      id: editId,
      title: edit_title.value,
      content: edit_content.value,
      publishdate: edit_publishdate.value,
      editoria: "",
      chapeu: ""
    })
  });

  fecharModal();
  carregar();
  focarConteudo();
}

// ---------------- DELETE ----------------
async function deletar(id) {

  const confirmado = confirm("Deseja excluir?");

  if (confirmado) {
    await fetch("api.php?path=delete", {
      method: "POST",
      headers: {"Content-Type":"application/json"},
      body: JSON.stringify({ id })
    });

    await carregar();
  }

  focarConteudo(); // ✅ sempre executa
}
// ---------------- LIMPAR ----------------
function limparFormulario() {
  content.value = "";
  title.value = "";
  linhafina.value = "";
  author.value = "";
  url.value = "";
  image.value = "";
  imgrights.value = "";
  chapeu.value = "";
  editoria.value = "";
  path.value = "";
  focarConteudo();
}

// ---------------- EXPORT ----------------
async function exportarJSON() {
  await fetch("api.php?path=export_json");
  focarConteudo();
  msg("JSON gerado");
}

async function exportarCSV() {
  await fetch("api.php?path=export_csv");
  focarConteudo();
  msg("CSV gerado");
}

// ---------------- EXCLUIR TABELA ----------------
async function excluirTabela() {

  const confirmado = confirm("Excluir tudo?");

  if (confirmado) {
    await fetch("api.php?path=tabela");
    await carregar();
  }

  focarConteudo(); // ✅ sempre executa
}
// ---------------- EDITORIAS ----------------
async function carregarEditorias() {
  const res = await fetch("api.php?path=editorias");
  const data = await res.json();

  // 🔥 PROTEÇÃO IMPORTANTE
  if (!Array.isArray(data)) {
    console.error("Erro ao carregar editorias:", data);
    return;
  }

  const select = document.getElementById("editoria");
  select.innerHTML = "<option value=''>Não definido</option>";

  data.forEach(e => {
    const opt = document.createElement("option");
    opt.value = e.NomeEditoria;
    opt.textContent = e.NomeEditoria;
    select.appendChild(opt);
  });
}

// ---------------- INIT ----------------
function focarConteudo() {
  document.getElementById("content").focus();
}

// ---------------- INIT ----------------
window.onload = () => {
  carregarEditorias();
  carregar();
};
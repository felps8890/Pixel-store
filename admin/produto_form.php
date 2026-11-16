<?php
/** Painel: cadastrar ou editar produto (mesmo formulário). */
require dirname(__DIR__) . '/includes/init.php';
exigir_admin();

$id = (int) ($_GET['id'] ?? 0);
$editando = $id > 0;

$produto = [
    'nome' => '', 'descricao' => '', 'preco' => '', 'estoque' => '0',
    'categoria_id' => 0, 'imagem' => null, 'ativo' => 1,
];

if ($editando) {
    $stmt = db()->prepare('SELECT * FROM produtos WHERE id = ?');
    $stmt->execute([$id]);
    $encontrado = $stmt->fetch();
    if (!$encontrado) {
        flash('danger', 'Produto não encontrado.');
        redirecionar('admin/produtos.php');
    }
    $produto = $encontrado;
}

$erros = [];

if (eh_post()) {
    csrf_validar();

    $nome          = trim((string) ($_POST['nome'] ?? ''));
    $descricao     = trim((string) ($_POST['descricao'] ?? ''));
    $precoTexto    = trim((string) ($_POST['preco'] ?? ''));
    $estoqueTexto  = trim((string) ($_POST['estoque'] ?? ''));
    $categoriaId   = (int) ($_POST['categoria_id'] ?? 0);
    $novaCategoria = trim((string) ($_POST['nova_categoria'] ?? ''));
    $ativo         = isset($_POST['ativo']) ? 1 : 0;

    // Aceita "1.234,56", "1234,56" e "1234.56"
    if (strpos($precoTexto, ',') !== false) {
        $precoNormalizado = str_replace(',', '.', str_replace('.', '', $precoTexto));
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $precoTexto)) {
        $precoNormalizado = str_replace('.', '', $precoTexto); // "1.234" = mil duzentos e trinta e quatro
    } else {
        $precoNormalizado = $precoTexto;
    }

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 150) {
        $erros[] = 'O nome deve ter entre 3 e 150 caracteres.';
    }
    if ($descricao === '') {
        $erros[] = 'Informe a descrição.';
    }
    if (!is_numeric($precoNormalizado) || (float) $precoNormalizado <= 0 || (float) $precoNormalizado > 99999999.99) {
        $erros[] = 'Informe um preço válido, maior que zero.';
    }
    if (!ctype_digit($estoqueTexto) || (int) $estoqueTexto > 1000000) {
        $erros[] = 'O estoque deve ser um número inteiro, zero ou maior.';
    }
    if ($novaCategoria !== '' && mb_strlen($novaCategoria) > 60) {
        $erros[] = 'O nome da categoria deve ter até 60 caracteres.';
    }
    if ($novaCategoria === '') {
        $stmt = db()->prepare('SELECT 1 FROM categorias WHERE id = ?');
        $stmt->execute([$categoriaId]);
        if (!$stmt->fetchColumn()) {
            $erros[] = 'Selecione uma categoria.';
        }
    }

    // Upload de imagem (opcional)
    $novaImagem = null;
    $upload = $_FILES['imagem'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $erros[] = 'Falha no envio da imagem (verifique o tamanho do arquivo).';
        } elseif ($upload['size'] > 2 * 1024 * 1024) {
            $erros[] = 'A imagem deve ter no máximo 2 MB.';
        } else {
            // Valida pelo conteúdo real do arquivo, não pela extensão informada
            $info = @getimagesize($upload['tmp_name']);
            if (!$info || !isset($tipos[$info[2]])) {
                $erros[] = 'Envie uma imagem JPG, PNG, WEBP ou GIF.';
            } else {
                $novaImagem = 'upload_' . bin2hex(random_bytes(8)) . '.' . $tipos[$info[2]];
            }
        }
    }

    // Mantém o que foi digitado caso haja erro
    $produto = array_merge($produto, [
        'nome' => $nome, 'descricao' => $descricao, 'preco' => $precoTexto,
        'estoque' => $estoqueTexto, 'categoria_id' => $categoriaId, 'ativo' => $ativo,
    ]);

    if (!$erros) {
        $pdo = db();

        if ($novaCategoria !== '') {
            $stmt = $pdo->prepare('SELECT id FROM categorias WHERE nome = ?');
            $stmt->execute([$novaCategoria]);
            $categoriaId = (int) $stmt->fetchColumn();
            if ($categoriaId === 0) {
                $stmt = $pdo->prepare('INSERT INTO categorias (nome) VALUES (?)');
                $stmt->execute([$novaCategoria]);
                $categoriaId = (int) $pdo->lastInsertId();
            }
        }

        $imagemFinal = $produto['imagem'];
        if ($novaImagem !== null) {
            $destino = RAIZ . '/assets/img/produtos/' . $novaImagem;
            if (move_uploaded_file($upload['tmp_name'], $destino)) {
                // Remove a imagem anterior, se também tiver sido enviada pelo painel
                if ($imagemFinal && strpos((string) $imagemFinal, 'upload_') === 0) {
                    @unlink(RAIZ . '/assets/img/produtos/' . basename((string) $imagemFinal));
                }
                $imagemFinal = $novaImagem;
            } else {
                flash('warning', 'O produto foi salvo, mas não foi possível gravar a imagem (verifique a permissão da pasta assets/img/produtos).');
            }
        }

        $precoFinal = number_format((float) $precoNormalizado, 2, '.', '');
        $dados = [$categoriaId, $nome, $descricao, $precoFinal, (int) $estoqueTexto, $imagemFinal, $ativo];

        if ($editando) {
            $stmt = $pdo->prepare(
                'UPDATE produtos
                    SET categoria_id = ?, nome = ?, descricao = ?, preco = ?, estoque = ?, imagem = ?, ativo = ?
                  WHERE id = ?'
            );
            $stmt->execute(array_merge($dados, [$id]));
            flash('success', 'Produto atualizado.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO produtos (categoria_id, nome, descricao, preco, estoque, imagem, ativo)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute($dados);
            flash('success', 'Produto cadastrado.');
        }

        redirecionar('admin/produtos.php');
    }
}

$categorias = db()->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();

$precoCampo = $produto['preco'];
if (!$erros && $precoCampo !== '') {
    $precoCampo = number_format((float) $precoCampo, 2, ',', '');
}

$tituloPagina = $editando ? 'Editar produto' : 'Novo produto';
$abaAdmin = 'produtos';
require __DIR__ . '/_topo.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-4">
                <h2 class="h4 mb-4"><?= e($tituloPagina) ?></h2>

                <?php if ($erros): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" novalidate class="precisa-validar"
                      action="<?= e(url('admin/produto_form.php' . ($editando ? '?id=' . $id : ''))) ?>">
                    <?= csrf_campo() ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= e($produto['nome']) ?>"
                               minlength="3" maxlength="150" required>
                        <div class="invalid-feedback">Informe o nome (3 a 150 caracteres).</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="categoria_id" class="form-label">Categoria</label>
                            <select class="form-select" id="categoria_id" name="categoria_id">
                                <option value="0">Selecione...</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= (int) $cat['id'] ?>" <?= (int) $produto['categoria_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="nova_categoria" class="form-label">Ou criar nova categoria</label>
                            <input type="text" class="form-control" id="nova_categoria" name="nova_categoria" maxlength="60"
                                   value="<?= e($_POST['nova_categoria'] ?? '') ?>" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="preco" class="form-label">Preço</label>
                            <div class="input-group has-validation">
                                <span class="input-group-text">R$</span>
                                <input type="text" class="form-control" id="preco" name="preco" inputmode="decimal"
                                       value="<?= e($precoCampo) ?>" placeholder="0,00" pattern="^\d{1,3}(\.?\d{3})*([,.]\d{1,2})?$" required>
                                <div class="invalid-feedback">Informe um preço válido, ex.: 199,90.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="estoque" class="form-label">Estoque</label>
                            <input type="number" class="form-control" id="estoque" name="estoque" min="0" step="1"
                                   value="<?= e($produto['estoque']) ?>" required>
                            <div class="invalid-feedback">Informe um número inteiro, zero ou maior.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?= e($produto['descricao']) ?></textarea>
                        <div class="invalid-feedback">Informe a descrição.</div>
                    </div>

                    <div class="row g-3 mb-3 align-items-center">
                        <div class="col-auto">
                            <img src="<?= e(imagem_produto($produto['imagem'])) ?>" alt="Imagem atual" class="rounded border" width="96" height="96" style="object-fit:cover">
                        </div>
                        <div class="col">
                            <label for="imagem" class="form-label">Imagem do produto</label>
                            <input type="file" class="form-control" id="imagem" name="imagem" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div class="form-text">JPG, PNG, WEBP ou GIF, até 2 MB. <?= $editando ? 'Deixe em branco para manter a atual.' : '' ?></div>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" <?= (int) $produto['ativo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ativo">Produto ativo (visível na loja)</label>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-destaque" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/produtos.php')) ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require RAIZ . '/includes/footer.php'; ?>

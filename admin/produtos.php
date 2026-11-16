<?php
/** Painel: listagem de produtos + ativar/desativar + excluir. */
require dirname(__DIR__) . '/includes/init.php';
exigir_admin();

if (eh_post()) {
    csrf_validar();

    $acao = (string) ($_POST['acao'] ?? '');
    $id   = (int) ($_POST['id'] ?? 0);

    $stmt = db()->prepare('SELECT id, nome, imagem, ativo FROM produtos WHERE id = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        flash('danger', 'Produto não encontrado.');
        redirecionar('admin/produtos.php');
    }

    if ($acao === 'alternar') {
        $novo = (int) $produto['ativo'] ? 0 : 1;
        $stmt = db()->prepare('UPDATE produtos SET ativo = ? WHERE id = ?');
        $stmt->execute([$novo, $id]);
        flash('success', $produto['nome'] . ($novo ? ' foi reativado.' : ' foi desativado e saiu da vitrine.'));
    }

    if ($acao === 'excluir') {
        // Produto que já foi vendido não pode ser apagado (o histórico dos
        // pedidos depende dele). Nesse caso ele é apenas desativado.
        $stmt = db()->prepare('SELECT COUNT(*) FROM pedido_itens WHERE produto_id = ?');
        $stmt->execute([$id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $stmt = db()->prepare('UPDATE produtos SET ativo = 0 WHERE id = ?');
            $stmt->execute([$id]);
            flash('warning', $produto['nome'] . ' já aparece em pedidos, então foi desativado em vez de excluído.');
        } else {
            $stmt = db()->prepare('DELETE FROM produtos WHERE id = ?');
            $stmt->execute([$id]);

            // Apaga apenas imagens enviadas pelo painel
            $arquivo = (string) $produto['imagem'];
            if (strpos($arquivo, 'upload_') === 0) {
                @unlink(RAIZ . '/assets/img/produtos/' . basename($arquivo));
            }
            unset($_SESSION['carrinho'][$id]);
            flash('success', $produto['nome'] . ' foi excluído.');
        }
    }

    redirecionar('admin/produtos.php');
}

$produtos = db()->query(
    'SELECT p.id, p.nome, p.preco, p.estoque, p.imagem, p.ativo, c.nome AS categoria
       FROM produtos p
       JOIN categorias c ON c.id = p.categoria_id
      ORDER BY p.ativo DESC, p.nome'
)->fetchAll();

$tituloPagina = 'Produtos';
$abaAdmin = 'produtos';
require __DIR__ . '/_topo.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?= count($produtos) ?> produto(s) cadastrados</span>
    <a class="btn btn-destaque" href="<?= e(url('admin/produto_form.php')) ?>"><i class="bi bi-plus-lg"></i> Novo produto</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col" colspan="2">Produto</th>
                    <th scope="col">Categoria</th>
                    <th scope="col" class="text-end">Preço</th>
                    <th scope="col" class="text-center">Estoque</th>
                    <th scope="col" class="text-center">Situação</th>
                    <th scope="col" class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$produtos): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Nenhum produto cadastrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($produtos as $p): ?>
                <tr class="<?= (int) $p['ativo'] ? '' : 'table-secondary' ?>">
                    <td style="width:64px"><img src="<?= e(imagem_produto($p['imagem'])) ?>" alt="" class="rounded border miniatura"></td>
                    <td class="fw-semibold"><?= e($p['nome']) ?><div class="small text-muted fw-normal">#<?= (int) $p['id'] ?></div></td>
                    <td><?= e($p['categoria']) ?></td>
                    <td class="text-end text-nowrap"><?= e(preco($p['preco'])) ?></td>
                    <td class="text-center">
                        <?php $est = (int) $p['estoque']; ?>
                        <span class="badge <?= $est === 0 ? 'text-bg-danger' : ($est <= 5 ? 'text-bg-warning' : 'text-bg-light border') ?>"><?= $est ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge <?= (int) $p['ativo'] ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= (int) $p['ativo'] ? 'Ativo' : 'Desativado' ?></span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-dark" href="<?= e(url('admin/produto_form.php?id=' . (int) $p['id'])) ?>" title="Editar">
                            <i class="bi bi-pencil"></i><span class="visually-hidden">Editar <?= e($p['nome']) ?></span>
                        </a>
                        <form method="post" action="<?= e(url('admin/produtos.php')) ?>" class="d-inline">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="alternar">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= (int) $p['ativo'] ? 'Desativar' : 'Reativar' ?>">
                                <i class="bi <?= (int) $p['ativo'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                                <span class="visually-hidden"><?= (int) $p['ativo'] ? 'Desativar' : 'Reativar' ?> <?= e($p['nome']) ?></span>
                            </button>
                        </form>
                        <form method="post" action="<?= e(url('admin/produtos.php')) ?>" class="d-inline"
                              data-confirmar="Excluir &quot;<?= e($p['nome']) ?>&quot;? Esta ação não pode ser desfeita.">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Excluir">
                                <i class="bi bi-trash"></i><span class="visually-hidden">Excluir <?= e($p['nome']) ?></span>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require RAIZ . '/includes/footer.php'; ?>

<?php
/** Carrinho de compras gerenciado em $_SESSION. */
require __DIR__ . '/includes/init.php';

if (eh_post()) {
    csrf_validar();

    $acao      = (string) ($_POST['acao'] ?? '');
    $produtoId = (int) ($_POST['produto_id'] ?? 0);
    $qtd       = (int) ($_POST['quantidade'] ?? 1);

    if ($acao === 'limpar') {
        $_SESSION['carrinho'] = [];
        flash('info', 'Carrinho esvaziado.');
        redirecionar('carrinho.php');
    }

    if ($acao === 'remover') {
        unset($_SESSION['carrinho'][$produtoId]);
        flash('info', 'Item removido do carrinho.');
        redirecionar('carrinho.php');
    }

    if ($acao === 'adicionar' || $acao === 'atualizar') {
        // Estoque sempre conferido no servidor
        $stmt = db()->prepare('SELECT id, nome, estoque FROM produtos WHERE id = ? AND ativo = 1');
        $stmt->execute([$produtoId]);
        $produto = $stmt->fetch();

        if (!$produto) {
            flash('danger', 'Produto não encontrado.');
            redirecionar('carrinho.php');
        }

        $estoque = (int) $produto['estoque'];
        $atual   = (int) ($_SESSION['carrinho'][$produtoId] ?? 0);
        $nova    = $acao === 'adicionar' ? $atual + max(1, $qtd) : $qtd;

        if ($nova < 1) {
            unset($_SESSION['carrinho'][$produtoId]);
            flash('info', 'Item removido do carrinho.');
        } elseif ($estoque < 1) {
            unset($_SESSION['carrinho'][$produtoId]);
            flash('warning', $produto['nome'] . ' está esgotado.');
        } elseif ($nova > $estoque) {
            $_SESSION['carrinho'][$produtoId] = $estoque;
            flash('warning', "Só temos $estoque unidade(s) de {$produto['nome']} em estoque. A quantidade foi ajustada.");
        } else {
            $_SESSION['carrinho'][$produtoId] = $nova;
            flash('success', $acao === 'adicionar'
                ? $produto['nome'] . ' adicionado ao carrinho.'
                : 'Quantidade atualizada.');
        }
        redirecionar('carrinho.php');
    }

    redirecionar('carrinho.php');
}

$carrinho = carrinho_detalhado();
foreach ($carrinho['avisos'] as $aviso) {
    flash('warning', $aviso);
}

$tituloPagina = 'Carrinho';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4"><i class="bi bi-cart3"></i> Meu carrinho</h1>

<?php if (!$carrinho['itens']): ?>
    <div class="text-center py-5">
        <i class="bi bi-cart-x display-4 text-muted"></i>
        <p class="fs-5 mt-3">Seu carrinho está vazio.</p>
        <a class="btn btn-destaque" href="<?= e(url('index.php')) ?>">Ver produtos</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col" colspan="2">Produto</th>
                                <th scope="col" class="text-end">Preço</th>
                                <th scope="col" class="text-center">Quantidade</th>
                                <th scope="col" class="text-end">Subtotal</th>
                                <th scope="col"><span class="visually-hidden">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($carrinho['itens'] as $item): ?>
                            <tr>
                                <td style="width:72px">
                                    <img src="<?= e(imagem_produto($item['imagem'])) ?>" alt="" class="rounded border miniatura">
                                </td>
                                <td>
                                    <a class="text-decoration-none text-dark fw-semibold" href="<?= e(url('produto.php?id=' . $item['id'])) ?>"><?= e($item['nome']) ?></a>
                                    <div class="small text-muted"><?= $item['estoque'] ?> em estoque</div>
                                </td>
                                <td class="text-end text-nowrap"><?= e(preco($item['preco'])) ?></td>
                                <td class="text-center">
                                    <form method="post" action="<?= e(url('carrinho.php')) ?>" class="d-inline-flex gap-1">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="acao" value="atualizar">
                                        <input type="hidden" name="produto_id" value="<?= $item['id'] ?>">
                                        <input type="number" class="form-control form-control-sm text-center campo-qtd" name="quantidade"
                                               value="<?= $item['quantidade'] ?>" min="1" max="<?= $item['estoque'] ?>"
                                               aria-label="Quantidade de <?= e($item['nome']) ?>" required>
                                        <button class="btn btn-sm btn-outline-dark" type="submit" title="Atualizar quantidade" aria-label="Atualizar quantidade">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-end text-nowrap fw-semibold"><?= e(preco($item['subtotal'])) ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?= e(url('carrinho.php')) ?>">
                                        <?= csrf_campo() ?>
                                        <input type="hidden" name="acao" value="remover">
                                        <input type="hidden" name="produto_id" value="<?= $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="Remover item" aria-label="Remover <?= e($item['nome']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-3">
                <a class="btn btn-link px-0" href="<?= e(url('index.php')) ?>"><i class="bi bi-arrow-left"></i> Continuar comprando</a>
                <form method="post" action="<?= e(url('carrinho.php')) ?>" data-confirmar="Esvaziar todo o carrinho?">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="limpar">
                    <button class="btn btn-link text-danger px-0" type="submit">Esvaziar carrinho</button>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Resumo</h2>
                    <?php foreach ($carrinho['itens'] as $item): ?>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-truncate me-2"><?= $item['quantidade'] ?>x <?= e($item['nome']) ?></span>
                            <span class="text-nowrap"><?= e(preco($item['subtotal'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Subtotal</span><span><?= e(preco($carrinho['total'])) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Frete</span><span class="text-success">Grátis</span>
                    </div>
                    <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                        <span>Total</span><span class="preco"><?= e(preco($carrinho['total'])) ?></span>
                    </div>
                    <a class="btn btn-destaque btn-lg w-100" href="<?= e(url('checkout.php')) ?>">
                        Finalizar compra <i class="bi bi-arrow-right"></i>
                    </a>
                    <?php if (!usuario_logado()): ?>
                        <p class="small text-muted mt-2 mb-0">Você precisará entrar ou criar uma conta para concluir.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

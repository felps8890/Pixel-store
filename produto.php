<?php
/** Página individual do produto. */
require __DIR__ . '/includes/init.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT p.id, p.nome, p.descricao, p.preco, p.estoque, p.imagem, p.categoria_id, c.nome AS categoria
       FROM produtos p
       JOIN categorias c ON c.id = p.categoria_id
      WHERE p.id = ? AND p.ativo = 1'
);
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    $tituloPagina = 'Produto não encontrado';
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-5"><i class="bi bi-emoji-frown display-4 text-muted"></i>'
        . '<p class="fs-5 mt-3">Produto não encontrado.</p>'
        . '<a class="btn btn-dark" href="' . e(url('index.php')) . '">Voltar para a loja</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$estoque    = (int) $produto['estoque'];
$noCarrinho = (int) ($_SESSION['carrinho'][(int) $produto['id']] ?? 0);
$podeAdicionar = max(0, $estoque - $noCarrinho);

$tituloPagina = $produto['nome'];
require __DIR__ . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Loja</a></li>
        <li class="breadcrumb-item">
            <a href="<?= e(url('index.php?categoria=' . (int) $produto['categoria_id'])) ?>"><?= e($produto['categoria']) ?></a>
        </li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($produto['nome']) ?></li>
    </ol>
</nav>

<div class="row g-4 g-lg-5">
    <div class="col-md-6">
        <img src="<?= e(imagem_produto($produto['imagem'])) ?>" class="img-fluid rounded-4 border w-100 imagem-detalhe"
             alt="<?= e($produto['nome']) ?>">
    </div>

    <div class="col-md-6">
        <span class="badge text-bg-dark mb-2"><?= e($produto['categoria']) ?></span>
        <h1 class="h2"><?= e($produto['nome']) ?></h1>
        <p class="text-muted small">Código do produto: #<?= str_pad((string) $produto['id'], 5, '0', STR_PAD_LEFT) ?></p>

        <p class="preco display-6 fw-bold mb-1"><?= e(preco($produto['preco'])) ?></p>
        <p class="text-muted small">Preço unitário</p>

        <?php if ($estoque > 5): ?>
            <p class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> Em estoque (<?= $estoque ?> unidades)</p>
        <?php elseif ($estoque > 0): ?>
            <p class="text-warning-emphasis fw-semibold"><i class="bi bi-exclamation-circle-fill"></i> Últimas unidades: restam <?= $estoque ?></p>
        <?php else: ?>
            <p class="text-danger fw-semibold"><i class="bi bi-x-circle-fill"></i> Produto esgotado</p>
        <?php endif; ?>

        <?php if ($podeAdicionar > 0): ?>
            <form method="post" action="<?= e(url('carrinho.php')) ?>" class="d-flex flex-wrap align-items-end gap-3 my-4">
                <?= csrf_campo() ?>
                <input type="hidden" name="acao" value="adicionar">
                <input type="hidden" name="produto_id" value="<?= (int) $produto['id'] ?>">
                <div>
                    <label for="quantidade" class="form-label small mb-1">Quantidade</label>
                    <div class="input-group seletor-qtd">
                        <button class="btn btn-outline-secondary" type="button" data-qtd="-1" aria-label="Diminuir">&minus;</button>
                        <input type="number" class="form-control text-center" id="quantidade" name="quantidade"
                               value="1" min="1" max="<?= $podeAdicionar ?>" required>
                        <button class="btn btn-outline-secondary" type="button" data-qtd="1" aria-label="Aumentar">+</button>
                    </div>
                </div>
                <button class="btn btn-destaque btn-lg" type="submit"><i class="bi bi-cart-plus"></i> Adicionar ao carrinho</button>
            </form>
            <?php if ($noCarrinho > 0): ?>
                <p class="small text-muted">Você já tem <?= $noCarrinho ?> no carrinho.</p>
            <?php endif; ?>
        <?php elseif ($estoque > 0): ?>
            <div class="alert alert-info my-4">
                Você já colocou todas as unidades disponíveis no carrinho.
                <a href="<?= e(url('carrinho.php')) ?>" class="alert-link">Ver carrinho</a>
            </div>
        <?php else: ?>
            <button class="btn btn-outline-secondary btn-lg my-4" type="button" disabled>Indisponível no momento</button>
        <?php endif; ?>

        <h2 class="h5 mt-3">Descrição</h2>
        <p class="descricao"><?= nl2br(e($produto['descricao'])) ?></p>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

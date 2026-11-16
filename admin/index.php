<?php
/** Painel: resumo geral. */
require dirname(__DIR__) . '/includes/init.php';
exigir_admin();

$pdo = db();

$resumo = [
    'produtos' => (int) $pdo->query('SELECT COUNT(*) FROM produtos WHERE ativo = 1')->fetchColumn(),
    'pedidos'  => (int) $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn(),
    'receita'  => (float) $pdo->query("SELECT COALESCE(SUM(total), 0) FROM pedidos WHERE status = 'pago'")->fetchColumn(),
    'clientes' => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'cliente'")->fetchColumn(),
];

$estoqueBaixo = $pdo->query(
    'SELECT id, nome, estoque FROM produtos WHERE ativo = 1 AND estoque <= 5 ORDER BY estoque, nome'
)->fetchAll();

$ultimosPedidos = $pdo->query(
    'SELECT pe.id, pe.data_pedido, pe.total, u.nome AS cliente
       FROM pedidos pe
       JOIN usuarios u ON u.id = pe.usuario_id
      ORDER BY pe.data_pedido DESC, pe.id DESC
      LIMIT 5'
)->fetchAll();

$tituloPagina = 'Painel administrativo';
$abaAdmin = 'resumo';
require __DIR__ . '/_topo.php';
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Receita total</div>
            <div class="fs-4 fw-bold"><?= e(preco($resumo['receita'])) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Pedidos</div>
            <div class="fs-4 fw-bold"><?= $resumo['pedidos'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Produtos ativos</div>
            <div class="fs-4 fw-bold"><?= $resumo['produtos'] ?></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Clientes</div>
            <div class="fs-4 fw-bold"><?= $resumo['clientes'] ?></div>
        </div></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Últimos pedidos</div>
            <?php if (!$ultimosPedidos): ?>
                <div class="card-body text-muted">Nenhum pedido ainda.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($ultimosPedidos as $pedido): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <a class="fw-semibold text-decoration-none" href="<?= e(url('admin/pedidos.php#pedido-' . (int) $pedido['id'])) ?>">#<?= (int) $pedido['id'] ?></a>
                                &middot; <?= e($pedido['cliente']) ?>
                                <br><small class="text-muted"><?= e(data_br($pedido['data_pedido'])) ?></small>
                            </span>
                            <span class="fw-semibold text-nowrap"><?= e(preco($pedido['total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Estoque baixo (5 ou menos)</div>
            <?php if (!$estoqueBaixo): ?>
                <div class="card-body text-muted">Nenhum produto com estoque baixo.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($estoqueBaixo as $p): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <a class="text-decoration-none" href="<?= e(url('admin/produto_form.php?id=' . (int) $p['id'])) ?>"><?= e($p['nome']) ?></a>
                            <span class="badge <?= (int) $p['estoque'] === 0 ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= (int) $p['estoque'] ?> un.</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require RAIZ . '/includes/footer.php'; ?>

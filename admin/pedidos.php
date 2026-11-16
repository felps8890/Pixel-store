<?php
/** Painel: pedidos realizados, com itens e valor total. */
require dirname(__DIR__) . '/includes/init.php';
exigir_admin();

$pedidos = db()->query(
    'SELECT pe.id, pe.data_pedido, pe.forma_pagamento, pe.status, pe.total,
            u.nome AS cliente, u.email
       FROM pedidos pe
       JOIN usuarios u ON u.id = pe.usuario_id
      ORDER BY pe.data_pedido DESC, pe.id DESC'
)->fetchAll();

// Carrega os itens de todos os pedidos em uma única consulta
$itensPorPedido = [];
$itens = db()->query(
    'SELECT i.pedido_id, i.quantidade, i.preco_unitario, p.nome
       FROM pedido_itens i
       JOIN produtos p ON p.id = i.produto_id
      ORDER BY i.pedido_id, p.nome'
);
foreach ($itens as $item) {
    $itensPorPedido[(int) $item['pedido_id']][] = $item;
}

$tituloPagina = 'Pedidos';
$abaAdmin = 'pedidos';
require __DIR__ . '/_topo.php';
?>

<p class="text-muted"><?= count($pedidos) ?> pedido(s) realizados. Clique em um pedido para ver os itens.</p>

<?php if (!$pedidos): ?>
    <div class="text-center py-5">
        <i class="bi bi-inbox display-4 text-muted"></i>
        <p class="fs-5 mt-3">Nenhum pedido realizado ainda.</p>
    </div>
<?php else: ?>
    <div class="accordion" id="lista-pedidos">
        <?php foreach ($pedidos as $pedido): $pid = (int) $pedido['id']; ?>
            <div class="accordion-item" id="pedido-<?= $pid ?>">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#itens-<?= $pid ?>" aria-expanded="false" aria-controls="itens-<?= $pid ?>">
                        <span class="row w-100 g-2 align-items-center me-2">
                            <span class="col-6 col-md-2 fw-bold">#<?= $pid ?></span>
                            <span class="col-6 col-md-3 small"><?= e(data_br($pedido['data_pedido'])) ?></span>
                            <span class="col-12 col-md-3"><?= e($pedido['cliente']) ?></span>
                            <span class="col-6 col-md-2 small"><?= e(FORMAS_PAGAMENTO[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento']) ?></span>
                            <span class="col-6 col-md-2 fw-bold text-md-end"><?= e(preco($pedido['total'])) ?></span>
                        </span>
                    </button>
                </h2>
                <div id="itens-<?= $pid ?>" class="accordion-collapse collapse" data-bs-parent="#lista-pedidos">
                    <div class="accordion-body">
                        <p class="small text-muted mb-2">
                            Cliente: <?= e($pedido['cliente']) ?> (<?= e($pedido['email']) ?>)
                            &middot; Status: <span class="badge <?= $pedido['status'] === 'pago' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(ucfirst($pedido['status'])) ?></span>
                        </p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Produto</th>
                                        <th scope="col" class="text-center">Qtd.</th>
                                        <th scope="col" class="text-end">Preço unit.</th>
                                        <th scope="col" class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($itensPorPedido[$pid] ?? [] as $item): ?>
                                    <tr>
                                        <td><?= e($item['nome']) ?></td>
                                        <td class="text-center"><?= (int) $item['quantidade'] ?></td>
                                        <td class="text-end text-nowrap"><?= e(preco($item['preco_unitario'])) ?></td>
                                        <td class="text-end text-nowrap"><?= e(preco((float) $item['preco_unitario'] * (int) $item['quantidade'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Total do pedido</th>
                                        <th class="text-end text-nowrap"><?= e(preco($pedido['total'])) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require RAIZ . '/includes/footer.php'; ?>

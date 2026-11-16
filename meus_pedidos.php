<?php
/** Histórico de pedidos do cliente logado. */
require __DIR__ . '/includes/init.php';

exigir_login('meus_pedidos.php');

$stmt = db()->prepare(
    'SELECT pe.id, pe.data_pedido, pe.forma_pagamento, pe.status, pe.total,
            COALESCE(SUM(i.quantidade), 0) AS qtd_itens
       FROM pedidos pe
       LEFT JOIN pedido_itens i ON i.pedido_id = pe.id
      WHERE pe.usuario_id = ?
      GROUP BY pe.id, pe.data_pedido, pe.forma_pagamento, pe.status, pe.total
      ORDER BY pe.data_pedido DESC, pe.id DESC'
);
$stmt->execute([usuario_logado()['id']]);
$pedidos = $stmt->fetchAll();

$tituloPagina = 'Meus pedidos';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4"><i class="bi bi-bag-check"></i> Meus pedidos</h1>

<?php if (!$pedidos): ?>
    <div class="text-center py-5">
        <i class="bi bi-bag display-4 text-muted"></i>
        <p class="fs-5 mt-3">Você ainda não fez nenhum pedido.</p>
        <a class="btn btn-destaque" href="<?= e(url('index.php')) ?>">Ver produtos</a>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Pedido</th>
                        <th scope="col">Data</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Pagamento</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col"><span class="visually-hidden">Ações</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td class="fw-semibold">#<?= (int) $pedido['id'] ?></td>
                        <td class="text-nowrap"><?= e(data_br($pedido['data_pedido'])) ?></td>
                        <td><?= (int) $pedido['qtd_itens'] ?></td>
                        <td><?= e(FORMAS_PAGAMENTO[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento']) ?></td>
                        <td><span class="badge <?= $pedido['status'] === 'pago' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(ucfirst($pedido['status'])) ?></span></td>
                        <td class="text-end text-nowrap fw-semibold"><?= e(preco($pedido['total'])) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= e(url('pedido.php?id=' . (int) $pedido['id'])) ?>">Ver detalhes</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

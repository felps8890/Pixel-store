<?php
/** Detalhe/confirmação de um pedido do próprio cliente. */
require __DIR__ . '/includes/init.php';

exigir_login('meus_pedidos.php');

$id = (int) ($_GET['id'] ?? 0);

// O filtro por usuario_id impede que um cliente veja o pedido de outro
$stmt = db()->prepare('SELECT id, data_pedido, forma_pagamento, status, total FROM pedidos WHERE id = ? AND usuario_id = ?');
$stmt->execute([$id, usuario_logado()['id']]);
$pedido = $stmt->fetch();

if (!$pedido) {
    flash('danger', 'Pedido não encontrado.');
    redirecionar('meus_pedidos.php');
}

$stmt = db()->prepare(
    'SELECT i.quantidade, i.preco_unitario, p.id AS produto_id, p.nome, p.imagem
       FROM pedido_itens i
       JOIN produtos p ON p.id = i.produto_id
      WHERE i.pedido_id = ?
      ORDER BY p.nome'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$tituloPagina = 'Pedido #' . $pedido['id'];
require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h1 class="h3 mb-0">Pedido #<?= (int) $pedido['id'] ?></h1>
    <a class="btn btn-outline-dark btn-sm" href="<?= e(url('meus_pedidos.php')) ?>"><i class="bi bi-arrow-left"></i> Meus pedidos</a>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <p class="mb-2"><span class="text-muted">Data:</span><br><?= e(data_br($pedido['data_pedido'])) ?></p>
                <p class="mb-2"><span class="text-muted">Pagamento:</span><br><?= e(FORMAS_PAGAMENTO[$pedido['forma_pagamento']] ?? $pedido['forma_pagamento']) ?></p>
                <p class="mb-2"><span class="text-muted">Status:</span><br>
                    <span class="badge <?= $pedido['status'] === 'pago' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= e(ucfirst($pedido['status'])) ?></span>
                </p>
                <p class="mb-0"><span class="text-muted">Total:</span><br><span class="preco fs-4 fw-bold"><?= e(preco($pedido['total'])) ?></span></p>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" colspan="2">Item</th>
                            <th scope="col" class="text-center">Qtd.</th>
                            <th scope="col" class="text-end">Preço unit.</th>
                            <th scope="col" class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($itens as $item): ?>
                        <tr>
                            <td style="width:72px"><img src="<?= e(imagem_produto($item['imagem'])) ?>" alt="" class="rounded border miniatura"></td>
                            <td><?= e($item['nome']) ?></td>
                            <td class="text-center"><?= (int) $item['quantidade'] ?></td>
                            <td class="text-end text-nowrap"><?= e(preco($item['preco_unitario'])) ?></td>
                            <td class="text-end text-nowrap"><?= e(preco((float) $item['preco_unitario'] * (int) $item['quantidade'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th class="text-end text-nowrap"><?= e(preco($pedido['total'])) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

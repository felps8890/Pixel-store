<?php
/**
 * Checkout: restrito a usuários autenticados.
 * Ao confirmar, dentro de UMA transação:
 *   1. cria o pedido
 *   2. grava cada item com o preço congelado
 *   3. dá baixa no estoque
 * e, após o commit, limpa o carrinho da sessão.
 */
require __DIR__ . '/includes/init.php';

exigir_login('checkout.php');

if (!$_SESSION['carrinho']) {
    flash('info', 'Seu carrinho está vazio.');
    redirecionar('carrinho.php');
}

$erro = '';
$formaEscolhida = 'pix';

if (eh_post()) {
    csrf_validar();

    $formaEscolhida = (string) ($_POST['forma_pagamento'] ?? '');

    if (!isset(FORMAS_PAGAMENTO[$formaEscolhida])) {
        $erro = 'Selecione uma forma de pagamento válida.';
        $formaEscolhida = 'pix';
    } else {
        $pdo = db();

        try {
            $pdo->beginTransaction();

            // Trava as linhas dos produtos (FOR UPDATE) para que duas compras
            // simultâneas não vendam a mesma unidade.
            $buscar = $pdo->prepare('SELECT id, nome, preco, estoque, ativo FROM produtos WHERE id = ? FOR UPDATE');

            $itens = [];
            $total = 0.0;
            $ids = array_keys($_SESSION['carrinho']);
            sort($ids); // ordem fixa de travamento evita deadlock

            foreach ($ids as $produtoId) {
                $qtd = (int) $_SESSION['carrinho'][$produtoId];
                $buscar->execute([(int) $produtoId]);
                $p = $buscar->fetch();

                if (!$p || !(int) $p['ativo']) {
                    throw new DomainException('Um dos produtos do carrinho não está mais disponível.');
                }
                if ($qtd < 1 || $qtd > (int) $p['estoque']) {
                    throw new DomainException(
                        "Estoque insuficiente para {$p['nome']} (disponível: {$p['estoque']})."
                    );
                }

                $itens[] = ['id' => (int) $p['id'], 'qtd' => $qtd, 'preco' => (float) $p['preco']];
                $total = round($total + (float) $p['preco'] * $qtd, 2);
            }

            // 1. Pedido
            $stmt = $pdo->prepare(
                "INSERT INTO pedidos (usuario_id, forma_pagamento, status, total) VALUES (?, ?, 'pago', ?)"
            );
            $stmt->execute([usuario_logado()['id'], $formaEscolhida, number_format($total, 2, '.', '')]);
            $pedidoId = (int) $pdo->lastInsertId();

            // 2. Itens (preço unitário congelado) e 3. baixa de estoque
            $inserirItem = $pdo->prepare(
                'INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)'
            );
            $baixar = $pdo->prepare('UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND estoque >= ?');

            foreach ($itens as $item) {
                $inserirItem->execute([$pedidoId, $item['id'], $item['qtd'], number_format($item['preco'], 2, '.', '')]);
                $baixar->execute([$item['qtd'], $item['id'], $item['qtd']]);

                if ($baixar->rowCount() !== 1) {
                    throw new DomainException('Não foi possível reservar o estoque. Tente novamente.');
                }
            }

            $pdo->commit();

            // 4. Limpa o carrinho
            $_SESSION['carrinho'] = [];

            flash('success', 'Pagamento confirmado! Seu pedido foi realizado.');
            redirecionar('pedido.php?id=' . $pedidoId);
        } catch (DomainException $e) {
            $pdo->rollBack();
            flash('danger', $e->getMessage());
            redirecionar('carrinho.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

$carrinho = carrinho_detalhado();
if ($carrinho['avisos'] || !$carrinho['itens']) {
    foreach ($carrinho['avisos'] as $aviso) {
        flash('warning', $aviso);
    }
    redirecionar('carrinho.php');
}

$usuario = usuario_logado();
$tituloPagina = 'Finalizar compra';
require __DIR__ . '/includes/header.php';
?>

<h1 class="h3 mb-4"><i class="bi bi-credit-card"></i> Finalizar compra</h1>

<?php if ($erro): ?>
    <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('checkout.php')) ?>" id="form-checkout">
    <?= csrf_campo() ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5">Dados do cliente</h2>
                    <p class="mb-0"><?= e($usuario['nome']) ?><br><span class="text-muted"><?= e($usuario['email']) ?></span></p>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Forma de pagamento</h2>

                    <div class="list-group mb-3">
                        <label class="list-group-item d-flex gap-3 align-items-center">
                            <input class="form-check-input flex-shrink-0" type="radio" name="forma_pagamento" value="pix"
                                   <?= $formaEscolhida === 'pix' ? 'checked' : '' ?> required>
                            <span><i class="bi bi-qr-code"></i> <strong>PIX</strong><br>
                                <small class="text-muted">Aprovação imediata</small></span>
                        </label>
                        <label class="list-group-item d-flex gap-3 align-items-center">
                            <input class="form-check-input flex-shrink-0" type="radio" name="forma_pagamento" value="boleto"
                                   <?= $formaEscolhida === 'boleto' ? 'checked' : '' ?>>
                            <span><i class="bi bi-upc-scan"></i> <strong>Boleto</strong><br>
                                <small class="text-muted">Simulado com confirmação imediata</small></span>
                        </label>
                        <label class="list-group-item d-flex gap-3 align-items-center">
                            <input class="form-check-input flex-shrink-0" type="radio" name="forma_pagamento" value="cartao"
                                   <?= $formaEscolhida === 'cartao' ? 'checked' : '' ?>>
                            <span><i class="bi bi-credit-card-2-front"></i> <strong>Cartão de crédito</strong><br>
                                <small class="text-muted">À vista, sem juros</small></span>
                        </label>
                    </div>

                    <div class="detalhe-pagamento alert alert-light border" data-forma="pix">
                        <i class="bi bi-qr-code fs-3 float-start me-3"></i>
                        Ao confirmar, um código PIX fictício é gerado e o pagamento é aprovado na hora.
                    </div>
                    <div class="detalhe-pagamento alert alert-light border d-none" data-forma="boleto">
                        <i class="bi bi-upc-scan fs-3 float-start me-3"></i>
                        O boleto é simulado: o pedido é confirmado imediatamente, sem compensação bancária.
                    </div>
                    <div class="detalhe-pagamento alert alert-light border d-none" data-forma="cartao">
                        <i class="bi bi-credit-card-2-front fs-3 float-start me-3"></i>
                        Pagamento simulado. Não pedimos nem armazenamos dados de cartão neste projeto.
                    </div>

                    <p class="small text-muted mb-0"><i class="bi bi-shield-check"></i> Ambiente de demonstração: nenhuma cobrança real é feita.</p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h2 class="h5 mb-3">Resumo do pedido</h2>
                    <?php foreach ($carrinho['itens'] as $item): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="me-2"><?= $item['quantidade'] ?>x <?= e($item['nome']) ?>
                                <br><small class="text-muted"><?= e(preco($item['preco'])) ?> cada</small></span>
                            <span class="text-nowrap"><?= e(preco($item['subtotal'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <hr>
                    <div class="d-flex justify-content-between fs-5 fw-bold mb-3">
                        <span>Total</span><span class="preco"><?= e(preco($carrinho['total'])) ?></span>
                    </div>
                    <button class="btn btn-destaque btn-lg w-100" type="submit" id="btn-confirmar">
                        <i class="bi bi-lock-fill"></i> Confirmar pagamento
                    </button>
                    <a class="btn btn-link w-100 mt-2" href="<?= e(url('carrinho.php')) ?>">Voltar ao carrinho</a>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>

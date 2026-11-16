<?php
/**
 * Topo comum do painel. Defina $tituloPagina e $abaAdmin antes de incluir.
 * A checagem de acesso (exigir_admin) é feita em cada página ANTES de
 * qualquer processamento; aqui só montamos o layout.
 */
if (!defined('RAIZ')) {
    http_response_code(403);
    exit('Acesso negado.');
}
$abaAdmin = $abaAdmin ?? '';
require RAIZ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0"><i class="bi bi-speedometer2"></i> Painel administrativo</h1>
    <a class="btn btn-outline-dark btn-sm" href="<?= e(url('index.php')) ?>"><i class="bi bi-shop"></i> Ver loja</a>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $abaAdmin === 'resumo' ? 'active' : '' ?>" href="<?= e(url('admin/index.php')) ?>">Resumo</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $abaAdmin === 'produtos' ? 'active' : '' ?>" href="<?= e(url('admin/produtos.php')) ?>">Produtos</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $abaAdmin === 'pedidos' ? 'active' : '' ?>" href="<?= e(url('admin/pedidos.php')) ?>">Pedidos</a>
    </li>
</ul>

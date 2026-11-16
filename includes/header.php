<?php
/** Cabeçalho comum. Defina $tituloPagina antes de incluir. */
$tituloPagina = $tituloPagina ?? APP_NAME;
$usuarioNav   = usuario_logado();
$qtdCarrinho  = carrinho_quantidade_total();
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tituloPagina) ?> | <?= e(APP_NAME) ?></title>
    <link href="<?= e(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')) ?>" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= e(url('assets/img/favicon.svg')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark navbar-loja sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">
            <i class="bi bi-controller"></i> <?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                aria-controls="menu" aria-expanded="false" aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menu">
            <form class="d-flex mx-lg-4 my-2 my-lg-0 flex-grow-1" role="search" method="get" action="<?= e(url('index.php')) ?>">
                <div class="input-group">
                    <input class="form-control" type="search" name="busca" placeholder="Buscar produtos..."
                           aria-label="Buscar produtos" value="<?= e($_GET['busca'] ?? '') ?>">
                    <button class="btn btn-destaque" type="submit" aria-label="Buscar"><i class="bi bi-search"></i></button>
                </div>
            </form>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(url('carrinho.php')) ?>">
                        <i class="bi bi-cart3"></i> Carrinho
                        <?php if ($qtdCarrinho > 0): ?>
                            <span class="badge rounded-pill text-bg-warning"><?= $qtdCarrinho ?></span>
                        <?php endif; ?>
                    </a>
                </li>

                <?php if ($usuarioNav): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= e(explode(' ', $usuarioNav['nome'])[0]) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= e(url('meus_pedidos.php')) ?>"><i class="bi bi-bag-check"></i> Meus pedidos</a></li>
                            <?php if (eh_admin()): ?>
                                <li><a class="dropdown-item" href="<?= e(url('admin/index.php')) ?>"><i class="bi bi-speedometer2"></i> Painel admin</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="<?= e(url('logout.php')) ?>">
                                    <?= csrf_campo() ?>
                                    <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right"></i> Sair</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('login.php')) ?>"><i class="bi bi-box-arrow-in-right"></i> Entrar</a></li>
                    <li class="nav-item"><a class="btn btn-sm btn-destaque ms-lg-2" href="<?= e(url('cadastro.php')) ?>">Criar conta</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php foreach (flash_ler() as $msg): ?>
        <div class="alert alert-<?= e($msg['tipo']) ?> alert-dismissible fade show" role="alert">
            <?= e($msg['mensagem']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endforeach; ?>

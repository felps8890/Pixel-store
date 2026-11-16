<?php
/** Login com password_verify(). */
require __DIR__ . '/includes/init.php';

if (usuario_logado()) {
    redirecionar('index.php');
}

$email = '';
$erro  = '';

if (eh_post()) {
    csrf_validar();

    $email = trim((string) ($_POST['email'] ?? ''));
    $senha = (string) ($_POST['senha'] ?? '');

    $stmt = db()->prepare('SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        // Atualiza o hash se o algoritmo padrão do PHP tiver mudado
        if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
            $novo = db()->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?');
            $novo->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]);
        }

        $destino = $_SESSION['destino'] ?? '';
        unset($_SESSION['destino']);

        iniciar_sessao_usuario($usuario);
        flash('success', 'Bem-vindo(a), ' . explode(' ', $usuario['nome'])[0] . '!');

        if ($destino === '') {
            $destino = $usuario['perfil'] === 'admin' ? 'admin/index.php' : 'index.php';
        }
        redirecionar($destino);
    }

    // Mensagem genérica: não revela se o e-mail existe
    $erro = 'E-mail ou senha incorretos.';
}

$tituloPagina = 'Entrar';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center">Entrar na sua conta</h1>

                <?php if ($erro): ?>
                    <div class="alert alert-danger" role="alert"><?= e($erro) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('login.php')) ?>" novalidate class="precisa-validar">
                    <?= csrf_campo() ?>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>"
                               autocomplete="email" required autofocus>
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="mb-4">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" autocomplete="current-password" required>
                        <div class="invalid-feedback">Informe sua senha.</div>
                    </div>
                    <button class="btn btn-destaque w-100" type="submit">Entrar</button>
                </form>

                <p class="text-center mt-4 mb-0">
                    Ainda não tem conta? <a href="<?= e(url('cadastro.php')) ?>">Cadastre-se</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

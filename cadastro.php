<?php
/** Cadastro de cliente com password_hash(). */
require __DIR__ . '/includes/init.php';

if (usuario_logado()) {
    redirecionar('index.php');
}

$nome  = '';
$email = '';
$erros = [];

if (eh_post()) {
    csrf_validar();

    $nome      = trim((string) ($_POST['nome'] ?? ''));
    $email     = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha     = (string) ($_POST['senha'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
        $erros[] = 'Informe seu nome completo (3 a 100 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (strlen($senha) < 8) {
        $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    }
    if ($senha !== $confirmar) {
        $erros[] = 'As senhas não conferem.';
    }

    if (!$erros) {
        $stmt = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn()) {
            $erros[] = 'Este e-mail já está cadastrado. Faça login.';
        }
    }

    if (!$erros) {
        try {
            // Todo cadastro público entra como "cliente"; admin só via banco
            $stmt = db()->prepare(
                "INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, 'cliente')"
            );
            $stmt->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);

            $destino = $_SESSION['destino'] ?? 'index.php';
            unset($_SESSION['destino']);

            iniciar_sessao_usuario([
                'id'     => (int) db()->lastInsertId(),
                'nome'   => $nome,
                'email'  => $email,
                'perfil' => 'cliente',
            ]);
            flash('success', 'Conta criada com sucesso. Boas compras!');
            redirecionar($destino);
        } catch (PDOException $e) {
            // 23000 = violação de chave única (corrida entre dois cadastros iguais)
            if ($e->getCode() === '23000') {
                $erros[] = 'Este e-mail já está cadastrado. Faça login.';
            } else {
                throw $e;
            }
        }
    }
}

$tituloPagina = 'Criar conta';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center">Criar conta</h1>

                <?php if ($erros): ?>
                    <div class="alert alert-danger" role="alert">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($erros as $erro): ?><li><?= e($erro) ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= e(url('cadastro.php')) ?>" novalidate class="precisa-validar">
                    <?= csrf_campo() ?>
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?= e($nome) ?>"
                               minlength="3" maxlength="100" autocomplete="name" required autofocus>
                        <div class="invalid-feedback">Informe seu nome (mínimo 3 caracteres).</div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>"
                               maxlength="150" autocomplete="email" required>
                        <div class="invalid-feedback">Informe um e-mail válido.</div>
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" minlength="8"
                               autocomplete="new-password" required>
                        <div class="form-text">Mínimo de 8 caracteres.</div>
                        <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirmar" class="form-label">Confirmar senha</label>
                        <input type="password" class="form-control" id="confirmar" name="confirmar" minlength="8"
                               autocomplete="new-password" data-igual-a="senha" required>
                        <div class="invalid-feedback">As senhas não conferem.</div>
                    </div>
                    <button class="btn btn-destaque w-100" type="submit">Criar conta</button>
                </form>

                <p class="text-center mt-4 mb-0">
                    Já tem conta? <a href="<?= e(url('login.php')) ?>">Entrar</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>

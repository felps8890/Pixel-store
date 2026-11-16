<?php
/**
 * Funções auxiliares: saída segura, URLs, mensagens, CSRF,
 * autenticação/autorização e carrinho em sessão.
 */

declare(strict_types=1);

const FORMAS_PAGAMENTO = [
    'pix'    => 'PIX',
    'boleto' => 'Boleto',
    'cartao' => 'Cartão de crédito',
];

/* ---------------------------------------------------------------
 * Saída e navegação
 * ------------------------------------------------------------- */

/** Escapa texto para HTML (prevenção de XSS). */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/** Monta uma URL interna respeitando a pasta onde o projeto está instalado. */
function url(string $caminho = ''): string
{
    return BASE_URL . '/' . ltrim($caminho, '/');
}

function redirecionar(string $caminho): void
{
    header('Location: ' . url($caminho));
    exit;
}

/** Formata valor monetário em reais. */
function preco($valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function data_br(string $data): string
{
    return date('d/m/Y H:i', strtotime($data));
}

/** URL da imagem do produto (ou imagem padrão se não houver arquivo). */
function imagem_produto(?string $arquivo): string
{
    if ($arquivo && is_file(RAIZ . '/assets/img/produtos/' . basename($arquivo))) {
        return url('assets/img/produtos/' . rawurlencode(basename($arquivo)));
    }
    return url('assets/img/sem-imagem.svg');
}

/* ---------------------------------------------------------------
 * Mensagens flash
 * ------------------------------------------------------------- */

function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

function flash_ler(): array
{
    $mensagens = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensagens;
}

/* ---------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Interrompe a requisição se o token do formulário for inválido. */
function csrf_validar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(419);
        exit('Sessão expirada ou formulário inválido. Volte e tente novamente.');
    }
}

function eh_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------------------------------------------------------------
 * Autenticação e controle de acesso (validado no servidor)
 * ------------------------------------------------------------- */

function usuario_logado(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function eh_admin(): bool
{
    $usuario = usuario_logado();
    return $usuario !== null && $usuario['perfil'] === 'admin';
}

/** Grava o usuário na sessão após login/cadastro bem-sucedido. */
function iniciar_sessao_usuario(array $usuario): void
{
    session_regenerate_id(true); // evita fixação de sessão
    $_SESSION['usuario'] = [
        'id'     => (int) $usuario['id'],
        'nome'   => $usuario['nome'],
        'email'  => $usuario['email'],
        'perfil' => $usuario['perfil'],
    ];
}

/** Exige usuário autenticado; caso contrário envia para o login. */
function exigir_login(string $voltarPara = ''): void
{
    if (usuario_logado() === null) {
        if ($voltarPara !== '') {
            $_SESSION['destino'] = $voltarPara;
        }
        flash('warning', 'Faça login ou cadastre-se para continuar.');
        redirecionar('login.php');
    }
}

/**
 * Exige perfil administrativo.
 * Confere o perfil direto no banco a cada requisição, para que uma
 * mudança de perfil tenha efeito imediato mesmo com sessão aberta.
 */
function exigir_admin(): void
{
    exigir_login('admin/index.php');

    $stmt = db()->prepare('SELECT perfil FROM usuarios WHERE id = ?');
    $stmt->execute([usuario_logado()['id']]);
    $perfil = $stmt->fetchColumn();

    if ($perfil !== 'admin') {
        $_SESSION['usuario']['perfil'] = $perfil ?: 'cliente';
        http_response_code(403);
        flash('danger', 'Acesso restrito a administradores.');
        redirecionar('index.php');
    }
}

/* ---------------------------------------------------------------
 * Carrinho ($_SESSION['carrinho'] = [produto_id => quantidade])
 * ------------------------------------------------------------- */

function carrinho_quantidade_total(): int
{
    return (int) array_sum($_SESSION['carrinho'] ?? []);
}

/**
 * Busca no banco os produtos do carrinho e calcula subtotais e total.
 * Também corrige o carrinho se algum produto saiu de linha ou se o
 * estoque diminuiu desde que o item foi adicionado.
 *
 * @return array{itens: array<int, array>, total: float, avisos: string[]}
 */
function carrinho_detalhado(): array
{
    $carrinho = $_SESSION['carrinho'] ?? [];
    $resultado = ['itens' => [], 'total' => 0.0, 'avisos' => []];

    if (!$carrinho) {
        return $resultado;
    }

    $ids = array_map('intval', array_keys($carrinho));
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $stmt = db()->prepare(
        "SELECT id, nome, preco, estoque, imagem, ativo
           FROM produtos
          WHERE id IN ($marcadores)"
    );
    $stmt->execute($ids);

    $produtos = [];
    foreach ($stmt as $linha) {
        $produtos[(int) $linha['id']] = $linha;
    }

    foreach ($carrinho as $id => $qtd) {
        $id  = (int) $id;
        $qtd = (int) $qtd;
        $p   = $produtos[$id] ?? null;

        if ($p === null || !(int) $p['ativo'] || (int) $p['estoque'] < 1) {
            unset($_SESSION['carrinho'][$id]);
            $nome = $p['nome'] ?? 'Um produto';
            $resultado['avisos'][] = "$nome não está mais disponível e foi removido do carrinho.";
            continue;
        }

        if ($qtd > (int) $p['estoque']) {
            $qtd = (int) $p['estoque'];
            $_SESSION['carrinho'][$id] = $qtd;
            $resultado['avisos'][] = "A quantidade de {$p['nome']} foi ajustada para o estoque disponível ($qtd).";
        }

        $subtotal = round((float) $p['preco'] * $qtd, 2);

        $resultado['itens'][] = [
            'id'         => $id,
            'nome'       => $p['nome'],
            'preco'      => (float) $p['preco'],
            'estoque'    => (int) $p['estoque'],
            'imagem'     => $p['imagem'],
            'quantidade' => $qtd,
            'subtotal'   => $subtotal,
        ];
        $resultado['total'] = round($resultado['total'] + $subtotal, 2);
    }

    return $resultado;
}

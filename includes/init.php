<?php
/**
 * Inicialização comum a todas as páginas:
 * variáveis de ambiente, sessão, conexão e funções auxiliares.
 */

declare(strict_types=1);

define('RAIZ', dirname(__DIR__));

require_once RAIZ . '/config/env.php';
require_once RAIZ . '/config/database.php';
require_once RAIZ . '/includes/functions.php';

// Tratamento global de erros: nunca expõe detalhes técnicos em produção
set_exception_handler(function (Throwable $e): void {
    $debug = isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true';
    error_log($e->getMessage());
    http_response_code(500);

    $mensagem = 'Ocorreu um erro interno. Tente novamente mais tarde.';
    if ($e instanceof RuntimeException && strpos($e->getMessage(), '.env') !== false) {
        $mensagem = $e->getMessage();
    } elseif ($e instanceof PDOException) {
        $mensagem = 'Não foi possível acessar o banco de dados. Verifique o arquivo .env e se o schema.sql foi importado.';
    }
    if ($debug) {
        $mensagem .= ' [' . get_class($e) . '] ' . $e->getMessage();
    }

    echo '<!doctype html><html lang="pt-br"><meta charset="utf-8"><title>Erro</title>'
        . '<body style="font-family:system-ui,sans-serif;max-width:640px;margin:15vh auto;padding:0 1rem">'
        . '<h1>Algo deu errado</h1><p>' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
});

carregar_env(RAIZ . '/.env');

date_default_timezone_set('America/Sao_Paulo');

// Caminho base (permite rodar em http://localhost/ ou http://localhost/pasta/)
$baseConfigurada = (string) env('APP_BASE_URL', '');
if ($baseConfigurada !== '') {
    $base = $baseConfigurada;
} else {
    $raiz = str_replace('\\', '/', (string) realpath(RAIZ));
    $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = ($doc !== '' && stripos($raiz, $doc) === 0) ? substr($raiz, strlen($doc)) : '';
}
define('BASE_URL', rtrim($base, '/'));
define('APP_NAME', (string) env('APP_NAME', 'PixelStore'));

// Sessão com cookie protegido
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('pixelstore_sess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

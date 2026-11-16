<?php
/**
 * Leitor simples de arquivo .env (sem dependências externas).
 * Formato suportado:  CHAVE=valor   |   CHAVE="valor com espaços"   |   # comentário
 */

function carregar_env(string $caminho): void
{
    if (!is_file($caminho) || !is_readable($caminho)) {
        throw new RuntimeException(
            'Arquivo .env não encontrado. Copie ".env.example" para ".env" e preencha as credenciais do banco.'
        );
    }

    $linhas = file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($linhas as $linha) {
        $linha = trim($linha);

        if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
            continue;
        }

        [$chave, $valor] = explode('=', $linha, 2);
        $chave = trim($chave);
        $valor = trim($valor);

        // Remove aspas ao redor do valor, se houver
        $tam = strlen($valor);
        if ($tam >= 2) {
            $primeiro = $valor[0];
            $ultimo   = $valor[$tam - 1];
            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        $_ENV[$chave] = $valor;
    }
}

/**
 * Lê uma variável de ambiente, com valor padrão opcional.
 */
function env(string $chave, $padrao = null)
{
    if (array_key_exists($chave, $_ENV)) {
        return $_ENV[$chave];
    }

    $valor = getenv($chave);
    return $valor !== false ? $valor : $padrao;
}

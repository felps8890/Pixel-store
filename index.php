<?php
/** Vitrine: listagem dinâmica com busca, filtro por categoria e ordenação. */
require __DIR__ . '/includes/init.php';

$busca       = trim((string) ($_GET['busca'] ?? ''));
$categoriaId = (int) ($_GET['categoria'] ?? 0);
$ordem       = (string) ($_GET['ordem'] ?? 'nome');

// Ordenação por lista fixa (nunca vem direto do usuário para o SQL)
$ordens = [
    'nome'        => ['Nome (A-Z)',   'p.nome ASC'],
    'menor_preco' => ['Menor preço',  'p.preco ASC'],
    'maior_preco' => ['Maior preço',  'p.preco DESC'],
    'recentes'    => ['Mais recentes', 'p.criado_em DESC, p.id DESC'],
];
if (!isset($ordens[$ordem])) {
    $ordem = 'nome';
}

$categorias = db()->query('SELECT id, nome FROM categorias ORDER BY nome')->fetchAll();

$sql = 'SELECT p.id, p.nome, p.preco, p.estoque, p.imagem, c.nome AS categoria
          FROM produtos p
          JOIN categorias c ON c.id = p.categoria_id
         WHERE p.ativo = 1';
$parametros = [];

if ($categoriaId > 0) {
    $sql .= ' AND p.categoria_id = ?';
    $parametros[] = $categoriaId;
}
if ($busca !== '') {
    $sql .= ' AND (p.nome LIKE ? OR p.descricao LIKE ?)';
    $termo = '%' . addcslashes($busca, '%_\\') . '%';
    $parametros[] = $termo;
    $parametros[] = $termo;
}
$sql .= ' ORDER BY ' . $ordens[$ordem][1];

$stmt = db()->prepare($sql);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll();

/** Mantém os filtros atuais ao montar links. */
function link_filtro(array $novos): string
{
    $atual = [
        'busca'     => trim((string) ($_GET['busca'] ?? '')),
        'categoria' => (int) ($_GET['categoria'] ?? 0),
        'ordem'     => (string) ($_GET['ordem'] ?? ''),
    ];
    $query = array_filter(array_merge($atual, $novos), fn($v) => $v !== '' && $v !== 0);
    return url('index.php') . ($query ? '?' . http_build_query($query) : '');
}

$tituloPagina = 'Loja';
require __DIR__ . '/includes/header.php';
?>

<?php if ($busca === '' && $categoriaId === 0): ?>
<section class="banner rounded-4 p-4 p-md-5 mb-4 text-white">
    <h1 class="display-6 fw-bold mb-2">Seu próximo setup começa aqui</h1>
    <p class="lead mb-0">Consoles, jogos e periféricos com entrega para todo o Brasil.</p>
</section>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a class="btn btn-sm <?= $categoriaId === 0 ? 'btn-dark' : 'btn-outline-dark' ?>"
       href="<?= e(link_filtro(['categoria' => 0])) ?>">Todas</a>
    <?php foreach ($categorias as $cat): ?>
        <a class="btn btn-sm <?= $categoriaId === (int) $cat['id'] ? 'btn-dark' : 'btn-outline-dark' ?>"
           href="<?= e(link_filtro(['categoria' => (int) $cat['id']])) ?>"><?= e($cat['nome']) ?></a>
    <?php endforeach; ?>

    <form class="ms-auto d-flex align-items-center gap-2" method="get" action="<?= e(url('index.php')) ?>">
        <?php if ($busca !== ''): ?><input type="hidden" name="busca" value="<?= e($busca) ?>"><?php endif; ?>
        <?php if ($categoriaId > 0): ?><input type="hidden" name="categoria" value="<?= $categoriaId ?>"><?php endif; ?>
        <label for="ordem" class="small text-muted text-nowrap">Ordenar por</label>
        <select class="form-select form-select-sm" id="ordem" name="ordem" data-auto-submit>
            <?php foreach ($ordens as $chave => $info): ?>
                <option value="<?= e($chave) ?>" <?= $ordem === $chave ? 'selected' : '' ?>><?= e($info[0]) ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-sm btn-dark" type="submit">Aplicar</button></noscript>
    </form>
</div>

<?php if ($busca !== ''): ?>
    <p class="text-muted">
        <?= count($produtos) ?> resultado(s) para <strong>"<?= e($busca) ?>"</strong>
        &middot; <a href="<?= e(link_filtro(['busca' => ''])) ?>">limpar busca</a>
    </p>
<?php endif; ?>

<?php if (!$produtos): ?>
    <div class="text-center py-5">
        <i class="bi bi-search display-4 text-muted"></i>
        <p class="mt-3 mb-1 fs-5">Nenhum produto encontrado.</p>
        <a href="<?= e(url('index.php')) ?>">Ver todos os produtos</a>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
        <?php foreach ($produtos as $p): $disponivel = (int) $p['estoque'] > 0; ?>
            <div class="col">
                <div class="card card-produto h-100">
                    <a href="<?= e(url('produto.php?id=' . (int) $p['id'])) ?>" class="position-relative">
                        <img src="<?= e(imagem_produto($p['imagem'])) ?>" class="card-img-top" alt="<?= e($p['nome']) ?>" loading="lazy">
                        <?php if (!$disponivel): ?>
                            <span class="badge text-bg-secondary position-absolute top-0 end-0 m-2">Esgotado</span>
                        <?php elseif ((int) $p['estoque'] <= 5): ?>
                            <span class="badge text-bg-warning position-absolute top-0 end-0 m-2">Últimas unidades</span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body d-flex flex-column">
                        <span class="small text-muted"><?= e($p['categoria']) ?></span>
                        <h2 class="card-title h6 mt-1">
                            <a class="stretched-link-off text-decoration-none text-dark" href="<?= e(url('produto.php?id=' . (int) $p['id'])) ?>"><?= e($p['nome']) ?></a>
                        </h2>
                        <p class="preco fs-5 fw-bold mt-auto mb-3"><?= e(preco($p['preco'])) ?></p>

                        <?php if ($disponivel): ?>
                            <form method="post" action="<?= e(url('carrinho.php')) ?>">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="acao" value="adicionar">
                                <input type="hidden" name="produto_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="quantidade" value="1">
                                <button class="btn btn-destaque w-100" type="submit"><i class="bi bi-cart-plus"></i> Adicionar</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary w-100" type="button" disabled>Indisponível</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>

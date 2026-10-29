<?php
// Back-end: simula pedidos (POST ?acao=simular) e lista pedidos (GET ?acao=listar)

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conexao.php';

/**
 * Gera $qtd pedidos usando um laço e grava no banco.
 * Cada pedido (pedidos + itens_pedido) é gravado dentro de uma transação.
 */
function simularPedidos(mysqli $conn, int $qtd): int
{
    // Dados já cadastrados que serão sorteados na simulação
    $usuarios     = $conn->query("SELECT id_usuario FROM usuarios")->fetch_all(MYSQLI_ASSOC);
    $entregadores = $conn->query("SELECT id_entregador FROM entregadores")->fetch_all(MYSQLI_ASSOC);
    $produtos     = $conn->query("SELECT id_produto, preco FROM produtos")->fetch_all(MYSQLI_ASSOC);

    if (!$usuarios || !$entregadores || !$produtos) {
        throw new Exception("Cadastre usuários, entregadores e produtos antes (rode o banco.sql).");
    }

    // Prepared statements: protegem contra SQL Injection e são reaproveitados no laço
    $stmtPedido = $conn->prepare(
        "INSERT INTO pedidos (id_usuario, id_entregador, status_pedido, valor_total) VALUES (?, ?, ?, ?)"
    );
    $stmtItem = $conn->prepare(
        "INSERT INTO itens_pedido (id_pedido, id_produto, quantidade, preco_unitario) VALUES (?, ?, ?, ?)"
    );

    $criados = 0;
    $statusPossiveis = ['pendente', 'em preparo', 'saiu para entrega', 'entregue'];

    // LAÇO que gera múltiplos pedidos
    for ($i = 1; $i <= $qtd; $i++) {
        $idUsuario = $usuarios[array_rand($usuarios)]['id_usuario'];
        $status    = $statusPossiveis[array_rand($statusPossiveis)];

        // Pedido pendente ou em preparo ainda não tem entregador (id_entregador NULL)
        $idEntregador = in_array($status, ['saiu para entrega', 'entregue'])
            ? $entregadores[array_rand($entregadores)]['id_entregador']
            : null;

        // Sorteia de 1 a 3 produtos diferentes para o pedido
        $qtdProdutos = min(rand(1, 3), count($produtos));
        $sorteados   = (array) array_rand($produtos, $qtdProdutos);

        $itens = [];
        $total = 0.0;
        foreach ($sorteados as $indice) {
            $quantidade = rand(1, 3);
            $preco      = (float) $produtos[$indice]['preco'];
            $itens[]    = [(int) $produtos[$indice]['id_produto'], $quantidade, $preco];
            $total     += $quantidade * $preco;
        }

        $conn->begin_transaction();
        try {
            // INSERE o pedido
            $stmtPedido->bind_param("iisd", $idUsuario, $idEntregador, $status, $total);
            $stmtPedido->execute();
            $idPedido = $conn->insert_id;

            // INSERE os itens do pedido
            foreach ($itens as [$idProduto, $quantidade, $preco]) {
                $stmtItem->bind_param("iiid", $idPedido, $idProduto, $quantidade, $preco);
                $stmtItem->execute();
            }

            $conn->commit();
            $criados++;
        } catch (Throwable $e) {
            $conn->rollback();   // desfaz tudo se algo falhar
            throw $e;
        }
    }

    $stmtPedido->close();
    $stmtItem->close();

    return $criados;
}

/**
 * Recupera a lista final de pedidos processados (JOIN entre as tabelas).
 */
function listarPedidos(mysqli $conn): array
{
    $sql = "SELECT p.id_pedido,
                   u.nome  AS cliente,
                   COALESCE(e.nome, 'Aguardando') AS entregador,
                   DATE_FORMAT(p.data_hora, '%d/%m/%Y %H:%i:%s') AS data_hora,
                   p.status_pedido AS status,
                   p.valor_total,
                   GROUP_CONCAT(CONCAT(i.quantidade, 'x ', pr.nome) SEPARATOR ', ') AS itens
            FROM pedidos p
            JOIN usuarios     u  ON u.id_usuario    = p.id_usuario
            LEFT JOIN entregadores e ON e.id_entregador = p.id_entregador
            LEFT JOIN itens_pedido i  ON i.id_pedido  = p.id_pedido
            LEFT JOIN produtos     pr ON pr.id_produto = i.id_produto
            GROUP BY p.id_pedido
            ORDER BY p.id_pedido DESC
            LIMIT 200";

    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

/**
 * Totais reais do banco (sem o LIMIT da listagem).
 */
function resumoPedidos(mysqli $conn): array
{
    $r = $conn->query(
        "SELECT COUNT(*) AS total, COALESCE(SUM(valor_total), 0) AS valor
         FROM pedidos"
    )->fetch_assoc();

    return ['total' => (int) $r['total'], 'valor' => (float) $r['valor']];
}

/**
 * Apaga todos os pedidos e reinicia a numeração.
 * Primeiro os itens (tabela filha), depois os pedidos (tabela pai).
 */
function resetarPedidos(mysqli $conn): void
{
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM itens_pedido");
        $conn->query("DELETE FROM pedidos");
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    // ALTER TABLE encerra transações implicitamente, por isso fica fora do try
    $conn->query("ALTER TABLE pedidos AUTO_INCREMENT = 1");
    $conn->query("ALTER TABLE itens_pedido AUTO_INCREMENT = 1");
}

try {
    $conn = conectar();
    $acao = $_GET['acao'] ?? 'listar';
    $criados = 0;

    if ($acao === 'simular') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['erro' => 'Use o método POST para simular.']);
            exit;
        }

        // Valida a entrada: inteiro entre 1 e 100
        $qtd = filter_var($_POST['qtd'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100]
        ]);

        if ($qtd === false) {
            http_response_code(400);
            echo json_encode(['erro' => 'Informe uma quantidade entre 1 e 100.']);
            exit;
        }

        $criados = simularPedidos($conn, $qtd);
    }

    if ($acao === 'resetar') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['erro' => 'Use o método POST para resetar.']);
            exit;
        }

        resetarPedidos($conn);
    }

    echo json_encode([
        'criados' => $criados,
        'pedidos' => listarPedidos($conn),
        'resumo'  => resumoPedidos($conn),
    ]);

    $conn->close();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Falha no servidor: ' . $e->getMessage()]);
}

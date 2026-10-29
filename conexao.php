<?php
// Conexão com o MySQL usando mysqli
// Ajuste usuário e senha conforme o seu ambiente (XAMPP padrão: root / senha vazia)

function conectar(): mysqli
{
    // Faz o mysqli lançar exceções em caso de erro (facilita o try/catch)
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = new mysqli("localhost", "root", "", "delivery");
    $conn->set_charset("utf8mb4");

    return $conn;
}

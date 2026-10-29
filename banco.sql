-- ATENÇÃO: este script recria o banco do zero (apaga os dados antigos).
-- Execute no MySQL (phpMyAdmin > Importar, ou: mysql -u root < banco.sql)

DROP DATABASE IF EXISTS delivery;
CREATE DATABASE delivery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE delivery;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100) NOT NULL,
    email      VARCHAR(100) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,   -- hash (password_hash), nunca a senha em texto puro
    telefone   VARCHAR(20)  NOT NULL,
    endereco   VARCHAR(200) NOT NULL
);

CREATE TABLE entregadores (
    id_entregador INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100) NOT NULL,
    cpf           VARCHAR(14)  NOT NULL UNIQUE,
    telefone      VARCHAR(20)  NOT NULL,
    veiculo       VARCHAR(50)  NOT NULL,
    status        VARCHAR(20)  NOT NULL DEFAULT 'disponível'  -- 'disponível', 'em entrega', 'offline'
);

CREATE TABLE produtos (
    id_produto INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(100)  NOT NULL,
    descricao  TEXT,
    preco      DECIMAL(10,2) NOT NULL
);

CREATE TABLE pedidos (
    id_pedido     INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario    INT NOT NULL,
    id_entregador INT NULL,   -- só recebe entregador depois que o pedido sai para entrega
    data_hora     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status_pedido VARCHAR(30)   NOT NULL DEFAULT 'pendente',  -- 'pendente', 'em preparo', 'saiu para entrega', 'entregue'
    valor_total   DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_usuario)    REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_entregador) REFERENCES entregadores(id_entregador),
    INDEX idx_pedidos_usuario (id_usuario),
    INDEX idx_pedidos_entregador (id_entregador)
);

-- Tabela associativa: resolve a relação N:M entre pedidos e produtos
CREATE TABLE itens_pedido (
    id_item        INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido      INT NOT NULL,
    id_produto     INT NOT NULL,
    quantidade     INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido)  REFERENCES pedidos(id_pedido),
    FOREIGN KEY (id_produto) REFERENCES produtos(id_produto),
    INDEX idx_itens_pedido (id_pedido),
    INDEX idx_itens_produto (id_produto)
);

-- Dados iniciais (senha de todos os usuários de exemplo: 123456, já em hash bcrypt)
INSERT INTO usuarios (nome, email, senha_hash, telefone, endereco) VALUES
('Ana Souza',    'ana@email.com',    '$2y$10$lGP4RQYCuJYngXIIEQmQ4.T3H6dt.nkVYf0PSsmESVXXwWsJVd4Vi', '(21) 99999-0001', 'Rua das Flores, 100 - Copacabana'),
('Bruno Lima',   'bruno@email.com',  '$2y$10$lGP4RQYCuJYngXIIEQmQ4.T3H6dt.nkVYf0PSsmESVXXwWsJVd4Vi', '(21) 99999-0002', 'Av. Atlântica, 250 - Leme'),
('Carla Mendes', 'carla@email.com',  '$2y$10$lGP4RQYCuJYngXIIEQmQ4.T3H6dt.nkVYf0PSsmESVXXwWsJVd4Vi', '(21) 99999-0003', 'Rua Voluntários da Pátria, 45 - Botafogo'),
('Diego Santos', 'diego@email.com',  '$2y$10$lGP4RQYCuJYngXIIEQmQ4.T3H6dt.nkVYf0PSsmESVXXwWsJVd4Vi', '(21) 99999-0004', 'Rua do Catete, 310 - Catete'),
('Elisa Rocha',  'elisa@email.com',  '$2y$10$lGP4RQYCuJYngXIIEQmQ4.T3H6dt.nkVYf0PSsmESVXXwWsJVd4Vi', '(21) 99999-0005', 'Rua Barão da Torre, 87 - Ipanema');

INSERT INTO entregadores (nome, cpf, telefone, veiculo, status) VALUES
('Marcos', '123.456.789-01', '(21) 98888-0001', 'Moto',      'disponível'),
('Paulo',  '234.567.890-12', '(21) 98888-0002', 'Bicicleta', 'disponível'),
('Julia',  '345.678.901-23', '(21) 98888-0003', 'Moto',      'disponível');

INSERT INTO produtos (nome, descricao, preco) VALUES
('Pizza',         'Pizza de mussarela com orégano',       45.90),
('Hambúrguer',    'Pão brioche, carne 180g e queijo',     32.50),
('Sushi',         'Combinado com 10 peças',               58.00),
('Salada',        'Mix de folhas, tomate e cenoura',      22.00),
('Lasanha',       'Lasanha à bolonhesa',                  39.90),
('Refrigerante',  'Lata 350ml',                            8.50);

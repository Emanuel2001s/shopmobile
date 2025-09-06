CREATE TABLE IF NOT EXISTS `categorias` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `status` ENUM('ativo', 'inativo') DEFAULT 'ativo'
);

CREATE TABLE IF NOT EXISTS `produtos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome` VARCHAR(255) NOT NULL,
    `descricao` TEXT,
    `preco` DECIMAL(10, 2) NOT NULL,
    `foto_principal` VARCHAR(255),
    `fotos_adicionais` TEXT,
    `categoria_id` INT,
    `estoque` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`)
);

CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS `pedidos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nome_completo` VARCHAR(255) NOT NULL,
    `whatsapp` VARCHAR(20) NOT NULL,
    `entregar_endereco` BOOLEAN DEFAULT FALSE,
    `rua` VARCHAR(255),
    `numero` VARCHAR(50),
    `bairro` VARCHAR(255),
    `cidade` VARCHAR(255),
    `cep` VARCHAR(10),
    `produto_id` INT NOT NULL,
    `status` ENUM('pendente', 'confirmado', 'cancelado') DEFAULT 'pendente',
    `data_pedido` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`produto_id`) REFERENCES `produtos`(`id`)
);


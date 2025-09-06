-- Dados de exemplo para testes da loja virtual

-- Inserir usuário admin
INSERT INTO usuarios (username, password) VALUES ('admin', 'admin123');

-- Inserir categorias de exemplo
INSERT INTO categorias (nome, status) VALUES 
('Eletrônicos', 'ativo'),
('Roupas', 'ativo'),
('Casa e Jardim', 'ativo'),
('Esportes', 'ativo'),
('Livros', 'ativo');

-- Inserir produtos de exemplo
INSERT INTO produtos (nome, descricao, preco, categoria_id) VALUES 
('Smartphone Galaxy', 'Smartphone com tela de 6.1 polegadas, 128GB de armazenamento, câmera tripla de 64MP e bateria de longa duração. Ideal para quem busca tecnologia e qualidade.', 899.99, 1),

('Notebook Gamer', 'Notebook para jogos com processador Intel i7, 16GB RAM, SSD 512GB e placa de vídeo dedicada. Perfeito para gamers e profissionais que precisam de alta performance.', 2499.99, 1),

('Camiseta Básica', 'Camiseta 100% algodão, disponível em várias cores. Tecido macio e confortável, ideal para o dia a dia. Modelagem unissex.', 29.90, 2),

('Jeans Premium', 'Calça jeans de alta qualidade com modelagem moderna. Tecido resistente e confortável, perfeita para diversas ocasiões.', 89.90, 2),

('Sofá 3 Lugares', 'Sofá confortável para sala de estar, revestimento em tecido de alta qualidade. Design moderno e elegante que combina com qualquer decoração.', 1299.99, 3),

('Mesa de Jantar', 'Mesa de jantar para 6 pessoas em madeira maciça. Design clássico e atemporal, perfeita para reunir a família.', 899.99, 3),

('Tênis de Corrida', 'Tênis esportivo com tecnologia de amortecimento avançada. Ideal para corridas e atividades físicas. Disponível em várias cores.', 199.99, 4),

('Bicicleta Mountain Bike', 'Bicicleta para trilhas com 21 marchas, freios a disco e suspensão dianteira. Perfeita para aventuras ao ar livre.', 799.99, 4),

('Livro de Ficção', 'Romance bestseller internacional. Uma história envolvente que prende o leitor do início ao fim. Edição com capa dura.', 39.90, 5),

('Curso de Programação', 'Livro completo sobre desenvolvimento web com PHP, HTML, CSS e JavaScript. Ideal para iniciantes e intermediários.', 79.90, 5);

-- Inserir alguns pedidos de exemplo
INSERT INTO pedidos (nome_completo, whatsapp, entregar_endereco, rua, numero, bairro, cidade, cep, produto_id, status) VALUES 
('João Silva', '(11) 99999-1234', 1, 'Rua das Flores', '123', 'Centro', 'São Paulo', '01234-567', 1, 'pendente'),
('Maria Santos', '(11) 98888-5678', 0, '', '', '', '', '', 3, 'confirmado'),
('Pedro Oliveira', '(11) 97777-9012', 1, 'Av. Paulista', '1000', 'Bela Vista', 'São Paulo', '01310-100', 5, 'pendente');


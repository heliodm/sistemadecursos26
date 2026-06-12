-- Sistema de Cursos - Banco de Dados
-- Versão 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "-03:00";

CREATE DATABASE IF NOT EXISTS `sistemadecursos` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sistemadecursos`;

-- Tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS `configuracoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `chave` varchar(100) NOT NULL,
  `valor` text DEFAULT NULL,
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chave` (`chave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `configuracoes` (`chave`, `valor`) VALUES
('site_nome', 'Sistema de Cursos'),
('site_logo', ''),
('site_descricao', 'Plataforma de Gestão de Cursos'),
('menu_item_1_label', 'Início'),
('menu_item_1_url', '/index.php'),
('menu_item_1_ativo', '1'),
('menu_item_2_label', 'Cursos'),
('menu_item_2_url', '/index.php#cursos'),
('menu_item_2_ativo', '1'),
('menu_item_3_label', 'Certificados'),
('menu_item_3_url', '/certificados.php'),
('menu_item_3_ativo', '1'),
('pix_ativo', '1'),
('pix_chave', ''),
('pix_instrucoes', 'Após realizar o pagamento via PIX, envie o comprovante para confirmar sua inscrição.'),
('transferencia_ativo', '1'),
('transferencia_banco', ''),
('transferencia_agencia', ''),
('transferencia_conta', ''),
('transferencia_titular', ''),
('transferencia_instrucoes', 'Realize a transferência e envie o comprovante para confirmar sua inscrição.'),
('deposito_ativo', '1'),
('deposito_banco', ''),
('deposito_agencia', ''),
('deposito_conta', ''),
('deposito_titular', ''),
('deposito_instrucoes', 'Realize o depósito identificado e envie o comprovante para confirmar sua inscrição.'),
('cartao_ativo', '0'),
('cartao_instrucoes', 'Em breve disponível.'),
('infinitepay_handle', ''),
('infinitepay_webhook_secret', ''),
('cert_background', ''),
('cert_titulo', 'CERTIFICADO DE PARTICIPAÇÃO'),
('cert_texto', 'Certificamos que {NOME} participou do curso {CURSO}, realizado em {DATA}, com carga horária de {CARGA_HORARIA} horas.'),
('cert_assinatura_nome', ''),
('cert_assinatura_cargo', ''),
('cert_assinatura_imagem', ''),
('cert_logo', ''),
('cert_cidade', ''),
('cert_validade_texto', 'Este certificado pode ser validado em nosso site através do código: {CODIGO}'),
('email_contato', ''),
('rodape_texto', 'Todos os direitos reservados.');

-- Tabela de usuários do sistema
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `tipo` enum('admin','usuario') NOT NULL DEFAULT 'usuario',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuário administrador padrão (heliodm@outlook.com / Helio74*)
INSERT IGNORE INTO `usuarios` (`nome`, `email`, `senha`, `tipo`, `ativo`) VALUES
('heliodm', 'heliodm@outlook.com', '$2y$12$XEcBYASjzkxmB3CkpS0FzO2ERkbOSqcdvdRTHRJX0PRMfh3//do1i', 'admin', 1);

-- Tabela de cursos
CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(300) NOT NULL,
  `data_hora` datetime DEFAULT NULL,
  `data_fim` datetime DEFAULT NULL,
  `tipo` enum('presencial','online') NOT NULL DEFAULT 'presencial',
  `local` varchar(255) DEFAULT NULL,
  `carga_horaria` int(11) DEFAULT NULL,
  `vagas` int(11) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT 0.00,
  `descricao` text DEFAULT NULL,
  `descricao_curta` varchar(500) DEFAULT NULL,
  `ministrador_nome` varchar(150) DEFAULT NULL,
  `ministrador_bio` text DEFAULT NULL,
  `ministrador_foto` varchar(255) DEFAULT NULL,
  `capa_foto` varchar(255) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `inscricoes_abertas` tinyint(1) NOT NULL DEFAULT 1,
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `criado_por` (`criado_por`),
  CONSTRAINT `fk_curso_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de inscrições
CREATE TABLE IF NOT EXISTS `inscricoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curso_id` int(11) NOT NULL,
  `nome_completo` varchar(200) NOT NULL,
  `profissao` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `endereco` varchar(300) DEFAULT NULL,
  `cpf` varchar(20) DEFAULT NULL,
  `rg` varchar(30) DEFAULT NULL,
  `formacao` varchar(200) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `forma_pagamento` enum('pix','transferencia','deposito','cartao') DEFAULT NULL,
  `status_pagamento` enum('pendente','confirmado','cancelado') NOT NULL DEFAULT 'pendente',
  `payment_id` varchar(200) DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `certificado_emitido` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `curso_id` (`curso_id`),
  KEY `email` (`email`),
  KEY `cpf` (`cpf`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `fk_inscricao_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de certificados
CREATE TABLE IF NOT EXISTS `certificados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inscricao_id` int(11) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `codigo_unico` varchar(50) NOT NULL,
  `nome_completo` varchar(200) NOT NULL,
  `emitido_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `valido` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_unico` (`codigo_unico`),
  UNIQUE KEY `inscricao_id` (`inscricao_id`),
  KEY `curso_id` (`curso_id`),
  CONSTRAINT `fk_cert_inscricao` FOREIGN KEY (`inscricao_id`) REFERENCES `inscricoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cert_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

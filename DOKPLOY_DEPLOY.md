# Guia de Deploy do ShopMobile no Dokploy

## Visão Geral
Este guia detalha como fazer o deploy da aplicação PHP ShopMobile no Dokploy usando Dockerfile.

## Pré-requisitos
- Dokploy instalado e configurado
- Repositório Git com o código do projeto
- Banco de dados MySQL configurado

## Configuração do Projeto

### 1. Tipo de Build
**Recomendado: Dockerfile**
- O projeto já inclui um Dockerfile otimizado para PHP 8.1 com Apache
- Inclui todas as extensões PHP necessárias (PDO, MySQL, GD, cURL, etc.)
- Configurações de upload e permissões adequadas

### 2. Configurações de Ambiente
Configure as seguintes variáveis de ambiente no Dokploy:

```bash
# Banco de Dados
DB_HOST=seu_host_mysql
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
DB_NAME=shopmobile

# Mercado Pago (opcional)
MERCADOPAGO_PUBLIC_KEY=sua_public_key
MERCADOPAGO_ACCESS_TOKEN=seu_access_token
MERCADOPAGO_ENABLED=1

# Configurações da Loja
LOJA_NOME=Nome da Sua Loja
LOJA_WHATSAPP=5511999999999
```

### 3. Configuração de Volumes
Configure os seguintes volumes para persistência de dados:

**Volume Mount:**
- Volume Name: `shopmobile_uploads`
- Mount Path: `/var/www/html/uploads`

**Bind Mount (alternativo):**
- Host Path: `/var/dokploy/shopmobile/uploads`
- Mount Path: `/var/www/html/uploads`

### 4. Configuração de Porta
- **Porta do Container:** 80
- **Porta Externa:** Configurada automaticamente pelo Dokploy

### 5. Configuração de Domínio
No painel do Dokploy:
1. Vá para a aba "Domains"
2. Adicione seu domínio (ex: `loja.seudominio.com`)
3. Configure SSL automático se disponível

## Configuração do Banco de Dados

### Opção 1: Banco Dokploy (Recomendado)
1. Crie um banco MySQL no Dokploy
2. Configure as credenciais nas variáveis de ambiente
3. O sistema criará automaticamente as tabelas necessárias

### Opção 2: Banco Externo
1. Configure um banco MySQL externo
2. Importe o schema se necessário
3. Configure as variáveis de ambiente com as credenciais

## Configurações Avançadas

### Health Check (Recomendado)
Configure um health check para zero downtime:

```json
{
  "Test": [
    "CMD",
    "curl",
    "-f",
    "http://localhost:80/"
  ],
  "Interval": 30000000000,
  "Timeout": 10000000000,
  "StartPeriod": 30000000000,
  "Retries": 3
}
```

### Update Config (Para Rollbacks Automáticos)
```json
{
  "Parallelism": 1,
  "Delay": 10000000000,
  "FailureAction": "rollback",
  "Order": "start-first"
}
```

### Configuração de Recursos
- **CPU:** 0.5 cores (mínimo)
- **Memória:** 512MB (mínimo)
- **Réplicas:** 1 (pode ser aumentado conforme necessário)

## Passos para Deploy

### 1. Preparação do Repositório
1. Certifique-se que todos os arquivos estão no repositório Git
2. Verifique se o Dockerfile está na raiz do projeto
3. Configure o arquivo `.env.example` com as variáveis necessárias

### 2. Criação da Aplicação no Dokploy
1. Acesse o painel do Dokploy
2. Clique em "Create Application"
3. Selecione "Git Repository"
4. Configure:
   - **Repository URL:** URL do seu repositório
   - **Branch:** main (ou sua branch principal)
   - **Build Type:** Dockerfile
   - **Dockerfile Path:** ./Dockerfile

### 3. Configuração de Variáveis
1. Vá para a aba "Environment"
2. Adicione todas as variáveis de ambiente necessárias
3. Salve as configurações

### 4. Configuração de Volumes
1. Vá para a aba "Mounts"
2. Configure o volume para uploads conforme descrito acima
3. Salve as configurações

### 5. Deploy
1. Clique em "Deploy"
2. Monitore os logs de build
3. Aguarde a conclusão do deploy

### 6. Configuração de Domínio
1. Vá para a aba "Domains"
2. Adicione seu domínio
3. Configure SSL se disponível

## Configuração Pós-Deploy

### 1. Acesso Inicial
1. Acesse a aplicação pelo domínio configurado
2. O sistema criará automaticamente as tabelas do banco
3. Configure as informações da loja no painel admin

### 2. Upload de Arquivos
1. Teste o upload de imagens de produtos
2. Verifique se as permissões estão corretas
3. Configure os sliders se necessário

### 3. Configuração do Mercado Pago (Opcional)
1. Configure as chaves do Mercado Pago
2. Teste os pagamentos em ambiente de sandbox
3. Ative o ambiente de produção quando estiver pronto

## Monitoramento e Manutenção

### Logs
- Monitore os logs da aplicação no painel do Dokploy
- Configure alertas se necessário

### Backups
- Configure backups automáticos do banco de dados
- Faça backup regular da pasta uploads

### Atualizações
- Use webhooks para deploy automático
- Configure CI/CD se necessário
- Teste sempre em ambiente de staging primeiro

## Troubleshooting

### Problemas Comuns
1. **Erro de conexão com banco:** Verifique as variáveis de ambiente
2. **Problemas de upload:** Verifique as permissões dos volumes
3. **Erro 500:** Verifique os logs da aplicação
4. **Problemas de SSL:** Verifique a configuração do domínio

### Comandos Úteis
```bash
# Verificar logs da aplicação
docker logs <container_id>

# Acessar container
docker exec -it <container_id> /bin/bash

# Verificar permissões
ls -la /var/www/html/uploads
```

## Considerações de Segurança

1. **Variáveis de Ambiente:** Nunca commite credenciais no código
2. **SSL:** Sempre use HTTPS em produção
3. **Backups:** Configure backups regulares
4. **Atualizações:** Mantenha o sistema atualizado
5. **Monitoramento:** Configure alertas de segurança

## Suporte

Para suporte adicional:
- Consulte a documentação oficial do Dokploy
- Verifique os logs de erro
- Entre em contato com o suporte técnico se necessário